<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Back Office has no outlet selector: every page works on "all outlets the user may access".
 * That is a view default only. What a user may read or change still comes from their role and outlet
 * assignments, so a limited user is never widened, by a leftover session value or by request parameters.
 */
class BackofficeOutletScopeTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $a;

    private Outlet $b;

    private Brand $brand;

    private ProductCategory $category;

    private IngredientCategory $ingredientCategory;

    private Product $atA;

    private Product $atB;

    private User $owner;

    private User $adminPusat;

    private User $limited;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Outlet::create(['name' => 'Alpha', 'code' => 'A', 'is_active' => true]);
        $this->b = Outlet::create(['name' => 'Bravo', 'code' => 'B', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->ingredientCategory = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);

        $this->atA = $this->product('Only At Alpha', 'ONLY-A', [$this->a]);
        $this->atB = $this->product('Only At Bravo', 'ONLY-B', [$this->b]);

        $this->owner = $this->user('owner', [$this->a, $this->b]);
        $this->adminPusat = $this->user('admin_pusat', [$this->a]);
        $this->limited = $this->user('admin_outlet', [$this->a]);
    }

    // ---- no selector, pages still work ------------------------------------------------------------

    public function test_full_access_roles_see_no_selector_and_every_core_page_loads_for_all_outlets(): void
    {
        foreach ([$this->owner, $this->adminPusat] as $user) {
            $this->actingAs($user);

            foreach ([
                route('backoffice.index'),
                route('backoffice.products.index'),
                route('backoffice.products.edit', $this->atB),
                route('backoffice.products.edit', [$this->atA, 'section' => 'stock']),
                route('backoffice.products.edit', [$this->atA, 'section' => 'promo']),
                route('backoffice.variants.index'),
                route('backoffice.recipes.index'),
                route('backoffice.ingredients.index'),
                route('backoffice.stock-balances.index'),
                route('backoffice.stock-movements.index'),
                route('backoffice.stock-adjustments.index'),
                route('backoffice.purchase-history.index'),
                route('backoffice.promos.index'),
                route('backoffice.transfers.index'),
                route('backoffice.transactions.index'),
            ] as $url) {
                $html = $this->get($url)->assertOk()->getContent();

                $this->assertStringNotContainsString('id="active-backoffice-outlet"', $html, $url);
                $this->assertStringNotContainsString('backoffice-context-bar', $html, $url);
                $this->assertStringNotContainsString('name="outlet_id" onchange="this.form.submit()"', $html, $url);
            }

            $products = $this->get(route('backoffice.products.index'))->viewData('products');
            $this->assertEqualsCanonicalizing([$this->atA->id, $this->atB->id], $products->pluck('id')->all(), 'all outlets');
        }
    }

    public function test_the_old_selector_endpoint_is_gone(): void
    {
        $this->actingAs($this->owner)->post('/backoffice/active-outlet', ['outlet_id' => $this->a->id])->assertNotFound();
    }

    // ---- limited user: only what they may access ---------------------------------------------------

    public function test_the_product_and_variant_catalogs_stay_globally_readable_for_a_limited_user(): void
    {
        $variantA = $this->variant($this->atA, 'Alpha Variant', 'ONLY-A-V', [$this->a]);
        $variantB = $this->variant($this->atB, 'Bravo Variant', 'ONLY-B-V', [$this->b]);
        $this->actingAs($this->limited);

        // removing the outlet selector did not narrow the master catalogs, whatever the session or query says
        foreach ([[], ['outlet_id' => $this->a->id]] as $query) {
            $products = $this->get(route('backoffice.products.index', $query))->assertOk()->viewData('products');
            $this->assertEqualsCanonicalizing([$this->atA->id, $this->atB->id], $products->pluck('id')->all(), json_encode($query));

            $variants = $this->get(route('backoffice.variants.index', $query))->assertOk()->viewData('variants');
            $this->assertEqualsCanonicalizing([$variantA->id, $variantB->id], $variants->pluck('id')->all(), json_encode($query));
        }

        foreach (['products.export.csv', 'variants.export.csv'] as $export) {
            $csv = $this->get(route('backoffice.'.$export))->streamedContent();
            $this->assertStringContainsString('ONLY-A', $csv);
            $this->assertStringContainsString('ONLY-B', $csv);
        }

        $this->assertEqualsCanonicalizing([$this->atA->id, $this->atB->id], $this->get(route('backoffice.variants.create'))->viewData('products')->pluck('id')->all());

        // Ingredients: global list, rows outside the user's outlets are "Hanya lihat"
        $mine = $this->ingredient('Mine', [$this->a]);
        $theirs = $this->ingredient('Theirs', [$this->b]);
        $html = $this->get(route('backoffice.ingredients.index'))->assertOk()->getContent();
        $this->assertStringContainsString('ingredients/'.$mine->id.'/edit', $html);
        $this->assertStringNotContainsString('ingredients/'.$theirs->id.'/edit', $html);
        $this->assertStringContainsString('Hanya lihat', $html);
    }

    public function test_a_limited_user_still_cannot_mutate_another_outlets_product_or_variant_whatever_the_session_or_query_says(): void
    {
        $variantB = $this->variant($this->atB, 'Bravo Variant', 'ONLY-B-V', [$this->b]);
        $before = $this->fingerprint();
        $forged = ['outlet_id' => $this->b->id, 'location_id' => $this->b->id];

        // a leftover / forged "active outlet" grants nothing
        $this->actingAs($this->limited)->withSession(['active_backoffice_outlet_id' => $this->b->id]);

        $payload = ['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Hijacked', 'code' => 'ONLY-B', 'is_active' => 0, 'outlet_ids' => [$this->b->id]];
        $variantPayload = ['name' => 'Hijacked', 'price_dine_in' => '1', 'price_delivery' => '1', 'outlet_ids' => [$this->b->id], 'is_active' => 0];

        // Product: open, update, deactivate, workspace saves
        $this->get(route('backoffice.products.edit', [$this->atB, ...$forged]))->assertForbidden();
        $this->put(route('backoffice.products.update', [$this->atB, ...$forged]), $payload)->assertForbidden();
        $this->delete(route('backoffice.products.destroy', [$this->atB, ...$forged]))->assertForbidden();
        $this->put(route('backoffice.products.workspace.general', [$this->atB, ...$forged]), $payload)->assertForbidden();
        $this->putJson(route('backoffice.products.workspace.outlets', [$this->atB, ...$forged]), ['outlet_ids' => [$this->a->id]])->assertForbidden();

        // Variant: classic group editor and workspace
        $this->get(route('backoffice.variants.edit', [$variantB, ...$forged]))->assertForbidden();
        $this->put(route('backoffice.variants.update', $variantB), ['product_id' => $this->atB->id, 'variants' => [['id' => $variantB->id] + $variantPayload]])->assertForbidden();
        $this->delete(route('backoffice.variants.destroy', $variantB))->assertForbidden();
        $this->postJson(route('backoffice.products.workspace.variants.store', $this->atB), $variantPayload)->assertForbidden();
        $this->putJson(route('backoffice.products.workspace.variants.update', [$this->atB, $variantB]), $variantPayload)->assertForbidden();
        $this->patchJson(route('backoffice.products.workspace.variants.deactivate', [$this->atB, $variantB]))->assertForbidden();

        $this->assertSame($before, $this->fingerprint(), 'nothing changed');
        $this->assertTrue($this->atB->fresh()->is_active);
        $this->assertSame('Only At Bravo', $this->atB->fresh()->name);
        $this->assertTrue($variantB->fresh()->is_active);
    }

    public function test_a_limited_user_cannot_mutate_another_outlet_through_the_stock_pages(): void
    {
        $milk = $this->ingredient('Milk', [$this->a, $this->b]);
        StockBalance::create(['ingredient_id' => $milk->id, 'location_type' => 'outlet', 'location_id' => $this->b->id, 'qty_on_hand' => 100]);
        $this->actingAs($this->limited);

        $this->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'outlet', 'location_id' => $this->b->id, 'note' => 'sneaky',
            'items' => [['ingredient_id' => $milk->id, 'actual_qty' => 1]],
        ])->assertSessionHasErrors('location_id');

        $this->post(route('backoffice.stock-balances.store'), [
            'location_type' => 'outlet', 'location_id' => $this->b->id, 'received_date' => '2026-01-01',
            'items' => [['ingredient_id' => $milk->id, 'qty_in' => 5, 'unit_price' => 1]],
        ])->assertSessionHasErrors('location_id');

        $this->assertSame(0, StockAdjustment::count());
        $this->assertEquals(100, StockBalance::where('location_id', $this->b->id)->value('qty_on_hand'));
    }

    // ---- imports name their own target outlet ----------------------------------------------------------

    public function test_product_import_needs_an_allowed_target_outlet_and_only_attaches_that_outlet(): void
    {
        $csv = "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Imported Latte,IMP-LATTE,,1\n";
        $post = fn (array $extra = [], ?string $content = null) => $this->post(route('backoffice.products.import.store'), $extra + ['file' => UploadedFile::fake()->createWithContent('products.csv', $content ?? $csv)]);

        // no target outlet: refused, nothing written (an import must never create global availability)
        $this->actingAs($this->limited);
        $post()->assertSessionHasErrors('outlet_id');
        $this->assertDatabaseMissing('products', ['code' => 'IMP-LATTE']);

        // an outlet the user cannot access: refused, nothing written
        $post(['outlet_id' => $this->b->id])->assertSessionHas('error');
        $this->assertDatabaseMissing('products', ['code' => 'IMP-LATTE']);

        // their own outlet: imported and available only there
        $post(['outlet_id' => $this->a->id])->assertSessionHasNoErrors();
        $this->assertSame([$this->a->id], Product::where('code', 'IMP-LATTE')->firstOrFail()->outlets()->pluck('outlets.id')->all());

        // the owner can pick any outlet
        $this->actingAs($this->owner);
        $mocha = "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Imported Mocha,IMP-MOCHA,,1\n";
        $post(['outlet_id' => $this->b->id], $mocha)->assertSessionHasNoErrors();
        $this->assertSame([$this->b->id], Product::where('code', 'IMP-MOCHA')->firstOrFail()->outlets()->pluck('outlets.id')->all());
    }

    public function test_ingredient_import_needs_an_allowed_target_outlet_and_only_attaches_that_outlet(): void
    {
        $csv = "name,category_name,unit,ingredient_type,minimum_stock,cost_per_unit,is_active\nImported Cream,Dairy,ml,raw,10,100,1\n";
        $post = fn (array $extra = []) => $this->post(route('backoffice.ingredients.import.store'), $extra + ['file' => UploadedFile::fake()->createWithContent('ingredients.csv', $csv)]);

        $this->actingAs($this->limited);
        $post()->assertSessionHasErrors('outlet_id');
        $post(['outlet_id' => $this->b->id])->assertSessionHas('error');
        $this->assertDatabaseMissing('ingredients', ['name' => 'Imported Cream']);

        $post(['outlet_id' => $this->a->id])->assertSessionHasNoErrors();
        $this->assertSame([$this->a->id], Ingredient::where('name', 'Imported Cream')->firstOrFail()->outlets()->pluck('outlets.id')->all());
    }

    public function test_the_import_forms_offer_only_the_outlets_the_user_may_use(): void
    {
        foreach ([route('backoffice.products.import'), route('backoffice.ingredients.import')] as $url) {
            $limited = $this->actingAs($this->limited)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('name="outlet_id"', $limited);
            $this->assertStringContainsString('>Alpha</option>', $limited);
            $this->assertStringNotContainsString('>Bravo</option>', $limited);

            $owner = $this->actingAs($this->owner)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('>Alpha</option>', $owner);
            $this->assertStringContainsString('>Bravo</option>', $owner);
        }
    }

    // ---- read-only guarantee ------------------------------------------------------------------------------

    public function test_opening_the_pages_and_ignoring_a_leftover_selection_writes_nothing(): void
    {
        $before = $this->fingerprint();

        foreach ([$this->owner, $this->limited] as $user) {
            $this->actingAs($user)->withSession(['active_backoffice_outlet_id' => $this->b->id]);
            $this->get(route('backoffice.products.index'))->assertOk();
            $this->get(route('backoffice.recipes.index'))->assertOk();
            $this->get(route('backoffice.stock-balances.index'))->assertOk();
        }

        $this->assertSame($before, $this->fingerprint());
    }

    // ---- helpers -----------------------------------------------------------------------------------------------

    private function fingerprint(): array
    {
        return json_decode(json_encode(collect(['products', 'product_variants', 'ingredients', 'recipes', 'recipe_items', 'stock_balances', 'stock_movements', 'sales_transactions', 'promos'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()])->all()), true);
    }

    private function product(string $name, string $code, array $outlets): Product
    {
        $product = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => $name, 'code' => $code, 'is_active' => true]);
        $product->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $product;
    }

    private function variant(Product $product, string $name, string $code, array $outlets): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'name' => $name, 'code' => $code,
            'price' => 1000, 'price_dine_in' => 1000, 'price_delivery' => 1000, 'is_active' => true,
        ]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    private function ingredient(string $name, array $outlets): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->ingredientCategory->id, 'name' => $name, 'code' => strtoupper($name), 'unit' => 'ml',
            'ingredient_type' => 'raw', 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true,
        ]);
        $ingredient->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $ingredient;
    }

    private function user(string $roleCode, array $outlets): User
    {
        $user = User::create([
            'name' => $roleCode, 'username' => $roleCode.'-'.uniqid(), 'email' => $roleCode.uniqid().'@example.test',
            'password' => 'password', 'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id,
            'outlet_id' => $outlets[0]->id, 'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }
}
