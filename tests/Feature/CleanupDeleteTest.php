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
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\IngredientWriter;
use App\Services\ProductWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * TEMPORARY cleanup delete: Product / Variant / Ingredient are tombstoned (SoftDeletes), Recipe is really
 * deleted. These tests pin: the flag, the roles, the typed confirmation, the blockers, that every
 * historical table is byte-for-byte untouched, that history still reads, and that the code is reusable.
 */
class CleanupDeleteTest extends TestCase
{
    use RefreshDatabase;

    /** Tables that are history / money / audit. They must never change because of a cleanup delete. */
    private const HISTORY_TABLES = [
        'sales_transactions', 'sales_transaction_items', 'stock_movements', 'stock_transfers', 'stock_balances',
        'stock_adjustments', 'stock_adjustment_items', 'purchase_receipts', 'purchase_receipt_items',
        'ingredient_productions', 'ingredient_production_items', 'backoffice_notifications',
    ];

    private Outlet $outlet;

    private Brand $brand;

    private ProductCategory $category;

    private IngredientCategory $ingredientCategory;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['backoffice.destructive_delete_enabled' => true]);

        $this->outlet = Outlet::create(['name' => 'Outlet A', 'code' => 'OA', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->ingredientCategory = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->owner = $this->makeUser('owner', 'owner');
    }

    // ---- Flag ---------------------------------------------------------------------------------------------

    public function test_flag_defaults_to_false_in_code(): void
    {
        // setUp() turned it on for the other tests; the shipped value (phpunit.xml / .env.example) is off.
        $config = require base_path('config/backoffice.php');
        $this->assertFalse($config['destructive_delete_enabled']);
        $this->assertStringContainsString('BACKOFFICE_DESTRUCTIVE_DELETE_ENABLED=false', file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString("env('BACKOFFICE_DESTRUCTIVE_DELETE_ENABLED', false)", file_get_contents(base_path('config/backoffice.php')));
    }

    public function test_flag_off_hides_every_delete_button_and_rejects_every_request(): void
    {
        config(['backoffice.destructive_delete_enabled' => false]);

        [$product, $variant, $recipe, $ingredient] = $this->world();
        $before = $this->fingerprint();

        foreach ([
            route('backoffice.products.index'),
            route('backoffice.products.edit', $product),
            route('backoffice.variants.index'),
            route('backoffice.ingredients.index'),
            route('backoffice.recipes.index'),
        ] as $url) {
            $html = $this->actingAs($this->owner)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('data-cleanup-delete', $html, $url);
            $this->assertStringNotContainsString('bo-cleanup-overlay', $html, $url);
        }

        foreach ([['product', $product], ['variant', $variant], ['recipe', $recipe], ['ingredient', $ingredient]] as [$type, $model]) {
            $this->actingAs($this->owner)
                ->delete(route('backoffice.cleanup.destroy', [$type, $model->id]), ['confirmation' => $model->name])
                ->assertForbidden();
            $this->actingAs($this->owner)->getJson(route('backoffice.cleanup.impact', [$type, $model->id]))->assertForbidden();
        }

        // the legacy URL cannot bypass the flag either
        $this->actingAs($this->owner)
            ->delete(route('backoffice.ingredients.destroy', $ingredient), ['confirmation' => $ingredient->name])
            ->assertForbidden();

        $this->assertSame($before, $this->fingerprint());
        $this->assertNull(Product::find($product->id)->deleted_at);
        $this->assertNotNull(Recipe::find($recipe->id));
    }

    // ---- Roles --------------------------------------------------------------------------------------------

    public function test_only_owner_and_admin_pusat_can_use_it_and_everyone_else_gets_403(): void
    {
        [$product, $variant, $recipe, $ingredient] = $this->world();
        $before = $this->fingerprint();

        foreach (['admin_outlet', 'staff_gudang', 'kasir'] as $role) {
            $user = $this->makeUser($role, $role);

            foreach ([['product', $product], ['variant', $variant], ['recipe', $recipe], ['ingredient', $ingredient]] as [$type, $model]) {
                $this->actingAs($user)
                    ->delete(route('backoffice.cleanup.destroy', [$type, $model->id]), ['confirmation' => $model->name])
                    ->assertForbidden();
                $this->actingAs($user)->getJson(route('backoffice.cleanup.impact', [$type, $model->id]))->assertForbidden();
            }

            $this->actingAs($user)
                ->delete(route('backoffice.ingredients.destroy', $ingredient), ['confirmation' => $ingredient->name])
                ->assertForbidden();
        }

        $this->assertSame($before, $this->fingerprint());
        $this->assertNull(Product::find($product->id)->deleted_at);
        $this->assertNull(Ingredient::find($ingredient->id)->deleted_at);

        // the button is not drawn for a limited role that can open the Product pages
        $adminOutlet = $this->makeUser('ao2', 'admin_outlet');
        $html = $this->actingAs($adminOutlet)->get(route('backoffice.products.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-cleanup-delete', $html);
    }

    public function test_owner_and_admin_pusat_see_the_buttons_everywhere_the_flag_is_on(): void
    {
        [$product] = $this->world();

        foreach (['owner', 'admin_pusat'] as $role) {
            $user = $role === 'owner' ? $this->owner : $this->makeUser($role, $role);

            foreach ([
                route('backoffice.products.index'),
                route('backoffice.products.edit', $product),
                route('backoffice.variants.index'),
                route('backoffice.ingredients.index'),
                route('backoffice.recipes.index'),
            ] as $url) {
                $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
                $this->assertStringContainsString('data-cleanup-delete', $html, $role.' '.$url);
                $this->assertStringContainsString('bo-cleanup-overlay', $html, $role.' '.$url);
            }
        }

        // Product danger zone + Variant + Recipe row + drawer wiring in the modern workspace
        $general = $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$product, 'section' => 'general']))->getContent();
        $this->assertStringContainsString('cleanup-danger-zone', $general);
        $this->assertStringContainsString('data-cleanup-type="product"', $general);
    }

    // ---- Confirmation -------------------------------------------------------------------------------------

    public function test_wrong_or_missing_confirmation_deletes_nothing(): void
    {
        [$product, $variant, $recipe, $ingredient] = $this->world(withIngredientFree: true);
        $before = $this->fingerprint();

        foreach ([['product', $product], ['variant', $variant], ['recipe', $recipe], ['ingredient', $this->freeIngredient()]] as [$type, $model]) {
            foreach (['', 'salah', mb_strtolower($model->name), $model->name.' x'] as $typed) {
                if ($typed === $model->name) {
                    continue;
                }

                $this->actingAs($this->owner)
                    ->delete(route('backoffice.cleanup.destroy', [$type, $model->id]), ['confirmation' => $typed])
                    ->assertRedirect()
                    ->assertSessionHas('error');
            }
        }

        $this->assertNull(Product::find($product->id)->deleted_at);
        $this->assertNull(ProductVariant::find($variant->id)->deleted_at);
        $this->assertNotNull(Recipe::find($recipe->id));
        $this->assertNull($this->freeIngredient()->deleted_at);
        $this->assertSame($before, $this->fingerprint());
    }

    // ---- A: unused entities -------------------------------------------------------------------------------

    public function test_case_a_unused_product_variant_ingredient_recipe_all_delete_and_disappear_operationally(): void
    {
        [$product, $variant, $recipe] = $this->world(withIngredientFree: true);
        $free = $this->freeIngredient();
        $before = $this->fingerprint();

        // Recipe first (real delete)
        $this->del('recipe', $recipe)->assertRedirect()->assertSessionHas('success');
        $this->assertNull(Recipe::find($recipe->id));
        $this->assertSame(0, RecipeItem::where('recipe_id', $recipe->id)->count());

        $this->del('ingredient', $free)->assertSessionHas('success');
        $this->assertSoftDeleted('ingredients', ['id' => $free->id]);

        $this->del('variant', $variant)->assertSessionHas('success');
        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);

        $this->del('product', $product)->assertSessionHas('success');
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $this->assertSame($before, $this->fingerprint());
        $this->assertOperationallyGone($product, $variant, $free);
    }

    public function test_product_delete_tombstones_all_variants_and_hard_deletes_their_recipes_and_pivots(): void
    {
        [$product, $variant, $recipe] = $this->world();
        $second = $this->makeVariant($product, 'Large');
        $recipeTwo = Recipe::create(['product_id' => $product->id, 'product_variant_id' => $second->id, 'name' => 'R2', 'is_active' => false]);
        RecipeItem::create(['recipe_id' => $recipeTwo->id, 'ingredient_id' => $this->ingredientNamed('Susu')->id, 'qty' => 5, 'unit' => 'ml']);

        $otherProduct = $this->makeProduct('Other');
        $otherVariant = $this->makeVariant($otherProduct, 'Reg');
        $otherRecipe = Recipe::create(['product_id' => $otherProduct->id, 'product_variant_id' => $otherVariant->id, 'name' => 'Other R', 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $otherRecipe->id, 'ingredient_id' => $this->ingredientNamed('Susu')->id, 'qty' => 7, 'unit' => 'ml']);
        $otherSnapshot = $this->snapshotRecipe($otherRecipe);

        $this->del('product', $product)->assertSessionHas('success');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
        $this->assertSoftDeleted('product_variants', ['id' => $second->id]);
        $this->assertNull(Recipe::find($recipe->id));
        $this->assertNull(Recipe::find($recipeTwo->id));
        $this->assertSame(0, RecipeItem::whereIn('recipe_id', [$recipe->id, $recipeTwo->id])->count());
        $this->assertSame(0, DB::table('product_outlet')->where('product_id', $product->id)->count());
        $this->assertSame(0, DB::table('product_variant_outlet')->whereIn('product_variant_id', [$variant->id, $second->id])->count());

        // another Product, its Variant and Recipe are untouched
        $this->assertNull(Product::find($otherProduct->id)->deleted_at);
        $this->assertNull(ProductVariant::find($otherVariant->id)->deleted_at);
        $this->assertSame($otherSnapshot, $this->snapshotRecipe(Recipe::find($otherRecipe->id)));
    }

    // ---- B: Recipe ----------------------------------------------------------------------------------------

    public function test_case_b_recipe_delete_removes_only_its_own_items_and_changes_no_other_recipe(): void
    {
        [$product, $variant, $recipe] = $this->world();
        $sibling = Recipe::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'name' => 'Sibling', 'is_active' => false]);
        RecipeItem::create(['recipe_id' => $sibling->id, 'ingredient_id' => $this->ingredientNamed('Susu')->id, 'qty' => 12.5, 'unit' => 'ml']);
        $siblingSnapshot = $this->snapshotRecipe($sibling);
        $itemsBefore = RecipeItem::where('recipe_id', $recipe->id)->count();
        $this->assertGreaterThan(0, $itemsBefore);
        $before = $this->fingerprint();

        $this->del('recipe', $recipe)->assertSessionHas('success');

        $this->assertNull(Recipe::find($recipe->id));
        $this->assertSame(0, RecipeItem::where('recipe_id', $recipe->id)->count());
        // not auto-selected / activated / repaired
        $fresh = Recipe::find($sibling->id);
        $this->assertFalse((bool) $fresh->is_active, 'the sibling stays inactive: no recipe is activated automatically');
        $this->assertSame($siblingSnapshot, $this->snapshotRecipe($fresh), 'sibling recipe and its qty/unit are unchanged byte for byte');
        $this->assertNull(ProductVariant::find($variant->id)->deleted_at);
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_recipe_delete_is_available_from_the_workspace_recipe_flow(): void
    {
        [$product, $variant, $recipe] = $this->world();

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$product, 'section' => 'recipe']))->assertOk()->getContent();

        $this->assertStringContainsString('data-cleanup-type="recipe"', $html);
        $this->assertStringContainsString('data-cleanup-id="'.$recipe->id.'"', $html);
        $this->assertStringContainsString('Hapus Permanen', $html);
    }

    // ---- C: Variant with sales ----------------------------------------------------------------------------

    public function test_case_c_variant_with_sales_is_tombstoned_and_the_sale_is_untouched(): void
    {
        [$product, $variant] = $this->world();
        $sibling = $this->makeVariant($product, 'Large');
        $transaction = $this->sale($product, $variant);
        $before = $this->fingerprint();
        $siblingBefore = ProductVariant::find($sibling->id)->getAttributes();

        $impact = $this->actingAs($this->owner)->getJson(route('backoffice.cleanup.impact', ['variant', $variant->id]))->assertOk()->json('impact');
        $this->assertTrue($impact['can_delete']);
        $this->assertContains(['label' => 'Baris riwayat transaksi', 'value' => 1], $impact['counts']);

        $this->del('variant', $variant)->assertSessionHas('success');

        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
        $this->assertSame($before, $this->fingerprint(), 'sale rows are byte-for-byte identical (FK is not nulled: the row is only tombstoned)');
        $item = $transaction->items()->first();
        $this->assertSame($variant->id, (int) $item->product_variant_id, 'the sale item still points at the variant');
        $this->assertSame($siblingBefore, ProductVariant::find($sibling->id)->getAttributes(), 'sibling Variant untouched');
        $this->assertNull(ProductVariant::find($variant->id), 'gone from every scoped query');
        $this->assertSame('Reg', $item->variant->name, 'history still resolves the deleted variant');
    }

    // ---- D: Ingredient with history ----------------------------------------------------------------------

    public function test_case_d_ingredient_with_history_is_tombstoned_and_all_history_survives_and_still_reads(): void
    {
        $gula = $this->ingredientNamed('Gula Aren');
        $this->inventoryHistory($gula);
        $before = $this->fingerprint();
        $counts = $this->historyCountsFor($gula);
        $this->assertSame(['balances' => 1, 'movements' => 1, 'transfers' => 1, 'adjustments' => 1, 'receipts' => 1, 'productions' => 1], $counts);

        $impact = $this->actingAs($this->owner)->getJson(route('backoffice.cleanup.impact', ['ingredient', $gula->id]))->json('impact');
        $this->assertTrue($impact['can_delete']);

        $this->del('ingredient', $gula)->assertSessionHas('success');

        $this->assertSoftDeleted('ingredients', ['id' => $gula->id]);
        $this->assertSame($before, $this->fingerprint(), 'movements, transfers, balances, adjustments, receipts, productions unchanged');
        $this->assertSame($counts, $this->historyCountsFor($gula));
        $this->assertSame(0, DB::table('ingredient_outlet')->where('ingredient_id', $gula->id)->count());

        // historical screens still show the Ingredient's identity
        $movements = $this->actingAs($this->owner)->get(route('backoffice.stock-movements.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Gula Aren', $movements, 'stock movement history');

        $adjustment = DB::table('stock_adjustments')->first();
        $this->actingAs($this->owner)->get(route('backoffice.stock-adjustments.show', $adjustment->id))->assertOk()->assertSee('Gula Aren');

        $receipt = DB::table('purchase_receipts')->first();
        $this->actingAs($this->owner)->get(route('backoffice.purchase-history.show', $receipt->id))->assertOk()->assertSee('Gula Aren');

        $production = DB::table('ingredient_productions')->first();
        $this->actingAs($this->owner)->get(route('backoffice.productions.show', $production->id))->assertOk()->assertSee('Gula Aren');

        // ...while operational screens no longer know it
        $this->assertOperationallyGone(null, null, $gula);
        $balances = $this->actingAs($this->owner)->get(route('backoffice.stock-balances.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Gula Aren', $balances, 'no ghost balance rows for a removed Ingredient');
    }

    public function test_historical_relations_resolve_a_tombstoned_master_while_operational_ones_do_not(): void
    {
        $gula = $this->ingredientNamed('Gula Aren');
        $this->inventoryHistory($gula);
        [$product, $variant] = $this->world();
        $this->sale($product, $variant);

        $this->del('ingredient', $gula);
        $this->del('product', $product);

        $movement = \App\Models\StockMovement::first();
        $this->assertSame('Gula Aren', $movement->ingredient->name);
        $this->assertSame('Gula Aren', \App\Models\StockTransfer::first()->ingredient->name);
        $this->assertSame('Gula Aren', \App\Models\StockAdjustmentItem::first()->ingredient->name);
        $this->assertSame('Gula Aren', \App\Models\PurchaseReceiptItem::first()->ingredient->name);
        $this->assertSame('Gula Aren', \App\Models\IngredientProductionItem::first()->ingredient->name);

        $item = \App\Models\SalesTransactionItem::first();
        $this->assertSame($product->name, $item->product->name);
        $this->assertSame('Reg', $item->variant->name);
        $this->assertSame($product->name, $item->product_name, 'the stored snapshot is still the source of truth');

        $this->assertNull(Ingredient::find($gula->id));
        $this->assertSame(1, Ingredient::withTrashed()->where('id', $gula->id)->count());
    }

    // ---- E: Product with sales ----------------------------------------------------------------------------

    public function test_case_e_product_with_sales_is_gone_operationally_and_history_and_receipt_still_read(): void
    {
        [$product, $variant] = $this->world();
        $transaction = $this->sale($product, $variant);
        $before = $this->fingerprint();

        $this->del('product', $product)->assertSessionHas('success');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
        $this->assertSame($before, $this->fingerprint());
        $this->assertOperationallyGone($product, $variant, null);

        $this->actingAs($this->owner)->get(route('backoffice.transactions.show', $transaction))->assertOk()->assertSee($product->name);
        $this->actingAs($this->owner)->get(route('backoffice.transactions.receipt', $transaction))->assertOk()->assertSee($product->name);
        $this->actingAs($this->owner)->get(route('backoffice.transactions.index'))->assertOk();
    }

    // ---- F: Promo blocking --------------------------------------------------------------------------------

    public function test_case_f_active_or_future_or_draft_promos_block_product_and_variant_and_are_never_changed(): void
    {
        [$product, $variant] = $this->world();

        foreach ([
            'aktif' => ['status' => 'active', 'is_active' => true, 'start_date' => null, 'end_date' => null],
            'terjadwal' => ['status' => 'active', 'is_active' => true, 'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(9)->toDateString()],
            'draft' => ['status' => 'draft', 'is_active' => false, 'start_date' => null, 'end_date' => null],
            'berakhir hari ini' => ['status' => 'active', 'is_active' => true, 'start_date' => null, 'end_date' => now()->toDateString()],
        ] as $label => $attrs) {
            $promo = $this->promo('Promo '.$label, $variant, $attrs);
            $promoBefore = $this->promoSnapshot($promo);
            $before = $this->fingerprint();

            foreach ([['variant', $variant], ['product', $product]] as [$type, $model]) {
                $impact = $this->actingAs($this->owner)->getJson(route('backoffice.cleanup.impact', [$type, $model->id]))->json('impact');
                $this->assertFalse($impact['can_delete'], $label.' '.$type);
                $this->assertSame('promo', $impact['blockers'][0]['code']);
                $this->assertStringContainsString('Promo '.$label, implode(' ', $impact['blockers'][0]['items']));

                $this->del($type, $model)->assertSessionHas('error');
                $this->assertNull($model->fresh()->deleted_at, $label.' '.$type.' must stay');
            }

            $this->assertSame($promoBefore, $this->promoSnapshot($promo->fresh()), 'the promo is never rewritten');
            $this->assertSame($before, $this->fingerprint());
            $promo->requirements()->delete();
            $promo->delete();
        }
    }

    public function test_promo_reward_reference_also_blocks(): void
    {
        [, $variant] = $this->world();
        $other = $this->makeVariant($variant->product, 'Large');
        $promo = $this->promo('Beli Reg gratis Large', $variant, ['status' => 'active', 'is_active' => true]);
        $promo->update(['reward_product_variant_id' => $other->id]);

        $this->del('variant', $other)->assertSessionHas('error');
        $this->assertNull($other->fresh()->deleted_at);
    }

    public function test_finished_or_discontinued_promo_does_not_block_and_keeps_its_configuration_and_still_renders(): void
    {
        [$product, $variant] = $this->world();
        $expired = $this->promo('Promo Lama', $variant, ['status' => 'active', 'is_active' => false, 'start_date' => now()->subDays(20)->toDateString(), 'end_date' => now()->subDays(2)->toDateString()]);
        $stopped = $this->promo('Promo Stop', $variant, ['status' => 'discontinued', 'is_active' => false]);
        $expiredBefore = $this->promoSnapshot($expired);
        $stoppedBefore = $this->promoSnapshot($stopped);

        $impact = $this->actingAs($this->owner)->getJson(route('backoffice.cleanup.impact', ['product', $product->id]))->json('impact');
        $this->assertTrue($impact['can_delete']);
        $this->assertContains(['label' => 'Promo lama (selesai / dihentikan)', 'value' => 2], $impact['counts']);

        $this->del('product', $product)->assertSessionHas('success');

        // historical Promo configuration is exactly as it was (requirement rows included)
        $this->assertSame($expiredBefore, $this->promoSnapshot($expired->fresh()));
        $this->assertSame($stoppedBefore, $this->promoSnapshot($stopped->fresh()));

        $this->actingAs($this->owner)->get(route('backoffice.promos.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('backoffice.promos.edit', $expired))->assertOk();
    }

    // ---- Ingredient used by a Recipe ----------------------------------------------------------------------

    public function test_ingredient_used_by_a_recipe_is_blocked_and_nothing_is_modified(): void
    {
        [, , $recipe, $susu] = $this->world();
        $recipeSnapshot = $this->snapshotRecipe($recipe);
        $before = $this->fingerprint();

        $impact = $this->actingAs($this->owner)->getJson(route('backoffice.cleanup.impact', ['ingredient', $susu->id]))->assertOk()->json('impact');
        $this->assertFalse($impact['can_delete']);
        $this->assertSame('recipe', $impact['blockers'][0]['code']);
        $this->assertStringContainsString($recipe->name, implode(' ', $impact['blockers'][0]['items']));
        $this->assertStringContainsString('#'.$recipe->id, implode(' ', $impact['blockers'][0]['items']));

        $this->del('ingredient', $susu)->assertSessionHas('error');

        $this->assertNull(Ingredient::find($susu->id)->deleted_at);
        $this->assertSame($recipeSnapshot, $this->snapshotRecipe(Recipe::find($recipe->id)));
        $this->assertSame($before, $this->fingerprint());

        // the legacy URL goes through the same check
        $this->actingAs($this->owner)
            ->delete(route('backoffice.ingredients.destroy', $susu), ['confirmation' => $susu->name])
            ->assertSessionHas('error');
        $this->assertNull(Ingredient::find($susu->id)->deleted_at);
    }

    public function test_ingredient_used_by_a_production_recipe_is_blocked_too(): void
    {
        $semi = $this->ingredientNamed('Sirup', Ingredient::TYPE_SEMI_FINISHED);
        $raw = $this->ingredientNamed('Gula Pasir');
        $productionRecipeId = DB::table('ingredient_production_recipes')->insertGetId([
            'output_ingredient_id' => $semi->id, 'name' => 'Masak Sirup', 'output_qty' => 1, 'output_unit' => 'ml', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ingredient_production_recipe_items')->insert([
            'ingredient_production_recipe_id' => $productionRecipeId, 'input_ingredient_id' => $raw->id, 'qty' => 2, 'unit' => 'gram',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([$raw, $semi] as $ingredient) {
            $this->del('ingredient', $ingredient)->assertSessionHas('error');
            $this->assertNull(Ingredient::find($ingredient->id)->deleted_at);
        }
    }

    // ---- Code reuse ---------------------------------------------------------------------------------------

    public function test_deleted_codes_are_released_and_can_be_created_again(): void
    {
        $product = $this->makeProduct('Kopi', 'CODE-X');
        $variant = $this->makeVariant($product, 'Reg', 'CODE-X-V');
        $ingredient = $this->ingredientNamed('Bubuk', code: 'ING-X');

        $this->del('variant', $variant);
        $this->del('product', $product);
        $this->del('ingredient', $ingredient);

        $this->assertSame('CODE-X__del'.$product->id, Product::withTrashed()->find($product->id)->code);
        $this->assertSame('CODE-X-V__del'.$variant->id, ProductVariant::withTrashed()->find($variant->id)->code);
        $this->assertSame('ING-X__del'.$ingredient->id, Ingredient::withTrashed()->find($ingredient->id)->code);

        $newProduct = $this->makeProduct('Kopi', 'CODE-X');
        $newVariant = $this->makeVariant($newProduct, 'Reg', 'CODE-X-V');
        $newIngredient = $this->ingredientNamed('Bubuk', code: 'ING-X');

        $this->assertNull($newProduct->fresh()->deleted_at);
        $this->assertSame('CODE-X', $newProduct->fresh()->code);
        $this->assertSame('CODE-X-V', $newVariant->fresh()->code);
        $this->assertSame('ING-X', $newIngredient->fresh()->code);
    }

    public function test_variant_code_is_unique_per_product_so_a_same_product_recreate_works(): void
    {
        $product = $this->makeProduct('Teh', 'TEH');
        $first = $this->makeVariant($product, 'Hot', 'TEH-HOT');

        $this->del('variant', $first);
        $again = $this->makeVariant($product, 'Hot', 'TEH-HOT');

        $this->assertSame('TEH-HOT', $again->fresh()->code);
        $this->assertSame(2, ProductVariant::withTrashed()->where('product_id', $product->id)->count());
    }

    public function test_tombstone_code_respects_the_column_length_and_keeps_the_suffix_intact(): void
    {
        $long = str_repeat('A', 255);
        $product = $this->makeProduct('Panjang', $long);

        $this->del('product', $product);

        $code = Product::withTrashed()->find($product->id)->code;
        $suffix = '__del'.$product->id;
        $this->assertSame(255, strlen($code));
        $this->assertStringEndsWith($suffix, $code);
        $this->assertSame(str_repeat('A', 255 - strlen($suffix)), substr($code, 0, -strlen($suffix)));
        $this->makeProduct('Panjang Baru', $long);   // the original code is free again
    }

    public function test_tombstone_code_never_collides_with_a_code_somebody_typed_by_hand(): void
    {
        $product = $this->makeProduct('Dobel', 'DUP');
        $squatter = $this->makeProduct('Penyerobot', 'DUP__del'.$product->id);

        $this->del('product', $product);

        $code = Product::withTrashed()->find($product->id)->code;
        $this->assertNotSame($squatter->code, $code);
        $this->assertStringStartsWith('DUP__del'.$product->id, $code);
        $this->assertSame('DUP__del'.$product->id, $squatter->fresh()->code, 'the live row is never renamed');
    }

    public function test_validation_ignores_removed_rows_but_still_rejects_live_duplicates(): void
    {
        $product = $this->makeProduct('Kopi', 'KOPI');
        $ingredient = $this->ingredientNamed('Susu Segar');

        $productRules = fn (string $code) => Validator::make(
            ['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Kopi', 'code' => $code, 'is_active' => 1],
            app(ProductWriter::class)->generalRules()
        );
        $ingredientRules = fn (string $name) => Validator::make(
            ['ingredient_category_id' => $this->ingredientCategory->id, 'name' => $name, 'unit' => 'ml', 'ingredient_type' => 'raw', 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => 1, 'outlet_ids' => [$this->outlet->id]],
            app(IngredientWriter::class)->rules()
        );

        $this->assertTrue($productRules('KOPI')->fails(), 'live duplicate code still rejected');
        $this->assertTrue($ingredientRules('Susu Segar')->fails(), 'live duplicate name still rejected');

        $this->del('product', $product);
        $this->del('ingredient', $ingredient);

        $this->assertFalse($productRules('KOPI')->fails(), 'a removed Product does not block its code');
        $this->assertFalse($ingredientRules('Susu Segar')->fails(), 'a removed Ingredient does not block its name');
    }

    public function test_removed_master_data_cannot_be_chosen_by_a_forged_id(): void
    {
        [$product, $variant] = $this->world();
        $gula = $this->ingredientNamed('Gula Aren');
        $this->del('ingredient', $gula);
        $this->del('variant', $variant);

        $rules = ['i' => 'required|exists:ingredients,id,deleted_at,NULL', 'v' => 'required|exists:product_variants,id,deleted_at,NULL'];
        $this->assertTrue(Validator::make(['i' => $gula->id, 'v' => $variant->id], $rules)->fails());
        $this->assertSame(['i', 'v'], array_keys(Validator::make(['i' => $gula->id, 'v' => $variant->id], $rules)->errors()->messages()));
    }

    // ---- Misc ---------------------------------------------------------------------------------------------

    public function test_deleting_something_already_gone_is_a_soft_warning_not_an_error(): void
    {
        [$product] = $this->world();
        $this->del('product', $product);

        $this->actingAs($this->owner)
            ->delete(route('backoffice.cleanup.destroy', ['product', $product->id]), ['confirmation' => $product->name])
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_impact_blockers_are_computed_again_inside_the_delete(): void
    {
        [, $variant] = $this->world();

        // dialog was drawn while nothing blocked...
        $impact = $this->actingAs($this->owner)->getJson(route('backoffice.cleanup.impact', ['variant', $variant->id]))->json('impact');
        $this->assertTrue($impact['can_delete']);

        // ...then somebody activates a Promo on it
        $this->promo('Terlambat', $variant, ['status' => 'active', 'is_active' => true]);

        $this->del('variant', $variant)->assertSessionHas('error');
        $this->assertNull($variant->fresh()->deleted_at);
    }

    public function test_cashier_no_longer_lists_a_removed_product_and_has_no_access_to_the_endpoints(): void
    {
        [$product, $variant] = $this->world();
        $cashier = $this->makeUser('kasir1', 'kasir');

        $show = fn () => $this->actingAs($cashier)->withSession(['auth_portal' => 'cashier'])->get(route('cashier.index'))->assertOk()->getContent();

        $this->assertStringContainsString($product->name, $show(), 'fixture sanity: the cashier sees the product before');

        $this->del('product', $product);

        $this->flushSession();   // the delete's own success flash would otherwise be rendered on this page
        $this->assertStringNotContainsString($product->name, $show());
        $this->actingAs($cashier)->delete(route('backoffice.cleanup.destroy', ['variant', $variant->id]), ['confirmation' => 'Reg'])->assertForbidden();
    }

    public function test_lists_search_and_pickers_do_not_show_removed_rows(): void
    {
        [$product, $variant, $recipe, $susu] = $this->world();
        $gula = $this->ingredientNamed('Gula Aren');

        $this->del('ingredient', $gula);
        $this->del('product', $product);

        $this->assertStringNotContainsString('Gula Aren', $this->actingAs($this->owner)->get(route('backoffice.ingredients.index', ['search' => 'Gula']))->getContent());
        $this->assertStringNotContainsString($product->name, $this->actingAs($this->owner)->get(route('backoffice.products.index', ['search' => 'Kopi']))->getContent());
        $this->assertStringNotContainsString($product->name, $this->actingAs($this->owner)->get(route('backoffice.variants.index'))->getContent());
        $this->assertStringNotContainsString($product->name, $this->actingAs($this->owner)->get(route('backoffice.recipes.index'))->getContent());
        $this->actingAs($this->owner)->get(route('backoffice.products.edit', $product))->assertNotFound();
    }

    // ---- Helpers ------------------------------------------------------------------------------------------

    private function del(string $type, $model)
    {
        return $this->actingAs($this->owner)->delete(route('backoffice.cleanup.destroy', [$type, $model->id]), ['confirmation' => $model->name]);
    }

    private function assertOperationallyGone(?Product $product, ?ProductVariant $variant, ?Ingredient $ingredient): void
    {
        if ($product) {
            $this->assertNull(Product::find($product->id));
            $this->assertSame(0, Product::where('name', $product->name)->count());
            $this->assertSame(0, Product::query()->availableAtOutlet($this->outlet->id)->whereKey($product->id)->count());
        }

        if ($variant) {
            $this->assertNull(ProductVariant::find($variant->id));
            $this->assertSame(0, ProductVariant::query()->availableAtOutlet($this->outlet->id)->whereKey($variant->id)->count());
        }

        if ($ingredient) {
            $this->assertNull(Ingredient::find($ingredient->id));
            $this->assertSame(0, Ingredient::query()->availableAtOutlet($this->outlet->id)->whereKey($ingredient->id)->count());
        }
    }

    /** md5 of every row of every history table, so "unchanged" means unchanged. */
    private function fingerprint(): array
    {
        $result = [];

        foreach (self::HISTORY_TABLES as $table) {
            $result[$table] = md5(json_encode(DB::table($table)->orderBy('id')->get()));
        }

        return $result;
    }

    private function snapshotRecipe(Recipe $recipe): string
    {
        return md5(json_encode([
            DB::table('recipes')->where('id', $recipe->id)->first(),
            DB::table('recipe_items')->where('recipe_id', $recipe->id)->orderBy('id')->get(),
        ]));
    }

    private function promoSnapshot(Promo $promo): string
    {
        return md5(json_encode([
            DB::table('promos')->where('id', $promo->id)->first(),
            DB::table('promo_requirements')->where('promo_id', $promo->id)->orderBy('id')->get(),
            DB::table('promo_rewards')->where('promo_id', $promo->id)->orderBy('id')->get(),
        ]));
    }

    /** @return array{0: Product, 1: ProductVariant, 2: Recipe, 3: Ingredient} */
    private function world(bool $withIngredientFree = false): array
    {
        $product = $this->makeProduct('Es Kopi Susu');
        $variant = $this->makeVariant($product, 'Reg');
        $susu = $this->ingredientNamed('Susu');
        $recipe = Recipe::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'name' => 'Resep Es Kopi', 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $susu->id, 'qty' => 120, 'unit' => 'ml']);

        if ($withIngredientFree) {
            $this->freeIngredient();
        }

        return [$product, $variant, $recipe, $susu];
    }

    private function freeIngredient(): Ingredient
    {
        return Ingredient::where('name', 'Bebas Pakai')->first() ?? $this->ingredientNamed('Bebas Pakai');
    }

    private function makeProduct(string $name, ?string $code = null): Product
    {
        $product = Product::create([
            'brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => $name,
            'code' => $code ?? strtoupper(str_replace(' ', '-', $name)).'-'.uniqid(), 'is_active' => true,
        ]);
        $product->outlets()->sync([$this->outlet->id]);

        return $product;
    }

    private function makeVariant(Product $product, string $name, ?string $code = null): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'name' => $name, 'code' => $code ?? strtoupper($product->code.'-'.$name),
            'price' => 20000, 'price_dine_in' => 20000, 'price_delivery' => 22000, 'is_active' => true,
        ]);
        $variant->outlets()->sync([$this->outlet->id]);

        return $variant;
    }

    private function ingredientNamed(string $name, string $type = Ingredient::TYPE_RAW, ?string $code = null): Ingredient
    {
        if ($existing = Ingredient::where('name', $name)->first()) {
            return $existing;
        }

        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->ingredientCategory->id, 'name' => $name,
            'code' => $code ?? strtoupper(str_replace(' ', '-', $name)), 'unit' => 'ml', 'ingredient_type' => $type,
            'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true,
        ]);
        $ingredient->outlets()->sync([$this->outlet->id]);

        return $ingredient;
    }

    private function promo(string $name, ProductVariant $variant, array $attrs): Promo
    {
        $promo = Promo::create(array_merge([
            'name' => $name, 'requirement_logic' => 'and', 'requirement_qty' => 1, 'reward_type' => 'percent', 'reward_value' => 10,
            'reward_qty' => 0, 'status' => 'active', 'is_active' => true,
        ], $attrs));
        PromoRequirement::create(['promo_id' => $promo->id, 'product_variant_id' => $variant->id, 'qty' => 1]);

        return $promo;
    }

    private function sale(Product $product, ProductVariant $variant): SalesTransaction
    {
        $transaction = SalesTransaction::create([
            'transaction_number' => 'TRX-'.uniqid(), 'user_id' => $this->owner->id, 'outlet_id' => $this->outlet->id,
            'subtotal' => 20000, 'grand_total' => 20000, 'status' => 'completed',
            'payment_method' => 'cash', 'payment_status' => 'paid', 'amount_paid' => 25000, 'change_amount' => 5000,
        ]);
        $transaction->items()->create([
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name,
            'variant_name' => $variant->name, 'qty' => 1, 'price' => 20000, 'line_total' => 20000,
        ]);

        return $transaction;
    }

    /** One row in every inventory history table for this Ingredient. */
    private function inventoryHistory(Ingredient $ingredient): void
    {
        $now = now();
        DB::table('stock_balances')->insert(['ingredient_id' => $ingredient->id, 'location_type' => 'outlet', 'location_id' => $this->outlet->id, 'qty_on_hand' => 40, 'created_at' => $now, 'updated_at' => $now]);
        $movementId = DB::table('stock_movements')->insertGetId(['ingredient_id' => $ingredient->id, 'location_type' => 'outlet', 'location_id' => $this->outlet->id, 'movement_type' => 'purchase_in', 'qty_in' => 40, 'qty_out' => 0, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('stock_transfers')->insert(['transfer_number' => 'TRF-1', 'ingredient_id' => $ingredient->id, 'qty' => 5, 'status' => 'received', 'from_location_type' => 'outlet', 'from_location_id' => $this->outlet->id, 'to_location_type' => 'outlet', 'to_location_id' => $this->outlet->id, 'created_at' => $now, 'updated_at' => $now]);
        $adjustmentId = DB::table('stock_adjustments')->insertGetId(['reference' => 'ADJ-1', 'location_type' => 'outlet', 'location_id' => $this->outlet->id, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('stock_adjustment_items')->insert(['stock_adjustment_id' => $adjustmentId, 'ingredient_id' => $ingredient->id, 'stock_movement_id' => $movementId, 'unit' => 'ml', 'system_qty' => 40, 'actual_qty' => 38, 'difference' => -2, 'created_at' => $now, 'updated_at' => $now]);
        $receiptId = DB::table('purchase_receipts')->insertGetId(['reference_number' => 'PR-1', 'destination_type' => 'outlet', 'destination_id' => $this->outlet->id, 'received_date' => $now->toDateString(), 'status' => 'posted', 'total_amount' => 400, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('purchase_receipt_items')->insert(['purchase_receipt_id' => $receiptId, 'ingredient_id' => $ingredient->id, 'qty' => 40, 'unit' => 'ml', 'unit_price' => 10, 'line_total' => 400, 'created_at' => $now, 'updated_at' => $now]);

        $semi = $this->ingredientNamed('Sirup Hasil', Ingredient::TYPE_SEMI_FINISHED);
        $productionRecipeId = DB::table('ingredient_production_recipes')->insertGetId(['output_ingredient_id' => $semi->id, 'name' => 'Masak Sirup', 'output_qty' => 1, 'output_unit' => 'ml', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $productionId = DB::table('ingredient_productions')->insertGetId([
            'ingredient_production_recipe_id' => $productionRecipeId, 'output_ingredient_id' => $semi->id, 'location_type' => 'outlet', 'location_id' => $this->outlet->id,
            'batch_qty' => 1, 'output_qty' => 1, 'output_unit' => 'ml', 'status' => 'completed', 'produced_by_user_id' => $this->owner->id, 'produced_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('ingredient_production_items')->insert(['ingredient_production_id' => $productionId, 'ingredient_id' => $ingredient->id, 'item_type' => 'input', 'qty' => 2, 'unit' => 'ml', 'created_at' => $now, 'updated_at' => $now]);
    }

    private function historyCountsFor(Ingredient $ingredient): array
    {
        return [
            'balances' => DB::table('stock_balances')->where('ingredient_id', $ingredient->id)->count(),
            'movements' => DB::table('stock_movements')->where('ingredient_id', $ingredient->id)->count(),
            'transfers' => DB::table('stock_transfers')->where('ingredient_id', $ingredient->id)->count(),
            'adjustments' => DB::table('stock_adjustment_items')->where('ingredient_id', $ingredient->id)->count(),
            'receipts' => DB::table('purchase_receipt_items')->where('ingredient_id', $ingredient->id)->count(),
            'productions' => DB::table('ingredient_production_items')->where('ingredient_id', $ingredient->id)->count(),
        ];
    }

    private function makeUser(string $username, string $roleCode): User
    {
        $user = User::create([
            'name' => $username, 'username' => $username, 'email' => $username.'@example.test', 'password' => 'password',
            'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id,
            'outlet_id' => $this->outlet->id, 'is_active' => true,
        ]);
        $user->outlets()->sync([$this->outlet->id]);

        return $user;
    }
}
