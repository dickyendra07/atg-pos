<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promo;
use App\Models\PromoRequirement;
use App\Models\PromoReward;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\ProductDeletionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Batch 4: Product permanent delete only when the Product is disposable; otherwise nonaktifkan.
 */
class ProductSafeDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outletA;

    private Outlet $outletB;

    private Brand $brand;

    private ProductCategory $category;

    private Ingredient $milk;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outletA = Outlet::create(['name' => 'Outlet A', 'code' => 'OA', 'is_active' => true]);
        $this->outletB = Outlet::create(['name' => 'Outlet B', 'code' => 'OB', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->milk = Ingredient::create([
            'ingredient_category_id' => IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true])->id,
            'name' => 'Milk', 'code' => 'MILK', 'unit' => 'ml', 'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true,
        ]);
        $this->milk->outlets()->sync([$this->outletA->id, $this->outletB->id]);

        $this->owner = $this->makeUser('owner', 'owner', [$this->outletA, $this->outletB]);
    }

    // ---- A / B / I: safe deletes ------------------------------------------------------------------

    public function test_fresh_product_without_dependencies_can_be_deleted_permanently(): void
    {
        $product = $this->makeProduct('Fresh');

        $verdict = app(ProductDeletionPolicy::class)->evaluate($product);
        $this->assertTrue($verdict['can_hard_delete']);
        $this->assertSame([], $verdict['blockers']);

        $this->actingAs($this->owner)
            ->delete(route('backoffice.products.destroy-permanent', $product->id))
            ->assertRedirect(route('backoffice.products.index'))
            ->assertSessionHas('success', 'Product berhasil dihapus permanen.');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_outlet', ['product_id' => $product->id]);
    }

    public function test_never_used_product_with_variants_and_recipe_is_deleted_with_exactly_its_own_children(): void
    {
        [$product, $variants, $recipe] = $this->makeProductWithRecipe('Disposable', 2);
        [$otherProduct, $otherVariants, $otherRecipe] = $this->makeProductWithRecipe('Keeper', 1);

        $verdict = app(ProductDeletionPolicy::class)->evaluate($product);
        $this->assertTrue($verdict['can_hard_delete']);
        $this->assertSame(['variants' => 2, 'recipes' => 1, 'outlets' => 2], $verdict['impact']);
        $this->assertStringContainsString('2 Variant dan 1 Recipe yang belum pernah dipakai juga akan dihapus.', $verdict['confirm_note']);

        $this->actingAs($this->owner)->delete(route('backoffice.products.destroy-permanent', $product->id))->assertSessionHas('success');

        // gone: the Product and ONLY its own children, no orphan rows
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        foreach ($variants as $variant) {
            $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
            $this->assertDatabaseMissing('product_variant_outlet', ['product_variant_id' => $variant->id]);
        }
        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_items', ['recipe_id' => $recipe->id]);
        $this->assertSame(0, DB::table('product_outlet')->where('product_id', $product->id)->count());

        // untouched: the other Product, its Variant/Recipe/items, and the shared Ingredient
        $this->assertDatabaseHas('products', ['id' => $otherProduct->id]);
        $this->assertDatabaseHas('product_variants', ['id' => $otherVariants[0]->id]);
        $this->assertDatabaseHas('recipes', ['id' => $otherRecipe->id]);
        $this->assertSame(1, RecipeItem::where('recipe_id', $otherRecipe->id)->count());
        $this->assertDatabaseHas('ingredients', ['id' => $this->milk->id]);
        $this->assertSame(0, $this->orphans(), 'no row points at a missing parent');
    }

    public function test_inactive_product_with_no_history_can_be_deleted(): void
    {
        $product = $this->makeProduct('Inactive safe', false);

        $this->actingAs($this->owner)->delete(route('backoffice.products.destroy-permanent', $product->id))->assertSessionHas('success');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_deletion_does_not_depend_on_is_active(): void
    {
        $active = $this->makeProduct('Active safe', true);

        $this->assertTrue(app(ProductDeletionPolicy::class)->evaluate($active)['can_hard_delete']);
        $this->assertContains('Product masih aktif dan akan hilang dari Cashier.', app(ProductDeletionPolicy::class)->evaluate($active)['warnings']);
    }

    // ---- C / D / E / J: blockers ------------------------------------------------------------------

    public function test_product_in_sales_history_is_blocked_by_product_id_or_by_variant_id(): void
    {
        [$byProduct, $v1] = $this->makeProductWithRecipe('Sold by product', 1);
        [$byVariant, $v2] = $this->makeProductWithRecipe('Sold by variant', 1);

        $this->sale($byProduct, null);          // item only knows the Product
        $this->sale(null, $v2[0]);              // item only knows the Variant

        foreach ([$byProduct, $byVariant] as $product) {
            $this->assertBlocked($product, 'sudah memiliki riwayat transaksi');
        }
    }

    public function test_blocked_delete_keeps_the_product_its_children_and_the_history_and_explains_why(): void
    {
        [$product, $variants, $recipe] = $this->makeProductWithRecipe('Sold', 1);
        $sale = $this->sale($product, $variants[0]);

        $this->actingAs($this->owner)
            ->delete(route('backoffice.products.destroy-permanent', $product->id))
            ->assertRedirect(route('backoffice.products.index').'')
            ->assertSessionHas('error', 'Product tidak dapat dihapus permanen karena sudah memiliki riwayat transaksi.');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('product_variants', ['id' => $variants[0]->id]);
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseHas('product_outlet', ['product_id' => $product->id, 'outlet_id' => $this->outletA->id]);
        $item = $sale->items()->first();
        $this->assertSame($product->id, $item->product_id, 'history keeps its link to the Product');
        $this->assertSame($variants[0]->id, $item->product_variant_id);
    }

    public function test_product_used_by_a_promo_is_blocked_in_every_way_a_promo_can_reference_a_variant(): void
    {
        foreach (['requirement row', 'reward row', 'promo requirement column', 'promo reward column'] as $how) {
            [$product, $variants] = $this->makeProductWithRecipe('Promo '.$how, 1);
            $promo = Promo::create(['name' => 'Promo '.$how, 'status' => 'draft', 'is_active' => false]);

            match ($how) {
                'requirement row' => PromoRequirement::create(['promo_id' => $promo->id, 'product_variant_id' => $variants[0]->id, 'qty' => 1]),
                'reward row' => PromoReward::create(['promo_id' => $promo->id, 'reward_type' => 'free_item', 'product_variant_id' => $variants[0]->id, 'qty' => 1]),
                'promo requirement column' => $promo->update(['requirement_product_variant_id' => $variants[0]->id]),
                'promo reward column' => $promo->update(['reward_product_variant_id' => $variants[0]->id]),
            };

            $this->assertBlocked($product, 'masih digunakan oleh Promo');

            // an ended / inactive Promo still blocks: promo configuration is not silently cascaded away
            $this->actingAs($this->owner)->delete(route('backoffice.products.destroy-permanent', $product->id))
                ->assertSessionHas('error', 'Product tidak dapat dihapus permanen karena masih digunakan oleh Promo.');
            $this->assertDatabaseHas('products', ['id' => $product->id]);
            $this->assertDatabaseHas('product_variants', ['id' => $variants[0]->id]);
        }
    }

    public function test_promo_rows_survive_a_blocked_delete(): void
    {
        [$product, $variants] = $this->makeProductWithRecipe('In promo', 1);
        $promo = Promo::create(['name' => 'P', 'status' => 'active', 'is_active' => true]);
        PromoRequirement::create(['promo_id' => $promo->id, 'product_variant_id' => $variants[0]->id, 'qty' => 1]);

        $this->actingAs($this->owner)->delete(route('backoffice.products.destroy-permanent', $product->id));

        $this->assertSame(1, PromoRequirement::where('product_variant_id', $variants[0]->id)->count());
    }

    public function test_inactive_product_with_history_is_still_blocked(): void
    {
        $product = $this->makeProduct('Inactive sold', false);
        $this->sale($product, null);

        $this->assertBlocked($product, 'sudah memiliki riwayat transaksi');
    }

    public function test_several_blockers_are_listed_in_one_sentence(): void
    {
        [$product, $variants] = $this->makeProductWithRecipe('Both', 1);
        $this->sale($product, $variants[0]);
        $promo = Promo::create(['name' => 'P', 'status' => 'active', 'is_active' => true]);
        PromoRequirement::create(['promo_id' => $promo->id, 'product_variant_id' => $variants[0]->id, 'qty' => 1]);

        $this->actingAs($this->owner)->delete(route('backoffice.products.destroy-permanent', $product->id))
            ->assertSessionHas('error', 'Product tidak dapat dihapus permanen karena sudah memiliki riwayat transaksi dan masih digunakan oleh Promo.');
    }

    public function test_a_recipe_that_points_across_products_blocks_the_delete(): void
    {
        [$product, $variants] = $this->makeProductWithRecipe('Owner of variant', 1);
        [$other, , $otherRecipe] = $this->makeProductWithRecipe('Owner of recipe', 1);

        // anomaly: the other Product's Recipe is attached to this Product's Variant
        $otherRecipe->update(['product_variant_id' => $variants[0]->id]);

        $this->assertBlocked($product, 'data Recipe yang tidak konsisten');
        $this->assertBlocked($other, 'data Recipe yang tidak konsisten');

        $this->actingAs($this->owner)->delete(route('backoffice.products.destroy-permanent', $product->id))->assertSessionHas('error');
        $this->assertDatabaseHas('recipes', ['id' => $otherRecipe->id]);
        $this->assertSame($variants[0]->id, $otherRecipe->fresh()->product_variant_id, 'the other Recipe was not detached');
    }

    // ---- G: global, not outlet-scoped -------------------------------------------------------------

    public function test_the_check_is_global_even_when_the_active_outlet_cannot_see_the_dependency(): void
    {
        // Product at both outlets; its sale happened at Outlet B only.
        [$product, $variants] = $this->makeProductWithRecipe('Shared', 1);
        $this->sale($product, $variants[0], $this->outletB);

        // Backoffice is looking at Outlet A.
        $session = ['active_backoffice_outlet_id' => $this->outletA->id];

        $html = $this->actingAs($this->owner)->withSession($session)->get(route('backoffice.products.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="product-'.$product->id.'"', $html, 'visible from Outlet A');
        $this->assertStringContainsString('data-bo-blocked="Product tidak dapat dihapus permanen karena sudah memiliki riwayat transaksi."', $html);

        $this->actingAs($this->owner)->withSession($session)
            ->delete(route('backoffice.products.destroy-permanent', $product->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertSame($this->outletA->id, session('active_backoffice_outlet_id'), 'Active Outlet untouched');
    }

    // ---- H / race: server decides again ------------------------------------------------------------

    public function test_forged_request_for_an_unsafe_product_is_refused_by_the_server(): void
    {
        [$product, $variants] = $this->makeProductWithRecipe('Forged', 1);
        $this->sale($product, $variants[0]);

        // no page, no button: just the raw DELETE
        $this->actingAs($this->owner)
            ->delete(route('backoffice.products.destroy-permanent', $product->id), ['return_to' => '/backoffice/products?search=Forged'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_that_became_used_after_the_page_rendered_is_refused(): void
    {
        [$product, $variants] = $this->makeProductWithRecipe('Race', 1);

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.index'))->getContent();
        $this->assertStringContainsString(route('backoffice.products.destroy-permanent', $product->id), $html, 'the page offered a permanent delete');

        $this->sale($product, $variants[0]);   // ...then a sale happens

        $this->actingAs($this->owner)
            ->delete(route('backoffice.products.destroy-permanent', $product->id))
            ->assertSessionHas('error', 'Product tidak dapat dihapus permanen karena sudah memiliki riwayat transaksi.');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('product_variants', ['id' => $variants[0]->id]);
    }

    public function test_deleting_a_product_that_is_already_gone_is_a_friendly_notice_not_a_500(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('backoffice.products.destroy-permanent', 987654))
            ->assertRedirect(route('backoffice.products.index'))
            ->assertSessionHas('warning', 'Product sudah tidak ada.');
    }

    public function test_an_unknown_database_dependency_rolls_everything_back_and_never_shows_sql(): void
    {
        [$product, $variants, $recipe] = $this->makeProductWithRecipe('Unknown dependency', 1);

        // a reference the policy does not know about, enforced by the database (RESTRICT)
        Schema::create('zz_unknown_refs', function ($table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
        });
        DB::table('zz_unknown_refs')->insert(['product_id' => $product->id]);

        $response = $this->actingAs($this->owner)
            ->delete(route('backoffice.products.destroy-permanent', $product->id))
            ->assertRedirect();

        $response->assertSessionHas('error', fn ($message) => str_contains($message, 'masih terhubung dengan data lain')
            && ! preg_match('/sql|constraint|foreign|integrity|zz_unknown/i', $message));

        // all-or-nothing: the children deleted before the failing statement came back
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('product_variants', ['id' => $variants[0]->id]);
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id]);
        $this->assertSame(1, RecipeItem::where('recipe_id', $recipe->id)->count());
    }

    // ---- L: authorization ---------------------------------------------------------------------------

    public function test_only_full_access_users_can_delete_permanently(): void
    {
        $product = $this->makeProduct('Guarded');

        $adminOutlet = $this->makeUser('admin-outlet', 'admin_outlet', [$this->outletA]);
        $cashier = $this->makeUser('kasir', 'kasir', [$this->outletA]);

        $this->actingAs($adminOutlet)->delete(route('backoffice.products.destroy-permanent', $product->id))->assertForbidden();
        $this->actingAs($cashier)->delete(route('backoffice.products.destroy-permanent', $product->id))->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        $adminPusat = $this->makeUser('admin-pusat', 'admin_pusat', [$this->outletA]);
        $this->actingAs($adminPusat)->delete(route('backoffice.products.destroy-permanent', $product->id))->assertSessionHas('success');
    }

    public function test_nonaktifkan_keeps_its_existing_permissions_and_never_deletes(): void
    {
        [$product, $variants] = $this->makeProductWithRecipe('To inactivate', 1);
        $adminOutlet = $this->makeUser('admin-outlet', 'admin_outlet', [$this->outletA]);

        $this->actingAs($adminOutlet)
            ->delete(route('backoffice.products.destroy', $product->id))
            ->assertSessionHas('success', 'Product berhasil dinonaktifkan. Histori dan data terkait tetap disimpan.');

        $this->assertFalse((bool) $product->fresh()->is_active);
        $this->assertDatabaseHas('product_variants', ['id' => $variants[0]->id]);
        $this->assertDatabaseHas('recipes', ['product_id' => $product->id]);
    }

    // ---- K: return_to / position ----------------------------------------------------------------------

    public function test_return_to_context_is_kept_for_nonaktifkan_blocked_and_deleted(): void
    {
        $listUrl = '/backoffice/products?search=Kafei&category_id='.$this->category->id.'&page=2';

        // nonaktifkan: row stays -> anchor
        $a = $this->makeProduct('Kafei A');
        $this->actingAs($this->owner)->delete(route('backoffice.products.destroy', $a->id), ['return_to' => $listUrl])
            ->assertRedirect(url($listUrl.'#product-'.$a->id));

        // blocked: row stays -> anchor, same list
        $b = $this->makeProduct('Kafei B');
        $this->sale($b, null);
        $this->delete(route('backoffice.products.destroy-permanent', $b->id), ['return_to' => $listUrl])
            ->assertRedirect(url($listUrl.'#product-'.$b->id))
            ->assertSessionHas('error');

        // deleted: row gone -> no dead anchor
        $c = $this->makeProduct('Kafei C');
        $this->delete(route('backoffice.products.destroy-permanent', $c->id), ['return_to' => $listUrl])
            ->assertRedirect(url($listUrl))
            ->assertSessionHas('success');

        // hostile return_to still falls back to the index
        $d = $this->makeProduct('Kafei D');
        $this->delete(route('backoffice.products.destroy-permanent', $d->id), ['return_to' => 'https://evil.example'])
            ->assertRedirect(route('backoffice.products.index'));
    }

    // ---- UI -------------------------------------------------------------------------------------------

    public function test_product_index_offers_the_right_actions_per_state(): void
    {
        $safeActive = $this->makeProduct('Safe Active');
        $safeInactive = $this->makeProduct('Safe Inactive', false);
        [$used, $usedVariants] = $this->makeProductWithRecipe('Used Active', 1);
        $this->sale($used, $usedVariants[0]);
        $usedInactive = $this->makeProduct('Used Inactive', false);
        $this->sale($usedInactive, null);

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.index'))->assertOk()->getContent();
        $row = fn (Product $p) => $this->rowHtml($html, $p);

        // A: safe -> confirm dialog with the exact copy
        $this->assertStringContainsString('data-bo-confirm-title="Hapus Product Permanen?"', $row($safeActive));
        $this->assertStringContainsString('data-bo-confirm-body="Product “Safe Active” akan dihapus permanen. Tindakan ini tidak dapat dibatalkan."', $row($safeActive));
        $this->assertStringContainsString('data-bo-confirm-label="Hapus Permanen"', $row($safeActive));
        $this->assertStringContainsString('data-bo-confirm-title="Nonaktifkan Product?"', $row($safeActive));
        $this->assertStringContainsString('data-bo-confirm-body="Product tidak akan tampil di Cashier, tetapi histori dan data terkait tetap disimpan."', $row($safeActive));

        // C: inactive + safe -> permanent delete offered, nothing to nonaktifkan
        $this->assertStringContainsString(route('backoffice.products.destroy-permanent', $safeInactive->id), $row($safeInactive));
        $this->assertStringNotContainsString('Nonaktifkan Product?', $row($safeInactive));

        // B: unsafe + active -> Nonaktifkan, and "Hapus Permanen" only explains
        $this->assertStringContainsString('Nonaktifkan Product?', $row($used));
        $this->assertStringNotContainsString(route('backoffice.products.destroy-permanent', $used->id), $row($used));
        $this->assertStringContainsString('data-bo-blocked="Product tidak dapat dihapus permanen karena sudah memiliki riwayat transaksi."', $row($used));

        // C: unsafe + inactive -> still explained, no permanent delete
        $this->assertStringContainsString('data-bo-blocked=', $row($usedInactive));
        $this->assertStringNotContainsString(route('backoffice.products.destroy-permanent', $usedInactive->id), $row($usedInactive));

        // no raw browser confirm() on the product rows, and the shared dialog is on the page
        $this->assertStringNotContainsString('onsubmit="return confirm(', $row($safeActive));
        $this->assertStringContainsString('id="bo-confirm-overlay"', $html);
    }

    public function test_admin_outlet_does_not_see_permanent_delete_at_all(): void
    {
        $product = $this->makeProduct('Visible');
        $adminOutlet = $this->makeUser('admin-outlet', 'admin_outlet', [$this->outletA]);

        $html = $this->actingAs($adminOutlet)->get(route('backoffice.products.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('backoffice.products.destroy-permanent', $product->id), $html);
        $this->assertStringNotContainsString('Hapus Permanen', $this->rowHtml($html, $product));
        $this->assertStringContainsString('Nonaktifkan Product?', $this->rowHtml($html, $product));
    }

    public function test_ui_copy_has_no_technical_words(): void
    {
        $product = $this->makeProduct('Copy');
        $html = $this->rowHtml($this->actingAs($this->owner)->get(route('backoffice.products.index'))->getContent(), $product);

        foreach (['destroy', 'foreign key', 'constraint', 'soft delete'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, strip_tags(preg_replace('/action="[^"]*"/', '', $html)), $word);
        }
    }

    // ---- insight + performance ---------------------------------------------------------------------------

    public function test_product_counts_by_deletability_for_a_seeded_catalog(): void
    {
        $safe = [$this->makeProduct('S1'), $this->makeProduct('S2', false), $this->makeProductWithRecipe('S3', 2)[0]];
        $sold = [$this->makeProduct('H1'), $this->makeProduct('H2')];
        foreach ($sold as $product) {
            $this->sale($product, null);
        }
        [$promoProduct, $promoVariants] = $this->makeProductWithRecipe('PR1', 1);
        PromoRequirement::create(['promo_id' => Promo::create(['name' => 'P', 'status' => 'active', 'is_active' => true])->id, 'product_variant_id' => $promoVariants[0]->id, 'qty' => 1]);
        [$other] = $this->makeProductWithRecipe('OT1', 1);
        Recipe::where('product_id', $other->id)->update(['product_id' => $promoProduct->id]);   // cross-product recipe

        $verdicts = app(ProductDeletionPolicy::class)->evaluateMany(Product::all());
        $summary = ['total' => count($verdicts), 'safe' => 0, 'sales' => 0, 'promo' => 0, 'other' => 0];

        foreach ($verdicts as $verdict) {
            $codes = array_column($verdict['blockers'], 'code');
            $summary['safe'] += $verdict['can_hard_delete'] ? 1 : 0;
            $summary['sales'] += in_array(ProductDeletionPolicy::BLOCK_SALES, $codes, true) ? 1 : 0;
            $summary['promo'] += in_array(ProductDeletionPolicy::BLOCK_PROMO, $codes, true) ? 1 : 0;
            $summary['other'] += in_array(ProductDeletionPolicy::BLOCK_RECIPE, $codes, true) ? 1 : 0;
        }

        if (getenv('PARITY_TABLE')) {
            fwrite(STDERR, "\nproduct deletability: ".json_encode($summary)."\n");
        }

        $this->assertSame(['total' => 7, 'safe' => 3, 'sales' => 2, 'promo' => 1, 'other' => 2], $summary);
    }

    public function test_deletion_verdicts_use_a_fixed_number_of_queries(): void
    {
        $counts = [];

        foreach ([1, 40] as $target) {
            while (Product::count() < $target) {
                $this->makeProductWithRecipe('Bulk '.Product::count(), 2);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            app(ProductDeletionPolicy::class)->evaluateMany(Product::all());
            $counts[$target] = count(DB::getQueryLog()) - 1;   // minus the Product::all() above
            DB::disableQueryLog();
        }

        $this->assertSame($counts[1], $counts[40], json_encode($counts));
    }

    // ---- helpers ---------------------------------------------------------------------------------------

    private function assertBlocked(Product $product, string $reasonText): void
    {
        $verdict = app(ProductDeletionPolicy::class)->evaluate($product);

        $this->assertFalse($verdict['can_hard_delete'], $product->name);
        $this->assertTrue($verdict['can_inactivate'], 'nonaktifkan is always possible');
        $this->assertStringContainsString($reasonText, $verdict['blocked_message']);
        $this->assertStringNotContainsStringIgnoringCase('constraint', $verdict['blocked_message']);
    }

    private function rowHtml(string $html, Product $product): string
    {
        $start = strpos($html, 'id="product-'.$product->id.'"');
        $this->assertNotFalse($start, 'row for '.$product->name);
        $end = strpos($html, '</tr>', $start);

        return substr($html, $start, $end - $start);
    }

    /** Rows whose parent no longer exists (what a careless delete would leave behind). */
    private function orphans(): int
    {
        return DB::table('product_variants')->whereNotIn('product_id', DB::table('products')->select('id'))->count()
            + DB::table('recipes')->whereNotIn('product_id', DB::table('products')->select('id'))->count()
            + DB::table('recipe_items')->whereNotIn('recipe_id', DB::table('recipes')->select('id'))->count()
            + DB::table('product_outlet')->whereNotIn('product_id', DB::table('products')->select('id'))->count()
            + DB::table('product_variant_outlet')->whereNotIn('product_variant_id', DB::table('product_variants')->select('id'))->count();
    }

    private function makeProduct(string $name, bool $active = true): Product
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->category->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '-', $name)).'-'.uniqid(),
            'is_active' => $active,
        ]);
        $product->outlets()->sync([$this->outletA->id, $this->outletB->id]);

        return $product;
    }

    /** @return array{0: Product, 1: ProductVariant[], 2: Recipe} */
    private function makeProductWithRecipe(string $name, int $variantCount): array
    {
        $product = $this->makeProduct($name);
        $variants = [];

        for ($i = 0; $i < $variantCount; $i++) {
            $variant = ProductVariant::create([
                'product_id' => $product->id, 'name' => 'V'.$i, 'code' => $product->code.'-V'.$i,
                'price' => 20000, 'price_dine_in' => 20000, 'price_delivery' => 22000, 'is_active' => true,
            ]);
            $variant->outlets()->sync([$this->outletA->id, $this->outletB->id]);
            $variants[] = $variant;
        }

        $recipe = Recipe::create(['product_id' => $product->id, 'product_variant_id' => $variants[0]->id, 'name' => 'Recipe '.$name, 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $this->milk->id, 'qty' => 10, 'unit' => 'ml']);

        return [$product, $variants, $recipe];
    }

    private function sale(?Product $product, ?ProductVariant $variant, ?Outlet $outlet = null): SalesTransaction
    {
        $transaction = SalesTransaction::create([
            'transaction_number' => 'TRX-'.uniqid(),
            'user_id' => $this->owner->id,
            'outlet_id' => ($outlet ?? $this->outletA)->id,
            'subtotal' => 20000,
            'grand_total' => 20000,
            'status' => 'completed',
        ]);

        $transaction->items()->create([
            'product_id' => $product?->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product?->name ?? 'Deleted product',
            'variant_name' => $variant?->name,
            'qty' => 1, 'price' => 20000, 'line_total' => 20000,
        ]);

        return $transaction;
    }

    private function makeUser(string $username, string $roleCode, array $outlets): User
    {
        $user = User::create([
            'name' => $username, 'username' => $username, 'email' => $username.'@example.test', 'password' => 'password',
            'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id,
            'outlet_id' => $outlets[0]->id, 'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }
}
