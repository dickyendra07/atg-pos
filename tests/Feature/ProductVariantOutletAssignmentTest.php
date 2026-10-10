<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CashierShift;
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
use App\Models\SalesTransactionItem;
use App\Models\User;
use App\Services\ProductDuplicateGuard;
use App\Services\SaleEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Feedback #04: one global Product / Variant shared by several outlets, without clones.
 *
 * Opt-in Variant assignment when a Product gains an outlet, assignment-only CSV imports for rows that already
 * exist, a warning (never a block or a merge) for look-alike Products, the legacy variants.outlet_id kept inert,
 * and the Cashier / promo quick-add still driven by SaleEligibilityService.
 */
class ProductVariantOutletAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $a;

    private Outlet $b;

    private Outlet $c;

    private Brand $brand;

    private ProductCategory $category;

    private Product $product;

    private ProductVariant $variant;

    private Recipe $recipe;

    private Ingredient $milk;

    private User $owner;

    private User $limitedA;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Outlet::create(['name' => 'Alpha', 'code' => 'OA', 'is_active' => true]);
        $this->b = Outlet::create(['name' => 'Bravo', 'code' => 'OB', 'is_active' => true]);
        $this->c = Outlet::create(['name' => 'Charlie', 'code' => 'OC', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);

        $this->product = $this->makeProduct('Ube Latte', 'UBE', [$this->a]);
        $this->variant = $this->makeVariant($this->product, 'Regular', 'UBE-R', 20000, 22000, [$this->a]);

        $category = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->milk = Ingredient::create(['ingredient_category_id' => $category->id, 'name' => 'Milk', 'code' => 'MILK', 'unit' => 'ml', 'ingredient_type' => Ingredient::TYPE_RAW, 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true]);
        $this->milk->outlets()->sync([$this->a->id, $this->b->id]);
        $this->recipe = Recipe::create(['product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'name' => 'Recipe Ube', 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $this->recipe->id, 'ingredient_id' => $this->milk->id, 'qty' => 10, 'unit' => 'ml']);

        $this->owner = $this->makeUser('owner', [$this->a, $this->b, $this->c]);
        $this->limitedA = $this->makeUser('admin_outlet', [$this->a]);
        $this->cashier = $this->makeUser('owner', [$this->a, $this->b]);

        foreach ([$this->a, $this->b] as $outlet) {
            CashierShift::create(['user_id' => $this->cashier->id, 'outlet_id' => $outlet->id, 'started_at' => now(), 'opening_cash' => 0, 'status' => 'open']);
        }
    }

    // ================================================================================================
    // Phase 1: Product gains / loses an outlet
    // ================================================================================================

    public function test_product_gaining_an_outlet_without_the_option_assigns_only_the_product(): void
    {
        $this->saveOutlets($this->owner, [$this->a, $this->b])->assertOk()->assertJsonPath('message', 'Outlet Product berhasil disimpan.');

        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $this->product->outlets()->pluck('outlets.id')->all());
        $this->assertSame([$this->a->id], $this->variant->outlets()->pluck('outlets.id')->all());
        $this->assertSame(1, ProductVariant::count(), 'no Variant row is ever created');
        $this->assertSame('variant_not_at_outlet', $this->saleStatus($this->variant, $this->b)['reason']);
    }

    public function test_the_workspace_explains_that_variants_still_need_an_assignment(): void
    {
        $this->saveOutlets($this->owner, [$this->a, $this->b])->assertOk();

        $page = $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'outlets']))->assertOk()->getContent();

        $this->assertStringContainsString('data-pw-variant-gap', $page);
        $this->assertStringContainsString('Bravo: Regular', $page);
        $this->assertStringContainsString('name="assign_variants"', $page);
        $this->assertStringNotContainsString('name="assign_variants" value="1" data-pw-assign-variants checked', $page, 'the option is never pre-checked');
    }

    public function test_the_preview_names_the_variants_and_outlets_and_matches_what_the_save_does(): void
    {
        $inactive = $this->makeVariant($this->product, 'Old', 'UBE-OLD', 1000, 1000, [$this->a], false);

        $off = $this->actingAs($this->owner)->postJson(route('backoffice.products.workspace.outlets.preview', $this->product), ['outlet_ids' => [$this->a->id, $this->b->id]])->assertOk();
        $off->assertJsonPath('assignable_variant_ids', [$this->variant->id])->assertJsonPath('assigned_variant_ids', []);
        $this->assertStringContainsString('data-pw-preview-gap', $off->json('html'));
        $this->assertStringContainsString('Regular', $off->json('html'));
        $this->assertStringContainsString('Bravo', $off->json('html'));
        $this->assertStringContainsString('data-pw-preview-inactive', $off->json('html'));

        $on = $this->actingAs($this->owner)->postJson(route('backoffice.products.workspace.outlets.preview', $this->product), ['outlet_ids' => [$this->a->id, $this->b->id], 'assign_variants' => 1])->assertOk();
        $on->assertJsonPath('assigned_variant_ids', [$this->variant->id])->assertJsonPath('has_consequences', true);
        $this->assertStringContainsString('data-pw-preview-assign="'.$this->variant->id.'"', $on->json('html'));

        // The preview wrote nothing.
        $this->assertSame([$this->a->id], $this->variant->outlets()->pluck('outlets.id')->all());

        $this->saveOutlets($this->owner, [$this->a, $this->b], true)->assertOk();
        $this->assertSame($on->json('assigned_variant_ids'), [$this->variant->id]);
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $this->variant->outlets()->pluck('outlets.id')->all());
        $this->assertSame([$this->a->id], $inactive->outlets()->pluck('outlets.id')->all(), 'the inactive Variant is not assigned');
    }

    public function test_the_option_assigns_active_variants_and_keeps_ids_prices_recipe_and_inactive_variants(): void
    {
        $inactive = $this->makeVariant($this->product, 'Old', 'UBE-OLD', 1000, 1100, [$this->a], false);
        $second = $this->makeVariant($this->product, 'Large', 'UBE-L', 25000, 27000, [$this->a]);
        $before = ProductVariant::orderBy('id')->get(['id', 'name', 'code', 'price', 'price_dine_in', 'price_delivery', 'is_active', 'product_id'])->toArray();

        $this->saveOutlets($this->owner, [$this->a, $this->b], true)->assertOk()
            ->assertJsonPath('message', 'Outlet Product berhasil disimpan. 2 Variant aktif ikut di-assign ke outlet baru. Cek kesiapan jual (Recipe/Ingredient) di Stock & Readiness.');

        $this->assertSame($before, ProductVariant::orderBy('id')->get(['id', 'name', 'code', 'price', 'price_dine_in', 'price_delivery', 'is_active', 'product_id'])->toArray(), 'ids, prices, codes and statuses are untouched');
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $this->variant->outlets()->pluck('outlets.id')->all());
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $second->outlets()->pluck('outlets.id')->all());
        $this->assertSame([$this->a->id], $inactive->outlets()->pluck('outlets.id')->all());
        $this->assertFalse((bool) $inactive->fresh()->is_active, 'an inactive Variant stays inactive');
        $this->assertSame(3, ProductVariant::count());
        $this->assertSame(1, Recipe::count());
        $this->assertSame($this->variant->id, $this->recipe->fresh()->product_variant_id);
        $this->assertSame($this->product->id, $this->recipe->fresh()->product_id);
    }

    public function test_after_the_explicit_assignment_the_same_variant_id_sells_in_both_outlets(): void
    {
        $this->saveOutlets($this->owner, [$this->a, $this->b], true)->assertOk();

        foreach ([$this->a, $this->b] as $outlet) {
            $this->assertTrue($this->saleStatus($this->variant, $outlet)['eligible'], $outlet->name);
            $html = $this->cashierPage($outlet);
            $this->assertSame(1, substr_count($html, '<div class="product-name">Ube Latte</div>'), 'one Product card at '.$outlet->name);
            $this->assertSame(1, substr_count($html, 'data-url="'.e(route('cashier.cart.add', $this->variant)).'"'), 'the shared Variant renders once at '.$outlet->name);
        }

        $this->assertSame(1, Product::count());
        $this->assertSame(1, ProductVariant::count());
    }

    public function test_the_option_never_overrides_recipe_readiness(): void
    {
        $this->milk->outlets()->sync([$this->a->id]);   // Milk is not available at Bravo

        $this->saveOutlets($this->owner, [$this->a, $this->b], true)->assertOk();

        $status = $this->saleStatus($this->variant, $this->b);
        $this->assertFalse($status['eligible']);
        $this->assertSame('ingredient_not_at_outlet', $status['reason']);
    }

    public function test_the_option_without_any_new_outlet_changes_nothing(): void
    {
        $this->saveOutlets($this->owner, [$this->a], true)->assertOk();

        $this->assertSame([$this->a->id], $this->variant->outlets()->pluck('outlets.id')->all());
    }

    public function test_variants_of_other_outlets_are_kept_and_never_removed_by_the_assignment(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->c->id]);
        $this->variant->outlets()->sync([$this->a->id, $this->c->id]);

        $this->saveOutlets($this->owner, [$this->a, $this->b, $this->c], true)->assertOk();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id, $this->c->id], $this->variant->outlets()->pluck('outlets.id')->all());
    }

    public function test_product_losing_an_outlet_still_trims_its_variants_and_the_subset_rule_holds(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);
        $this->variant->outlets()->sync([$this->a->id, $this->b->id]);
        $onlyB = $this->makeVariant($this->product, 'Large', 'UBE-L', 25000, 27000, [$this->b]);

        $this->saveOutlets($this->owner, [$this->a, $this->c], true)->assertOk();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->product->outlets()->pluck('outlets.id')->all());
        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->variant->outlets()->pluck('outlets.id')->all());
        $this->assertFalse((bool) $onlyB->fresh()->is_active, 'a Variant left with no outlet is deactivated, as before');

        $productOutlets = $this->product->outlets()->pluck('outlets.id');
        foreach (ProductVariant::with('outlets:id')->get() as $variant) {
            $this->assertEmpty($variant->outlets->pluck('id')->diff($productOutlets)->all(), 'variant '.$variant->name.' stays inside its Product outlets');
        }
    }

    public function test_variant_losing_an_outlet_keeps_the_product_assignment(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);
        $this->variant->outlets()->sync([$this->a->id, $this->b->id]);

        $this->actingAs($this->owner)->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $this->variant]), [
            'name' => 'Regular', 'price_dine_in' => '20000', 'price_delivery' => '22000', 'outlet_ids' => [$this->a->id], 'is_active' => 1,
        ])->assertOk();

        $this->assertSame([$this->a->id], $this->variant->outlets()->pluck('outlets.id')->all());
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $this->product->outlets()->pluck('outlets.id')->all());
    }

    public function test_classic_product_update_accepts_the_same_option(): void
    {
        $this->actingAs($this->owner)->put(route('backoffice.products.update', $this->product), [
            'brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Ube Latte', 'code' => 'UBE', 'is_active' => 1,
            'outlet_ids' => [$this->a->id, $this->b->id], 'assign_variants' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $this->variant->outlets()->pluck('outlets.id')->all());
        $this->assertSame('Ube Latte', $this->product->fresh()->name);
    }

    // ---- Admin Outlet --------------------------------------------------------------------------------

    public function test_admin_outlet_assignment_only_adds_outlets_it_can_access_and_keeps_the_others(): void
    {
        $limited = $this->makeUser('admin_outlet', [$this->a, $this->b]);
        $this->product->outlets()->sync([$this->a->id, $this->c->id]);
        $this->variant->outlets()->sync([$this->a->id, $this->c->id]);

        $this->saveOutlets($limited, [$this->a, $this->b], true)->assertOk();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id, $this->c->id], $this->product->outlets()->pluck('outlets.id')->all(), 'Charlie, outside its access, is kept');
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id, $this->c->id], $this->variant->outlets()->pluck('outlets.id')->all());
    }

    public function test_admin_outlet_cannot_add_an_inaccessible_outlet_even_with_the_option(): void
    {
        $this->saveOutlets($this->limitedA, [$this->a, $this->c], true)->assertStatus(422);

        $this->assertSame([$this->a->id], $this->product->outlets()->pluck('outlets.id')->all());
        $this->assertSame([$this->a->id], $this->variant->outlets()->pluck('outlets.id')->all());
    }

    public function test_admin_outlet_cannot_open_another_outlets_product(): void
    {
        $other = $this->makeProduct('Other', 'OTHER', [$this->b]);

        $this->actingAs($this->limitedA)->putJson(route('backoffice.products.workspace.outlets', $other), ['outlet_ids' => [$this->a->id], 'assign_variants' => 1])->assertForbidden();
        $this->assertSame([$this->b->id], $other->outlets()->pluck('outlets.id')->all());
    }

    // ================================================================================================
    // Phase 2: CSV imports are assignment-only for rows that already exist
    // ================================================================================================

    public function test_existing_product_import_assigns_the_outlet_and_changes_nothing_else(): void
    {
        $before = $this->product->only(['name', 'brand_id', 'product_category_id', 'description', 'is_active']);
        $csv = "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Totally Different,ube,New description,0\n";

        $response = $this->importProducts($this->owner, $this->b, $csv);

        $response->assertSessionHasNoErrors();
        $this->assertSame($before, $this->product->fresh()->only(['name', 'brand_id', 'product_category_id', 'description', 'is_active']));
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $this->product->outlets()->pluck('outlets.id')->all());
        $this->assertSame(1, Product::count());

        $warnings = collect(session('import_warnings'))->pluck('text')->implode(' | ');
        $this->assertStringContainsString('TIDAK diubah', $warnings);
        $this->assertStringContainsString('nama', $warnings);
        $this->assertStringContainsString('status', $warnings);
        $this->assertStringContainsString('Di-assign ke outlet: 1', session('success'));
    }

    public function test_existing_product_import_warns_that_its_variants_are_not_available_and_links_the_workspace(): void
    {
        $csv = "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Ube Latte,UBE,,1\n";

        $this->importProducts($this->owner, $this->b, $csv);

        $gap = collect(session('import_warnings'))->first(fn ($warning) => str_contains($warning['text'], 'Variant aktif belum tersedia'));
        $this->assertNotNull($gap);
        $this->assertStringContainsString('Regular', $gap['text']);
        $this->assertStringContainsString('Bravo', $gap['text']);
        $this->assertSame(route('backoffice.products.edit', [$this->product->id, 'section' => 'outlets'], false), $gap['url']);
        $this->assertSame([$this->a->id], $this->variant->outlets()->pluck('outlets.id')->all(), 'never assigned automatically');

        $page = $this->get(route('backoffice.products.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-import-warnings', $page);
    }

    public function test_repeating_a_product_import_is_idempotent(): void
    {
        $csv = "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Ube Latte,UBE,,1\n";

        $this->importProducts($this->owner, $this->b, $csv);
        $this->importProducts($this->owner, $this->b, $csv);

        $this->assertStringContainsString('Di-assign ke outlet: 0', session('success'));
        $this->assertStringContainsString('Sudah tersedia (tidak berubah): 1', session('success'));
        $this->assertSame(1, Product::count());
        $this->assertSame(2, $this->product->outlets()->count());
        $this->assertSame(2, \DB::table('product_outlet')->where('product_id', $this->product->id)->count());
    }

    public function test_admin_outlet_import_cannot_rewrite_a_shared_product(): void
    {
        $limitedB = $this->makeUser('admin_outlet', [$this->b]);
        $csv = "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Renamed By Bravo,UBE,,0\n";

        $this->importProducts($limitedB, $this->b, $csv);

        $product = $this->product->fresh();
        $this->assertSame('Ube Latte', $product->name);
        $this->assertTrue((bool) $product->is_active);
        $this->assertContains($this->b->id, $product->outlets()->pluck('outlets.id')->all());

        $this->importProducts($limitedB, $this->a, $csv)->assertSessionHas('error');   // an outlet it cannot use
    }

    public function test_new_product_import_keeps_the_existing_creation_workflow(): void
    {
        $csv = "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Mocha,MOCHA,Choco,1\n";

        $this->importProducts($this->limitedA, $this->a, $csv)->assertSessionHasNoErrors();

        $mocha = Product::where('code', 'MOCHA')->firstOrFail();
        $this->assertSame('Choco', $mocha->description);
        $this->assertSame([$this->a->id], $mocha->outlets()->pluck('outlets.id')->all());
        $this->assertStringContainsString('Baru: 1', session('success'));
    }

    public function test_import_skips_a_look_alike_new_product_and_reports_the_existing_one(): void
    {
        $csv = "brand_name,category_name,name,code,description,is_active\nATG,Kafei,  UBE   latte!,UBE-NEW,,1\n";

        $this->importProducts($this->owner, $this->b, $csv);

        $this->assertDatabaseMissing('products', ['code' => 'UBE-NEW']);
        $error = collect(session('import_errors'))->implode(' ');
        $this->assertStringContainsString('mirip', $error);
        $this->assertStringContainsString('kode UBE', $error);
        $this->assertStringContainsString('Alpha', $error);
        $this->assertStringContainsString('Buat meskipun mirip', $error);
        $this->assertStringContainsString('Dilewati: 1', session('success'));
    }

    public function test_import_can_still_create_a_legitimate_distinct_product_when_confirmed(): void
    {
        $csv = "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Ube Latte,UBE-NEW,,1\n";

        $this->importProducts($this->owner, $this->b, $csv, ['allow_similar' => 1]);

        $created = Product::where('code', 'UBE-NEW')->firstOrFail();
        $this->assertNotSame($this->product->id, $created->id);
        $this->assertSame([$this->b->id], $created->outlets()->pluck('outlets.id')->all());
        $this->assertSame(2, Product::count());
        $this->assertSame('UBE', $this->product->fresh()->code, 'the existing Product is untouched');
    }

    public function test_import_validation_failures_do_not_write_anything(): void
    {
        $csv = "brand_name,category_name,name,code,description,is_active\nNoBrand,Kafei,X,X1,,1\nATG,NoCat,Y,Y1,,1\nATG,Kafei,,Z1,,1\n";

        $this->importProducts($this->owner, $this->b, $csv);

        $this->assertSame(1, Product::count());
        $this->assertCount(3, session('import_errors'));
        $this->assertStringContainsString('Dilewati: 3', session('success'));

        $this->actingAs($this->owner)->post(route('backoffice.products.import.store'), ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])->assertSessionHasErrors('outlet_id');
        $this->actingAs($this->owner)->post(route('backoffice.products.import.store'), ['outlet_id' => $this->b->id, 'file' => UploadedFile::fake()->createWithContent('p.csv', "wrong,header\n1,2\n")])->assertSessionHas('error');
    }

    public function test_a_failure_in_the_middle_of_a_product_import_rolls_the_whole_file_back(): void
    {
        Product::creating(function (Product $product) {
            if ($product->code === 'BOOM') {
                throw new \RuntimeException('boom');
            }
        });
        $csv = "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Fine,FINE,,1\nATG,Kafei,Explodes,BOOM,,1\n";

        $this->importProducts($this->owner, $this->b, $csv)->assertStatus(500);

        $this->assertDatabaseMissing('products', ['code' => 'FINE']);
        $this->assertSame(1, Product::count());
    }

    // ---- Variant import ------------------------------------------------------------------------------

    public function test_existing_variant_import_assigns_the_outlet_and_keeps_the_global_prices(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);

        $this->importVariants($this->owner, "product_code,outlet_code,name,code,price_dine_in,price_delivery,is_active\nUBE,OB,Regular,UBE-R,20000,22000,1\n")->assertSessionHasNoErrors();

        $variant = $this->variant->fresh();
        $this->assertSame(20000.0, (float) $variant->price_dine_in);
        $this->assertSame(22000.0, (float) $variant->price_delivery);
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $variant->outlets()->pluck('outlets.id')->all());
        $this->assertSame(1, ProductVariant::count());
        $this->assertStringContainsString('Di-assign ke outlet: 1', session('success'));
        $this->assertSame([], session('import_errors'));
    }

    public function test_a_conflicting_variant_price_rejects_the_row_and_changes_nothing(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);

        $this->importVariants($this->owner, "product_code,outlet_code,name,code,price_dine_in,price_delivery,is_active\nUBE,OB,Regular,UBE-R,15000,22000,1\n");

        $variant = $this->variant->fresh();
        $this->assertSame(20000.0, (float) $variant->price_dine_in, 'Alpha keeps charging what it charged');
        $this->assertSame([$this->a->id], $variant->outlets()->pluck('outlets.id')->all(), 'a rejected row assigns nothing');
        $this->assertSame(1, ProductVariant::count(), 'no clone as a workaround');
        $error = collect(session('import_errors'))->implode(' ');
        $this->assertStringContainsString('harga global', $error);
        $this->assertStringContainsString('dipakai bersama semua outlet', $error);
        $this->assertStringContainsString('Dilewati: 1', session('success'));
    }

    public function test_existing_variant_import_does_not_change_name_or_status_and_reports_the_difference(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);

        $this->importVariants($this->owner, "product_code,outlet_code,name,code,price_dine_in,price_delivery,is_active\nUBE,OB,Renamed,UBE-R,20000,22000,0\n");

        $variant = $this->variant->fresh();
        $this->assertSame('Regular', $variant->name);
        $this->assertTrue((bool) $variant->is_active);
        $warning = collect(session('import_warnings'))->pluck('text')->implode(' ');
        $this->assertStringContainsString('TIDAK diubah', $warning);
        $this->assertStringContainsString('nama', $warning);
        $this->assertStringContainsString('status', $warning);
    }

    public function test_repeating_a_variant_import_is_idempotent(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);
        $csv = "product_code,outlet_code,name,code,price_dine_in,price_delivery,is_active\nUBE,OB,Regular,UBE-R,20000,22000,1\nUBE,OB,Large,UBE-L,25000,27000,1\n";

        $this->importVariants($this->owner, $csv);
        $this->assertStringContainsString('Baru: 1', session('success'));
        $this->importVariants($this->owner, $csv);

        $this->assertStringContainsString('Baru: 0', session('success'));
        $this->assertStringContainsString('Sudah tersedia (tidak berubah): 2', session('success'));
        $this->assertSame(2, ProductVariant::count());
        $this->assertSame(2, \DB::table('product_variant_outlet')->where('outlet_id', $this->b->id)->count());
        $this->assertSame(1, \DB::table('product_variant_outlet')->where('product_variant_id', $this->variant->id)->where('outlet_id', $this->b->id)->count());
    }

    public function test_new_variant_import_keeps_the_creation_workflow_and_its_validation(): void
    {
        $csv = "product_code,outlet_code,name,code,price_dine_in,price_delivery,is_active\nUBE,OA,Large,ube-l,25000,27000,1\nUBE,OA,Bad,UBE-X,abc,1,1\nUBE,OC,Nope,UBE-Y,1,1,1\nGHOST,OA,Nope,G,1,1,1\n";

        $this->importVariants($this->limitedA, $csv);

        $large = ProductVariant::where('code', 'UBE-L')->firstOrFail();
        $this->assertSame(25000.0, (float) $large->price_dine_in);
        $this->assertSame([$this->a->id], $large->outlets()->pluck('outlets.id')->all());
        $this->assertNull($large->outlet_id);
        $this->assertCount(3, session('import_errors'));
        $this->assertStringContainsString('Baru: 1', session('success'));
    }

    public function test_variant_import_cannot_use_an_outlet_the_user_cannot_access_or_the_product_lacks(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);

        $this->importVariants($this->limitedA, "product_code,outlet_code,name,code,price_dine_in,price_delivery,is_active\nUBE,OB,Regular,UBE-R,20000,22000,1\n");

        $this->assertSame([$this->a->id], $this->variant->outlets()->pluck('outlets.id')->all());
        $this->assertStringContainsString('tidak aktif atau tidak tersedia untuk akun ini', collect(session('import_errors'))->implode(' '));

        $this->importVariants($this->owner, "product_code,outlet_code,name,code,price_dine_in,price_delivery,is_active\nUBE,OC,Regular,UBE-R,20000,22000,1\n");
        $this->assertStringContainsString('belum tersedia pada Product', collect(session('import_errors'))->implode(' '));
        $this->assertSame([$this->a->id], $this->variant->outlets()->pluck('outlets.id')->all());
    }

    public function test_a_failure_in_the_middle_of_a_variant_import_rolls_the_whole_file_back(): void
    {
        ProductVariant::creating(function (ProductVariant $variant) {
            if ($variant->code === 'BOOM') {
                throw new \RuntimeException('boom');
            }
        });
        $csv = "product_code,outlet_code,name,code,price_dine_in,price_delivery,is_active\nUBE,OA,Fine,FINE,1,1,1\nUBE,OA,Explodes,BOOM,1,1,1\n";

        $this->importVariants($this->owner, $csv)->assertStatus(500);

        $this->assertDatabaseMissing('product_variants', ['code' => 'FINE']);
        $this->assertSame(1, ProductVariant::count());
    }

    // ================================================================================================
    // Phase 3: look-alike Products warn, never block or merge
    // ================================================================================================

    public function test_normalized_names_match_regardless_of_case_spacing_and_punctuation(): void
    {
        $this->assertSame('thai tea', ProductDuplicateGuard::normalize('  Thai   TEA! '));
        $this->assertSame('', ProductDuplicateGuard::normalize(' -- '));
        $this->assertCount(1, app(ProductDuplicateGuard::class)->similar('ube  LATTE', $this->brand->id, $this->category->id));
        $this->assertCount(0, app(ProductDuplicateGuard::class)->similar('Ube Latte', $this->brand->id, $this->category->id, $this->product->id), 'a Product is not similar to itself');
        $this->assertCount(0, app(ProductDuplicateGuard::class)->similar('Ube Latte Large', $this->brand->id, $this->category->id));
    }

    public function test_creating_a_look_alike_product_warns_first_and_writes_nothing(): void
    {
        $response = $this->actingAs($this->owner)->post(route('backoffice.products.store'), $this->productPayload(['code' => 'UBE-2']));

        $response->assertRedirect()->assertSessionHas('similar_products');
        $this->assertDatabaseMissing('products', ['code' => 'UBE-2']);
        $similar = session('similar_products')[0];
        $this->assertSame('UBE', $similar['code']);
        $this->assertSame(['Alpha'], $similar['outlets']);
        $this->assertSame($this->product->id, $similar['id']);
        $this->assertNotNull($similar['url']);
    }

    public function test_the_warning_page_shows_identity_code_outlets_and_a_confirm_box(): void
    {
        $page = $this->followingRedirects()->actingAs($this->owner)
            ->from(route('backoffice.products.create'))
            ->post(route('backoffice.products.store'), $this->productPayload(['code' => 'UBE-2']))
            ->getContent();

        $this->assertStringContainsString('data-similar-product="'.$this->product->id.'"', $page);
        $this->assertStringContainsString('kode UBE', $page);
        $this->assertStringContainsString('Alpha', $page);
        $this->assertStringContainsString('name="confirm_similar"', $page);
        $this->assertDatabaseMissing('products', ['code' => 'UBE-2']);
    }

    public function test_a_confirmed_look_alike_product_is_created_without_touching_the_existing_one(): void
    {
        $this->actingAs($this->owner)->post(route('backoffice.products.store'), $this->productPayload(['code' => 'UBE-2', 'confirm_similar' => 1]))->assertSessionHasNoErrors();

        $this->assertSame(2, Product::where('name', 'Ube Latte')->count());
        $this->assertSame('UBE', $this->product->fresh()->code);
        $this->assertSame([$this->a->id], $this->product->outlets()->pluck('outlets.id')->all());
    }

    public function test_the_same_name_in_another_brand_or_category_is_not_a_look_alike(): void
    {
        $other = Brand::create(['name' => 'Other', 'code' => 'OTH', 'is_active' => true]);
        $otherCategory = ProductCategory::create(['brand_id' => $other->id, 'name' => 'Snack', 'code' => 'SNACK', 'is_active' => true]);

        $this->actingAs($this->owner)->post(route('backoffice.products.store'), $this->productPayload(['code' => 'UBE-B', 'brand_id' => $other->id, 'product_category_id' => $otherCategory->id]))->assertSessionDoesntHaveErrors();
        $this->actingAs($this->owner)->post(route('backoffice.products.store'), $this->productPayload(['code' => 'UBE-C', 'product_category_id' => ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Cold', 'code' => 'COLD', 'is_active' => true])->id]))->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('products', ['code' => 'UBE-B']);
        $this->assertDatabaseHas('products', ['code' => 'UBE-C']);
    }

    public function test_the_guard_never_merges_or_deletes_existing_look_alikes(): void
    {
        $twin = $this->makeProduct('Ube Latte', 'UBE-TWIN', [$this->b]);
        $ids = Product::orderBy('id')->pluck('id')->all();

        app(ProductDuplicateGuard::class)->similar('Ube Latte', $this->brand->id, $this->category->id);

        $this->assertSame($ids, Product::orderBy('id')->pluck('id')->all());
        $this->assertSame([$this->b->id], $twin->outlets()->pluck('outlets.id')->all());
    }

    // ================================================================================================
    // Phase 4: legacy variants.outlet_id
    // ================================================================================================

    public function test_both_editors_preserve_the_stored_legacy_outlet_id(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);
        $this->variant->outlets()->sync([$this->a->id, $this->b->id]);
        $this->variant->forceFill(['outlet_id' => $this->a->id])->save();

        $this->actingAs($this->owner)->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $this->variant]), [
            'name' => 'Regular', 'price_dine_in' => '20000', 'price_delivery' => '22000', 'outlet_ids' => [$this->a->id, $this->b->id], 'is_active' => 1,
        ])->assertOk();
        $this->assertSame($this->a->id, $this->variant->fresh()->outlet_id, 'Workspace editor');

        $this->actingAs($this->owner)->put(route('backoffice.variants.update', $this->variant), [
            'product_id' => $this->product->id,
            'variants' => [['id' => $this->variant->id, 'name' => 'Regular', 'price_dine_in' => 20000, 'price_delivery' => 22000, 'outlet_ids' => [$this->a->id, $this->b->id], 'is_active' => 1]],
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->a->id, $this->variant->fresh()->outlet_id, 'classic editor');
    }

    public function test_a_forged_legacy_outlet_id_cannot_change_assignment_or_bypass_the_scope(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);
        $this->variant->outlets()->sync([$this->a->id]);

        // Charlie is neither an outlet of the Product nor of the limited user: it must have no effect at all.
        $this->actingAs($this->limitedA)->put(route('backoffice.variants.update', $this->variant), [
            'product_id' => $this->product->id,
            'variants' => [['id' => $this->variant->id, 'outlet_id' => $this->c->id, 'name' => 'Regular', 'price_dine_in' => 20000, 'price_delivery' => 22000, 'outlet_ids' => [$this->a->id], 'is_active' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$this->a->id], $this->variant->outlets()->pluck('outlets.id')->all(), 'assignment comes from outlet_ids only');
        $this->assertNull($this->variant->fresh()->outlet_id, 'the forged value was not stored');

        // And it can not smuggle an assignment in when outlet_ids is empty.
        $this->actingAs($this->limitedA)->put(route('backoffice.variants.update', $this->variant), [
            'product_id' => $this->product->id,
            'variants' => [['id' => $this->variant->id, 'outlet_id' => $this->a->id, 'name' => 'Regular', 'price_dine_in' => 20000, 'price_delivery' => 22000, 'outlet_ids' => [], 'is_active' => 1]],
        ])->assertSessionHasErrors();
        $this->assertSame([$this->a->id], $this->variant->outlets()->pluck('outlets.id')->all());
    }

    public function test_a_new_variant_ignores_a_forged_legacy_outlet_id(): void
    {
        $this->actingAs($this->owner)->post(route('backoffice.variants.store'), [
            'product_id' => $this->product->id,
            'variants' => [['outlet_id' => $this->b->id, 'name' => 'Large', 'price_dine_in' => 25000, 'price_delivery' => 27000, 'outlet_ids' => [$this->a->id], 'is_active' => 1]],
        ])->assertSessionHasNoErrors();

        $large = ProductVariant::where('name', 'Large')->firstOrFail();
        $this->assertNull($large->outlet_id);
        $this->assertSame([$this->a->id], $large->outlets()->pluck('outlets.id')->all());
    }

    public function test_a_conflicting_legacy_outlet_id_never_decides_cashier_eligibility(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);
        $this->variant->forceFill(['outlet_id' => $this->b->id])->save();   // legacy says Bravo, the pivot says Alpha only

        $this->assertTrue($this->saleStatus($this->variant, $this->a)['eligible']);
        $this->assertSame('variant_not_at_outlet', $this->saleStatus($this->variant, $this->b)['reason']);
        $this->assertSame(0, ProductVariant::availableAtOutlet($this->b->id)->count());
    }

    // ================================================================================================
    // Phase 5: Cashier and promo quick-add
    // ================================================================================================

    public function test_the_cashier_lists_only_what_is_assigned_to_its_outlet(): void
    {
        $this->assertStringContainsString('Ube Latte', $this->cashierPage($this->a));
        $this->assertStringNotContainsString('Ube Latte', $this->cashierPage($this->b), 'not assigned to Bravo yet');

        $this->product->outlets()->sync([$this->a->id, $this->b->id]);   // Product only: its Variant is still Alpha only
        $this->assertStringNotContainsString('Ube Latte', $this->cashierPage($this->b), 'an unassigned Variant is excluded');

        $this->variant->outlets()->sync([$this->a->id, $this->b->id]);
        $this->assertStringContainsString('Ube Latte', $this->cashierPage($this->b));
    }

    public function test_add_to_cart_follows_the_assignment(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);

        $this->addToCart($this->b)->assertStatus(422)->assertJsonPath('reason', 'variant_not_at_outlet');

        $this->variant->outlets()->sync([$this->a->id, $this->b->id]);
        $this->addToCart($this->b)->assertOk()->assertJsonPath('success', true);
        $this->addToCart($this->a)->assertOk()->assertJsonPath('success', true);
    }

    public function test_checkout_revalidates_and_then_history_is_independent_of_later_assignments(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);
        $this->variant->outlets()->sync([$this->a->id, $this->b->id]);

        // Bravo's variant assignment disappears while the cart is filled: checkout refuses, nothing is written.
        $this->variant->outlets()->sync([$this->a->id]);
        $this->checkout($this->b)->assertRedirect(route('cashier.index'))->assertSessionHas('error');
        $this->assertSame(0, SalesTransaction::count());

        $this->variant->outlets()->sync([$this->a->id, $this->b->id]);
        $this->checkout($this->b)->assertRedirect(route('cashier.index'))->assertSessionHas('last_checkout');
        $this->assertSame(1, SalesTransaction::count());
        $item = SalesTransactionItem::firstOrFail();
        $snapshot = $item->only(['product_id', 'product_variant_id', 'product_name', 'variant_name', 'qty', 'price', 'line_total']);

        // Later changes to the assignments never rewrite the sale.
        $this->saveOutlets($this->owner, [$this->a])->assertOk();
        $this->assertSame([$this->a->id], $this->variant->outlets()->pluck('outlets.id')->all());
        $this->assertSame(1, SalesTransaction::count());
        $this->assertSame($snapshot, $item->fresh()->only(['product_id', 'product_variant_id', 'product_name', 'variant_name', 'qty', 'price', 'line_total']));
        $this->assertSame($this->b->id, SalesTransaction::first()->outlet_id);
    }

    public function test_promo_quick_add_adds_eligible_items(): void
    {
        $promo = $this->promo($this->variant, [$this->a]);

        $this->postPromo($promo, $this->a)->assertOk()->assertJsonPath('success', true);
        $this->assertTrue($this->saleStatus($this->variant, $this->a)['eligible']);
    }

    public function test_promo_quick_add_refuses_an_ineligible_variant_and_leaves_the_cart_untouched(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);
        $promo = $this->promo($this->variant, [$this->a, $this->b]);   // the promo reaches Bravo, the Variant does not

        $session = ['cashier_cart' => []];
        $response = $this->postPromo($promo, $this->b, $session);

        $response->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('reason', 'variant_not_at_outlet');
        $this->assertStringContainsString('Variant tidak tersedia di Bravo', $response->json('cashier_message'));
        $this->assertEmpty(session('cashier_cart', []), 'nothing from the promo reached the cart');
        $this->assertNull(session('cashier_quick_promo_id'));
    }

    public function test_promo_quick_add_applies_the_recipe_rules_too(): void
    {
        $promo = $this->promo($this->variant, [$this->a]);
        $this->recipe->update(['is_active' => false]);

        $this->postPromo($promo, $this->a)->assertStatus(422)->assertJsonPath('reason', 'recipe_inactive');
    }

    public function test_promo_quick_add_non_json_keeps_the_redirect_and_flashes_the_error(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);
        $promo = $this->promo($this->variant, [$this->a, $this->b]);

        $this->actingAs($this->cashier)->withSession($this->cashierSession($this->b))
            ->post(route('cashier.promo.apply', $promo))
            ->assertRedirect(route('cashier.index'))->assertSessionHas('error');
    }

    // ================================================================================================
    // Phase 6: integrity
    // ================================================================================================

    public function test_assignments_never_touch_recipes_promos_or_ids(): void
    {
        $promo = $this->promo($this->variant, [$this->a]);
        $recipeBefore = Recipe::first()->only(['id', 'product_id', 'product_variant_id', 'name', 'is_active']);
        $itemsBefore = RecipeItem::orderBy('id')->get(['recipe_id', 'ingredient_id', 'qty'])->toArray();

        $this->saveOutlets($this->owner, [$this->a, $this->b], true)->assertOk();
        $this->saveOutlets($this->owner, [$this->a], true)->assertOk();

        $this->assertSame($recipeBefore, Recipe::first()->only(['id', 'product_id', 'product_variant_id', 'name', 'is_active']));
        $this->assertSame($itemsBefore, RecipeItem::orderBy('id')->get(['recipe_id', 'ingredient_id', 'qty'])->toArray());
        $this->assertSame([$this->variant->id], $promo->fresh()->requirements->pluck('product_variant_id')->all());
        $this->assertSame([$this->a->id], $promo->fresh()->outlets->pluck('id')->all(), 'Promo outlets are never modified');
        $this->assertSame($this->product->id, $this->variant->fresh()->product_id);
        $this->assertSame(1, Product::count());
        $this->assertSame(1, ProductVariant::count());
    }

    // ================================================================================================
    // helpers
    // ================================================================================================

    private function saveOutlets(User $user, array $outlets, bool $assignVariants = false)
    {
        return $this->actingAs($user)->putJson(route('backoffice.products.workspace.outlets', $this->product), array_filter([
            'outlet_ids' => collect($outlets)->pluck('id')->all(),
            'assign_variants' => $assignVariants ? 1 : null,
        ]));
    }

    private function importProducts(User $user, Outlet $outlet, string $csv, array $extra = [])
    {
        return $this->actingAs($user)->post(route('backoffice.products.import.store'), $extra + ['outlet_id' => $outlet->id, 'file' => UploadedFile::fake()->createWithContent('products.csv', $csv)]);
    }

    private function importVariants(User $user, string $csv)
    {
        return $this->actingAs($user)->post(route('backoffice.variants.import.store'), ['file' => UploadedFile::fake()->createWithContent('variants.csv', $csv)]);
    }

    private function productPayload(array $overrides = []): array
    {
        return $overrides + [
            'brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Ube Latte', 'code' => 'UBE-X',
            'is_active' => 1, 'outlet_ids' => [$this->a->id],
        ];
    }

    private function saleStatus(ProductVariant $variant, Outlet $outlet): array
    {
        return app(SaleEligibilityService::class)->variantStatuses([$variant->id], $outlet->id)[$variant->id];
    }

    private function cashierSession(Outlet $outlet): array
    {
        return ['auth_portal' => 'cashier', 'cashier_outlet_id' => $outlet->id, 'cashier_order_type' => 'dine_in'];
    }

    private function cashierPage(Outlet $outlet): string
    {
        return $this->actingAs($this->cashier)->withSession($this->cashierSession($outlet))->get(route('cashier.index'))->assertOk()->getContent();
    }

    private function addToCart(Outlet $outlet)
    {
        return $this->actingAs($this->cashier)->withSession($this->cashierSession($outlet) + ['cashier_cart' => []])
            ->postJson(route('cashier.cart.add', $this->variant), ['order_type' => 'dine_in']);
    }

    private function checkout(Outlet $outlet)
    {
        $key = 'variant_'.$this->variant->id.'_dine_in';

        return $this->actingAs($this->cashier)->withSession($this->cashierSession($outlet) + ['cashier_cart' => [$key => [
            'cart_key' => $key, 'variant_id' => $this->variant->id, 'product_id' => $this->product->id, 'product_name' => $this->product->name,
            'variant_name' => $this->variant->name, 'order_type' => 'dine_in', 'qty' => 1, 'price' => 20000, 'line_total' => 20000,
        ]]])->post(route('cashier.checkout'), ['payment_method' => 'cash', 'amount_paid' => 20000, 'order_type' => 'dine_in']);
    }

    private function postPromo(Promo $promo, Outlet $outlet, array $session = [])
    {
        return $this->actingAs($this->cashier)->withSession($this->cashierSession($outlet) + $session)
            ->postJson(route('cashier.promo.apply', $promo), ['order_type' => 'dine_in']);
    }

    private function promo(ProductVariant $variant, array $outlets): Promo
    {
        $promo = Promo::create(['name' => 'Quick', 'requirement_logic' => 'and', 'status' => 'active', 'is_active' => true, 'active_days' => []]);
        PromoRequirement::create(['promo_id' => $promo->id, 'product_variant_id' => $variant->id, 'qty' => 1]);
        $promo->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $promo;
    }

    private function makeProduct(string $name, string $code, array $outlets): Product
    {
        $product = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => $name, 'code' => $code, 'is_active' => true]);
        $product->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $product;
    }

    private function makeVariant(Product $product, string $name, string $code, float $dineIn, float $delivery, array $outlets, bool $active = true): ProductVariant
    {
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => $name, 'code' => $code, 'price' => $dineIn, 'price_dine_in' => $dineIn, 'price_delivery' => $delivery, 'is_active' => $active]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    private function makeUser(string $roleCode, array $outlets): User
    {
        $user = User::create([
            'name' => $roleCode, 'username' => $roleCode.'-'.uniqid(), 'email' => $roleCode.uniqid().'@example.test', 'password' => 'password',
            'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id, 'outlet_id' => $outlets[0]->id, 'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }
}
