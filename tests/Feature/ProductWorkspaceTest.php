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
use App\Models\StockBalance;
use App\Models\User;
use App\Services\ProductWorkspace;
use App\Services\SaleEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * UX-B: the Product Workspace (products.edit) - read-only sections, access scope, return context.
 */
class ProductWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $bxc;

    protected Outlet $bazaar;

    protected Outlet $kemang;

    protected Brand $brand;

    protected ProductCategory $category;

    protected Product $product;

    protected ProductVariant $regular;

    protected ProductVariant $large;

    protected Ingredient $milk;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bxc = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $this->bazaar = Outlet::create(['name' => 'Bazaar', 'code' => 'BZR', 'is_active' => true]);
        $this->kemang = Outlet::create(['name' => 'Kemang', 'code' => 'KMG', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);

        $this->product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->category->id,
            'name' => 'Ube Latte',
            'code' => 'UBE-LATTE',
            'description' => 'Purple yam latte',
            'is_active' => true,
        ]);
        $this->product->outlets()->sync([$this->bxc->id, $this->bazaar->id]);

        $this->regular = $this->makeVariant($this->product, 'Regular', 'UBE-R', 20000, 22000, [$this->bxc, $this->bazaar]);
        $this->large = $this->makeVariant($this->product, 'Large', 'UBE-L', 25000, 27500, [$this->bxc]);

        $packaging = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->milk = Ingredient::create([
            'ingredient_category_id' => $packaging->id, 'name' => 'Fresh Milk', 'code' => 'FRESH_MILK', 'unit' => 'ml',
            'ingredient_type' => 'raw', 'minimum_stock' => 500, 'cost_per_unit' => 10, 'is_active' => true,
        ]);
        $this->milk->outlets()->sync([$this->bxc->id, $this->bazaar->id]);

        $this->owner = $this->makeUser('owner', [$this->bxc, $this->bazaar, $this->kemang]);
    }

    // ---- access -------------------------------------------------------------------------------------

    public function test_owner_opens_the_workspace_with_the_right_product_and_all_six_sections(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', $this->product))->assertOk()->getContent();

        $this->assertStringContainsString('data-product-workspace', $html);
        $this->assertStringContainsString('data-pw-product-id="'.$this->product->id.'"', $html);
        $this->assertStringContainsString('<h1 class="pw-title" data-pw-title>Ube Latte</h1>', $html);
        $this->assertStringContainsString('UBE-LATTE', $html);

        foreach (array_keys(ProductWorkspace::SECTIONS) as $section) {
            $this->assertStringContainsString('data-pw-panel="'.$section.'"', $html);
            $this->assertStringContainsString('data-pw-nav="'.$section.'"', $html);
        }

        $this->assertSame(['general', 'outlets', 'variants', 'recipe', 'stock', 'promo'], array_keys(ProductWorkspace::SECTIONS));
        $this->assertStringNotContainsStringIgnoringCase('modifier', $html);
    }

    public function test_admin_outlet_opens_a_product_in_its_outlets(): void
    {
        $adminOutlet = $this->makeUser('admin_outlet', [$this->bazaar]);

        $this->actingAs($adminOutlet)->get(route('backoffice.products.edit', $this->product))->assertOk();
    }

    public function test_admin_outlet_cannot_open_an_out_of_scope_product_by_url(): void
    {
        $adminOutlet = $this->makeUser('admin_outlet', [$this->kemang]);

        $this->actingAs($adminOutlet)->get(route('backoffice.products.edit', $this->product))->assertForbidden();
        $this->actingAs($adminOutlet)->get(route('backoffice.products.edit', [$this->product, 'section' => 'promo']))->assertForbidden();
    }

    public function test_admin_outlet_cannot_open_a_product_without_any_outlet(): void
    {
        $this->product->outlets()->sync([]);
        $adminOutlet = $this->makeUser('admin_outlet', [$this->bxc]);

        $this->actingAs($adminOutlet)->get(route('backoffice.products.edit', $this->product))->assertForbidden();
        $this->actingAs($this->owner)->get(route('backoffice.products.edit', $this->product))->assertOk();
    }

    public function test_roles_without_product_access_are_still_rejected(): void
    {
        foreach (['staff_gudang', 'kasir'] as $role) {
            $this->actingAs($this->makeUser($role, [$this->bxc]))
                ->get(route('backoffice.products.edit', $this->product))
                ->assertForbidden();
        }
    }

    public function test_unknown_product_id_is_not_found(): void
    {
        $this->actingAs($this->owner)->get('/backoffice/products/999999/edit')->assertNotFound();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('backoffice.products.edit', $this->product))->assertRedirect(route('login'));
    }

    // ---- sections -------------------------------------------------------------------------------------

    public static function sectionProvider(): array
    {
        return [
            'general' => ['general', 'general'],
            'outlets' => ['outlets', 'outlets'],
            'variants' => ['variants', 'variants'],
            'recipe' => ['recipe', 'recipe'],
            'stock' => ['stock', 'stock'],
            'promo' => ['promo', 'promo'],
            'unknown falls back' => ['modifiers', 'general'],
            'empty falls back' => ['', 'general'],
        ];
    }

    #[DataProvider('sectionProvider')]
    public function test_section_query_selects_the_visible_panel(string $requested, string $expected): void
    {
        $html = $this->actingAs($this->owner)
            ->get(route('backoffice.products.edit', [$this->product, 'section' => $requested]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-pw-section="'.$expected.'"', $html);

        foreach (array_keys(ProductWorkspace::SECTIONS) as $section) {
            $panel = $this->panelTag($html, $section);
            $section === $expected
                ? $this->assertStringNotContainsString(' hidden', $panel, $section)
                : $this->assertStringContainsString(' hidden', $panel, $section);
        }

        $this->assertMatchesRegularExpression('/class="pw-nav-link is-active"\s+data-pw-nav="'.$expected.'"/', $html);
    }

    public function test_section_query_with_an_array_value_falls_back_safely(): void
    {
        $this->actingAs($this->owner)
            ->get('/backoffice/products/'.$this->product->id.'/edit?section[]=promo')
            ->assertOk()
            ->assertSee('data-pw-section="general"', false);
    }

    // ---- return context -------------------------------------------------------------------------------

    public function test_close_returns_to_the_list_context_and_section_links_keep_it(): void
    {
        $returnTo = '/backoffice/products?search=ube&category_id='.$this->category->id;

        $html = $this->actingAs($this->owner)
            ->get(route('backoffice.products.edit', [$this->product, 'return_to' => $returnTo, 'section' => 'variants']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="'.e($returnTo.'#product-'.$this->product->id).'" class="btn btn-dark" data-pw-close', $html);
        $this->assertStringContainsString('href="'.e(ProductWorkspace::url($this->product, 'promo', $returnTo)).'"', $html);
        $this->assertStringContainsString(e('section=promo&return_to='.urlencode($returnTo)), $html);
    }

    public function test_close_without_return_to_goes_to_the_plain_index(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', $this->product))->getContent();

        // Same fallback as every Backoffice editor: no return_to -> the plain index, no anchor.
        $this->assertStringContainsString('href="/backoffice/products" class="btn btn-dark" data-pw-close', $html);
    }

    public function test_hostile_return_to_is_ignored_everywhere_in_the_workspace(): void
    {
        $html = $this->actingAs($this->owner)
            ->get(route('backoffice.products.edit', [$this->product, 'return_to' => '//evil.example/backoffice']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('evil.example', $html);
    }

    public function test_existing_editors_are_linked_with_return_to_pointing_back_to_their_workspace_section(): void
    {
        $returnTo = '/backoffice/products?search=ube';
        $this->makeRecipe($this->regular, true, [[$this->milk, 150]]);

        $html = $this->actingAs($this->owner)
            ->get(route('backoffice.products.edit', [$this->product, 'return_to' => $returnTo]))
            ->getContent();

        $variantsBack = ProductWorkspace::url($this->product, 'variants', $returnTo);
        $recipeBack = ProductWorkspace::url($this->product, 'recipe', $returnTo);

        // Variants are edited as one group per Product (existing screen): any Variant opens the group.
        $this->assertStringContainsString(e(route('backoffice.variants.edit', [$this->large->id, 'return_to' => $variantsBack], false)), $html);
        $this->assertStringContainsString(e(route('backoffice.variants.create', ['return_to' => $variantsBack], false)), $html);
        $this->assertStringContainsString(e(route('backoffice.recipes.edit', [Recipe::first()->id, 'return_to' => $recipeBack], false)), $html);
        $this->assertStringContainsString(e(route('backoffice.recipes.create', ['return_to' => $recipeBack], false)), $html);
    }

    public function test_saving_in_the_linked_variant_editor_comes_back_to_the_workspace_section(): void
    {
        $back = ProductWorkspace::url($this->product, 'variants', '/backoffice/products?search=ube');

        $this->actingAs($this->owner)
            ->delete(route('backoffice.variants.destroy', $this->large), ['return_to' => $back])
            ->assertRedirect(url($back.'#variant-group-'.$this->product->id));
    }

    // ---- read-only context ----------------------------------------------------------------------------

    public function test_variant_summary_shows_codes_status_both_prices_and_outlets(): void
    {
        $this->large->update(['is_active' => false]);

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'variants']))->getContent();
        $regularRow = $this->rowFor($html, 'data-pw-variant="'.$this->regular->id.'"');
        $largeRow = $this->rowFor($html, 'data-pw-variant="'.$this->large->id.'"');

        $this->assertStringContainsString('UBE-R', $regularRow);
        $this->assertStringContainsString('Rp 20.000', $regularRow);
        $this->assertStringContainsString('Rp 22.000', $regularRow);
        $this->assertStringContainsString('BXC', $regularRow);
        $this->assertStringContainsString('Bazaar', $regularRow);
        $this->assertStringContainsString('status-active">Active', $regularRow);

        $this->assertStringContainsString('Rp 25.000', $largeRow);
        $this->assertStringContainsString('Rp 27.500', $largeRow);
        $this->assertStringContainsString('status-inactive">Inactive', $largeRow);
    }

    public function test_variant_prices_fall_back_to_the_legacy_price(): void
    {
        $this->regular->forceFill(['price' => 18000, 'price_dine_in' => null, 'price_delivery' => null])->save();

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', $this->product))->getContent();
        $row = $this->rowFor($html, 'data-pw-variant="'.$this->regular->id.'"');

        $this->assertSame(2, substr_count($row, 'Rp 18.000'));
    }

    public function test_recipe_status_none_inactive_empty_valid_and_ambiguous(): void
    {
        $empty = $this->makeVariant($this->product, 'Empty', 'UBE-E', 1, 1, [$this->bxc]);
        $ambiguous = $this->makeVariant($this->product, 'Ambiguous', 'UBE-A', 1, 1, [$this->bxc]);
        $inactive = $this->makeVariant($this->product, 'Inactive Recipe', 'UBE-I', 1, 1, [$this->bxc]);

        $this->makeRecipe($this->regular, true, [[$this->milk, 150]]);       // valid
        $this->makeRecipe($empty, true, []);                                  // empty
        $this->makeRecipe($ambiguous, true, [[$this->milk, 100]]);            // ambiguous (2 active)
        $this->makeRecipe($ambiguous, true, [[$this->milk, 120]]);
        $this->makeRecipe($inactive, false, [[$this->milk, 100]]);            // inactive
        // $this->large has none

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'recipe']))->getContent();

        $this->assertStringContainsString('data-pw-recipe-variant="'.$this->regular->id.'" data-pw-recipe-status="valid"', $html);
        $this->assertStringContainsString('data-pw-recipe-variant="'.$this->large->id.'" data-pw-recipe-status="none"', $html);
        $this->assertStringContainsString('data-pw-recipe-variant="'.$empty->id.'" data-pw-recipe-status="empty"', $html);
        $this->assertStringContainsString('data-pw-recipe-variant="'.$ambiguous->id.'" data-pw-recipe-status="ambiguous"', $html);
        $this->assertStringContainsString('data-pw-recipe-variant="'.$inactive->id.'" data-pw-recipe-status="inactive"', $html);

        // Ambiguous: both Recipes are listed and none is picked.
        foreach (Recipe::where('product_variant_id', $ambiguous->id)->get() as $recipe) {
            $this->assertStringContainsString(e(route('backoffice.recipes.edit', $recipe->id, false)), $html);
        }
        $this->assertStringContainsString('Sistem tidak memilih salah satu secara otomatis', $html);
    }

    public function test_readiness_uses_sale_eligibility_service_for_every_variant_and_outlet(): void
    {
        $this->makeRecipe($this->regular, true, [[$this->milk, 150]]);

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock']))->getContent();
        $service = app(SaleEligibilityService::class);

        // Rows: Variants by name; columns: Product outlets by name (same ordering as the page).
        $outlets = $this->product->outlets()->orderBy('name')->get();
        $variants = $this->product->variants()->orderBy('name')->get();
        $statuses = $outlets->mapWithKeys(fn ($outlet) => [$outlet->id => $service->variantStatuses($variants->pluck('id')->all(), $outlet->id)]);

        $expected = [];
        foreach ($variants as $variant) {
            foreach ($outlets as $outlet) {
                $status = $statuses[$outlet->id][$variant->id];
                $expected[] = $status['eligible'] ? 'eligible' : $status['reason'];
            }
        }

        preg_match_all('/data-pw-eligibility="([^"]+)"/', $html, $matches);

        $this->assertSame($expected, $matches[1]);
        $this->assertContains('eligible', $matches[1]);
        $this->assertContains('recipe_missing', $matches[1]);
        $this->assertContains('variant_not_at_outlet', $matches[1]);
        $this->assertStringContainsString('Jumlah stok saat ini tidak memblokir penjualan.', $html);
    }

    public function test_readiness_follows_the_active_outlet_context(): void
    {
        $html = $this->actingAs($this->owner)
            ->withSession(['active_backoffice_outlet_id' => $this->bxc->id])
            ->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock']))
            ->getContent();

        preg_match_all('/data-pw-eligibility="/', $html, $matches);
        $this->assertCount(2, $matches[0], 'two variants x one outlet');
    }

    public function test_ingredient_stock_context_shows_qty_minimum_and_low_stock(): void
    {
        $this->makeRecipe($this->regular, true, [[$this->milk, 150]]);
        StockBalance::create(['ingredient_id' => $this->milk->id, 'location_type' => 'outlet', 'location_id' => $this->bxc->id, 'qty_on_hand' => 300]);

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock']))->getContent();

        $this->assertStringContainsString('Fresh Milk', $html);
        $this->assertStringContainsString('500,00 ml', $html);
        $this->assertStringContainsString('300,00 ml', $html);
        $this->assertStringContainsString('Low Stock', $html);
        $this->assertStringContainsString('Belum ada saldo', $html); // Bazaar has no balance row
    }

    public function test_promo_section_lists_only_promos_that_use_this_products_variants(): void
    {
        $other = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Other', 'code' => 'OTHER', 'is_active' => true]);
        $otherVariant = $this->makeVariant($other, 'Regular', 'OTHER-R', 1, 1, [$this->bxc]);

        $mine = $this->makePromo('Ube Weekday Deal', $this->regular, [$this->bxc]);
        $legacy = Promo::create(['name' => 'Legacy Large Promo', 'requirement_logic' => 'and', 'reward_type' => 'free_item', 'reward_product_variant_id' => $this->large->id, 'status' => 'draft', 'is_active' => false]);
        $foreign = $this->makePromo('Other Product Promo', $otherVariant, [$this->bxc]);

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'promo']))->getContent();

        $this->assertStringContainsString('data-pw-promo="'.$mine->id.'"', $html);
        $this->assertStringContainsString('data-pw-promo="'.$legacy->id.'"', $html);
        $this->assertStringNotContainsString('data-pw-promo="'.$foreign->id.'"', $html);
        $this->assertStringContainsString('Regular (Syarat)', $html);
        $this->assertStringContainsString('Large (Reward)', $html);
        $this->assertStringContainsString(e(route('backoffice.promos.edit', [$mine->id, 'return_to' => ProductWorkspace::url($this->product, 'promo', null)], false)), $html);
    }

    public function test_promo_edit_link_is_hidden_from_roles_that_cannot_open_promos(): void
    {
        $promo = $this->makePromo('Ube Weekday Deal', $this->regular, [$this->bxc]);
        $adminOutlet = $this->makeUser('admin_outlet', [$this->bxc]);

        $html = $this->actingAs($adminOutlet)->get(route('backoffice.products.edit', [$this->product, 'section' => 'promo']))->getContent();

        $this->assertStringContainsString('data-pw-promo="'.$promo->id.'"', $html);
        $this->assertStringNotContainsString(route('backoffice.promos.edit', $promo->id, false), $html);
    }

    public function test_workspace_is_read_only_for_data_opening_it_changes_nothing(): void
    {
        $before = [$this->product->fresh()->toArray(), $this->regular->fresh()->outlets->pluck('id')->all()];

        $this->actingAs($this->owner)->get(route('backoffice.products.edit', $this->product))->assertOk();

        $this->assertEquals($before, [$this->product->fresh()->toArray(), $this->regular->fresh()->outlets->pluck('id')->all()]);
    }

    // ---- UX-C: General save ------------------------------------------------------------------------

    public function test_general_save_updates_only_the_product_fields_and_returns_fresh_sections(): void
    {
        $tea = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Tea', 'code' => 'TEA', 'is_active' => true]);
        $outletsBefore = $this->regular->outlets()->pluck('outlets.id')->sort()->values()->all();

        $response = $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.general', $this->product), $this->generalPayload([
                'name' => 'Ube Latte Baru', 'description' => 'Baru', 'product_category_id' => $tea->id, 'is_active' => 0,
            ]))
            ->assertOk()
            ->assertJson(['ok' => true, 'section' => 'general', 'title' => 'Ube Latte Baru', 'message' => 'General Product berhasil disimpan.']);

        $product = $this->product->fresh();
        $this->assertSame('Ube Latte Baru', $product->name);
        $this->assertSame('Baru', $product->description);
        $this->assertSame($tea->id, (int) $product->product_category_id);
        $this->assertFalse((bool) $product->is_active);

        // Outlets / Variants are not part of General.
        $this->assertEqualsCanonicalizing([$this->bxc->id, $this->bazaar->id], $product->outlets()->pluck('outlets.id')->all());
        $this->assertSame($outletsBefore, $this->regular->outlets()->pluck('outlets.id')->sort()->values()->all());

        $this->assertSame(array_keys(ProductWorkspace::SECTIONS), array_keys($response->json('sections')));
        $this->assertStringContainsString('Ube Latte Baru', $response->json('header'));
        $this->assertStringContainsString('status-inactive">Inactive', $response->json('header'));
        $this->assertStringContainsString('value="'.$tea->id.'" selected', $response->json('sections.general'));
    }

    public function test_general_save_validation_error_saves_nothing(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.general', $this->product), $this->generalPayload(['name' => '', 'code' => 'UBE-LATTE']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Taken', 'code' => 'TAKEN', 'is_active' => true]);

        $this->putJson(route('backoffice.products.workspace.general', $this->product), $this->generalPayload(['code' => 'TAKEN']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertSame('Ube Latte', $this->product->fresh()->name);
        $this->assertSame('UBE-LATTE', $this->product->fresh()->code);
    }

    public function test_general_category_must_be_active_unless_it_is_the_current_one(): void
    {
        $inactive = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Old', 'code' => 'OLD', 'is_active' => false]);

        $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.general', $this->product), $this->generalPayload(['product_category_id' => $inactive->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_category_id']);

        $this->category->update(['is_active' => false]);

        $this->putJson(route('backoffice.products.workspace.general', $this->product), $this->generalPayload())
            ->assertOk();
    }

    public function test_general_save_without_js_redirects_back_to_the_same_section_with_the_list_context(): void
    {
        $returnTo = '/backoffice/products?search=ube';

        $this->actingAs($this->owner)
            ->put(route('backoffice.products.workspace.general', $this->product), $this->generalPayload(['name' => 'Ube No JS', 'return_to' => $returnTo]))
            ->assertRedirect(url(ProductWorkspace::url($this->product, 'general', $returnTo)))
            ->assertSessionHas('success', 'General Product berhasil disimpan.');

        $this->assertSame('Ube No JS', $this->product->fresh()->name);
    }

    public function test_general_form_renders_in_the_workspace_with_the_current_values(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', $this->product))->getContent();

        $this->assertStringContainsString('action="'.route('backoffice.products.workspace.general', $this->product).'"', $html);
        $this->assertStringContainsString('name="name" value="Ube Latte"', $html);
        $this->assertStringContainsString('value="'.$this->category->id.'" selected', $html);
        $this->assertStringContainsString('data-pw-open-drawer="category"', $html);
        $this->assertStringNotContainsString('name="price', $html);
        $this->assertStringNotContainsString('type="file"', $html);
    }

    public function test_every_editable_field_has_its_own_error_slot_so_a_stale_error_can_be_cleared_per_field(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', $this->product))->assertOk()->getContent();

        foreach (['brand_id', 'product_category_id', 'name', 'code', 'description', 'is_active'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, $field);
            $this->assertMatchesRegularExpression('/data-pw-error="'.$field.'"\s+hidden/', $html, $field);
        }
        $this->assertMatchesRegularExpression('/data-pw-error="outlet_ids"\s+hidden/', $html);

        foreach (['name', 'brand_id', 'is_active'] as $field) {
            $this->assertMatchesRegularExpression('/data-pw-drawer-error="'.$field.'"\s+hidden/', $html, 'drawer '.$field);
        }

        // The page script clears only the edited field's message and keeps the server as the authority.
        $this->assertStringContainsString('function clearFieldError(input)', $html);
    }

    public function test_a_failed_general_save_renders_the_error_only_for_the_failing_field(): void
    {
        $url = ProductWorkspace::url($this->product, 'general', '/backoffice/products?search=ube');

        $this->actingAs($this->owner)
            ->from($url)
            ->put(route('backoffice.products.workspace.general', $this->product), $this->generalPayload(['name' => '', 'description' => 'keep me']))
            ->assertRedirect($url);

        $html = $this->get($url)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-pw-error="name"\s*>The name field is required\.<\/div>/', $html);
        $this->assertMatchesRegularExpression('/data-pw-error="code"\s+hidden/', $html);
        $this->assertStringContainsString('keep me', $html);
        $this->assertSame('Ube Latte', $this->product->fresh()->name);
    }

    // ---- UX-C: Outlets save --------------------------------------------------------------------------

    public function test_outlet_save_adds_an_outlet(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.outlets', $this->product), ['outlet_ids' => [$this->bxc->id, $this->bazaar->id, $this->kemang->id]])
            ->assertOk()
            ->assertJson(['ok' => true, 'section' => 'outlets', 'message' => 'Outlet Product berhasil disimpan.']);

        $this->assertEqualsCanonicalizing([$this->bxc->id, $this->bazaar->id, $this->kemang->id], $this->product->outlets()->pluck('outlets.id')->all());
        $this->assertEqualsCanonicalizing([$this->bxc->id, $this->bazaar->id], $this->regular->outlets()->pluck('outlets.id')->all(), 'variants are not widened');
    }

    public function test_outlet_save_trims_variant_outlets_and_deactivates_a_variant_left_without_outlets(): void
    {
        $bazaarOnly = $this->makeVariant($this->product, 'Bazaar Only', 'UBE-BZ', 1, 1, [$this->bazaar]);
        $noRows = $this->makeVariant($this->product, 'No Rows', 'UBE-NR', 1, 1, []);

        $response = $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.outlets', $this->product), ['outlet_ids' => [$this->bxc->id]])
            ->assertOk();

        $this->assertSame([$this->bxc->id], $this->product->outlets()->pluck('outlets.id')->all());
        $this->assertSame([$this->bxc->id], $this->regular->outlets()->pluck('outlets.id')->all());
        $this->assertTrue((bool) $this->regular->fresh()->is_active);
        $this->assertSame([$this->bxc->id], $this->large->outlets()->pluck('outlets.id')->all());
        $this->assertSame([], $bazaarOnly->outlets()->pluck('outlets.id')->all());
        $this->assertFalse((bool) $bazaarOnly->fresh()->is_active);
        // Current rule: a Variant without any outlet row is left alone.
        $this->assertTrue((bool) $noRows->fresh()->is_active);

        $this->assertStringContainsString('1 Variant dinonaktifkan', $response->json('message'));
    }

    public function test_outlet_save_keeps_outlets_outside_a_limited_users_access(): void
    {
        $this->product->outlets()->sync([$this->bxc->id, $this->bazaar->id, $this->kemang->id]);
        $this->regular->outlets()->sync([$this->bxc->id, $this->kemang->id]);
        $adminOutlet = $this->makeUser('admin_outlet', [$this->bxc, $this->bazaar]);

        $html = $this->actingAs($adminOutlet)->get(route('backoffice.products.edit', [$this->product, 'section' => 'outlets']))->getContent();
        $this->assertStringNotContainsString('name="outlet_ids[]" value="'.$this->kemang->id.'"', $html);
        $this->assertStringContainsString('pw-chip-locked">Kemang', $html);

        $this->putJson(route('backoffice.products.workspace.outlets', $this->product), ['outlet_ids' => [$this->bxc->id]])->assertOk();

        $this->assertEqualsCanonicalizing([$this->bxc->id, $this->kemang->id], $this->product->outlets()->pluck('outlets.id')->all());
        $this->assertEqualsCanonicalizing([$this->bxc->id, $this->kemang->id], $this->regular->outlets()->pluck('outlets.id')->all());

        $this->putJson(route('backoffice.products.workspace.outlets', $this->product), ['outlet_ids' => [$this->kemang->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_ids']);
    }

    public function test_outlet_save_requires_at_least_one_outlet(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.outlets', $this->product), ['outlet_ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_ids']);

        $this->assertCount(2, $this->product->outlets()->get());
    }

    public function test_outlet_preview_matches_the_actual_effect_and_warns_about_promos_without_changing_them(): void
    {
        $bazaarOnly = $this->makeVariant($this->product, 'Bazaar Only', 'UBE-BZ', 1, 1, [$this->bazaar]);
        $promo = $this->makePromo('Ube Bazaar Deal', $this->large, [$this->bazaar]);
        $unrelated = $this->makePromo('Large BXC Deal', $this->large, [$this->bxc]);
        $promoBefore = [$promo->outlets()->pluck('outlets.id')->all(), $promo->requirements()->pluck('product_variant_id')->all(), $promo->fresh()->status];

        $preview = $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.outlets.preview', $this->product), ['outlet_ids' => [$this->bxc->id]])
            ->assertOk()
            ->assertJson(['ok' => true, 'has_consequences' => true, 'removed' => [$this->bazaar->id], 'deactivated_variant_ids' => [$bazaarOnly->id], 'promo_ids' => [$promo->id]])
            ->json();

        $this->assertStringContainsString('data-pw-preview-variant="'.$this->regular->id.'"', $preview['html']);
        $this->assertStringContainsString('data-pw-preview-variant="'.$bazaarOnly->id.'"', $preview['html']);
        $this->assertStringNotContainsString('data-pw-preview-variant="'.$this->large->id.'"', $preview['html']);
        $this->assertStringContainsString('Variant akan dinonaktifkan', $preview['html']);
        $this->assertStringContainsString('data-pw-preview-promo="'.$promo->id.'"', $preview['html']);
        $this->assertStringNotContainsString('data-pw-preview-promo="'.$unrelated->id.'"', $preview['html']);
        $this->assertStringContainsString('<li data-pw-preview-promo="'.$promo->id.'">Ube Bazaar Deal memakai Large dan berlaku di Bazaar.</li>', $preview['html']);

        // Preview wrote nothing.
        $this->assertCount(2, $this->product->outlets()->get());
        $this->assertTrue((bool) $bazaarOnly->fresh()->is_active);

        $this->putJson(route('backoffice.products.workspace.outlets', $this->product), ['outlet_ids' => [$this->bxc->id]])->assertOk();

        // What the preview announced is exactly what happened.
        $deactivated = ProductVariant::where('product_id', $this->product->id)->where('is_active', false)->pluck('id')->all();
        $this->assertSame($preview['deactivated_variant_ids'], $deactivated);
        $this->assertSame([], $this->product->outlets()->where('outlets.id', $this->bazaar->id)->pluck('outlets.id')->all());
        $this->assertSame([], $this->regular->outlets()->where('outlets.id', $this->bazaar->id)->pluck('outlets.id')->all());

        // Promo untouched.
        $this->assertSame($promoBefore, [$promo->outlets()->pluck('outlets.id')->all(), $promo->requirements()->pluck('product_variant_id')->all(), $promo->fresh()->status]);
    }

    public function test_outlet_preview_without_changes_or_with_an_empty_selection(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.outlets.preview', $this->product), ['outlet_ids' => [$this->bxc->id, $this->bazaar->id]])
            ->assertOk()
            ->assertJson(['has_consequences' => false, 'empty_selection' => false])
            ->assertSee('data-pw-preview-state=\"unchanged\"', false);

        $this->postJson(route('backoffice.products.workspace.outlets.preview', $this->product), [])
            ->assertOk()
            ->assertJson(['empty_selection' => true])
            ->assertSee('data-pw-preview-state=\"empty\"', false);
    }

    public function test_outlet_save_only_touches_this_products_variants(): void
    {
        $other = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Other', 'code' => 'OTHER', 'is_active' => true]);
        $other->outlets()->sync([$this->bazaar->id]);
        $otherVariant = $this->makeVariant($other, 'Regular', 'OTHER-R', 1, 1, [$this->bazaar]);

        $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.outlets', $this->product), ['outlet_ids' => [$this->bxc->id], 'product_id' => $other->id])
            ->assertOk();

        $this->assertSame([$this->bazaar->id], $other->outlets()->pluck('outlets.id')->all());
        $this->assertSame([$this->bazaar->id], $otherVariant->outlets()->pluck('outlets.id')->all());
        $this->assertTrue((bool) $otherVariant->fresh()->is_active);
    }

    // ---- UX-C: inline Menu Category ------------------------------------------------------------------

    public function test_inline_category_is_created_with_the_category_page_rules_and_is_selectable(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.menu-categories.store', $this->product), ['name' => '  Signature   Latte ', 'brand_id' => $this->brand->id, 'is_active' => 1])
            ->assertOk()
            ->assertJson(['ok' => true, 'category' => ['name' => 'Signature Latte', 'brand_id' => $this->brand->id, 'is_active' => true]]);

        $category = ProductCategory::findOrFail($response->json('category.id'));
        $this->assertSame('SIGNATURE_LATTE', $category->code);

        // Product itself unchanged by creating a Category.
        $this->assertSame($this->category->id, (int) $this->product->fresh()->product_category_id);

        // Immediately selectable and savable.
        $this->get(route('backoffice.products.edit', $this->product))->assertSee('<option value="'.$category->id.'"', false);
        $this->putJson(route('backoffice.products.workspace.general', $this->product), $this->generalPayload(['product_category_id' => $category->id]))->assertOk();
        $this->assertSame($category->id, (int) $this->product->fresh()->product_category_id);

        // Same code generation as the Category page for a second similar name.
        $second = $this->postJson(route('backoffice.products.workspace.menu-categories.store', $this->product), ['name' => 'Signature-Latte', 'brand_id' => $this->brand->id, 'is_active' => 1])->json('category.id');
        $this->assertSame('SIGNATURE_LATTE_1', ProductCategory::find($second)->code);
    }

    public function test_inline_category_duplicate_and_validation_failures(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.menu-categories.store', $this->product), ['name' => ' kafei ', 'brand_id' => $this->brand->id, 'is_active' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Category dengan nama tersebut sudah ada (huruf besar/kecil dan spasi dianggap sama).']);

        $this->postJson(route('backoffice.products.workspace.menu-categories.store', $this->product), ['name' => '', 'brand_id' => $this->brand->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Nama category wajib diisi.']);

        $this->postJson(route('backoffice.products.workspace.menu-categories.store', $this->product), ['name' => 'Brandless'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['brand_id' => 'Brand wajib dipilih.']);

        $this->postJson(route('backoffice.products.workspace.menu-categories.store', $this->product), ['name' => 'Ghost Brand', 'brand_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['brand_id']);

        $this->assertSame(1, ProductCategory::count());
    }

    public function test_inline_inactive_category_is_created_but_reported_as_not_selectable(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.menu-categories.store', $this->product), ['name' => 'Seasonal', 'brand_id' => $this->brand->id, 'is_active' => 0])
            ->assertOk()
            ->assertJson(['category' => ['is_active' => false]]);

        $this->get(route('backoffice.products.edit', $this->product))->assertDontSee('>Seasonal<', false);
    }

    public function test_classic_menu_category_route_still_works(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backoffice.menu-categories.store'), ['name' => 'Classic Route', 'brand_id' => $this->brand->id, 'is_active' => 1])
            ->assertRedirect()
            ->assertSessionHas('success', 'Kategori berhasil ditambahkan.');

        $this->assertSame('CLASSIC_ROUTE', ProductCategory::where('name', 'Classic Route')->value('code'));
    }

    // ---- UX-C: security ------------------------------------------------------------------------------

    public function test_workspace_writes_reject_out_of_scope_products_and_roles_without_product_access(): void
    {
        $outOfScope = $this->makeUser('admin_outlet', [$this->kemang]);
        $staffGudang = $this->makeUser('staff_gudang', [$this->bxc]);

        foreach ([$outOfScope, $staffGudang] as $user) {
            $this->actingAs($user)->putJson(route('backoffice.products.workspace.general', $this->product), $this->generalPayload(['name' => 'Hacked']))->assertForbidden();
            $this->actingAs($user)->putJson(route('backoffice.products.workspace.outlets', $this->product), ['outlet_ids' => [$this->kemang->id]])->assertForbidden();
            $this->actingAs($user)->postJson(route('backoffice.products.workspace.outlets.preview', $this->product), ['outlet_ids' => [$this->kemang->id]])->assertForbidden();
            $this->actingAs($user)->postJson(route('backoffice.products.workspace.menu-categories.store', $this->product), ['name' => 'Sneaky', 'brand_id' => $this->brand->id])->assertForbidden();
        }

        $this->assertSame('Ube Latte', $this->product->fresh()->name);
        $this->assertCount(2, $this->product->outlets()->get());
        $this->assertSame(0, ProductCategory::where('name', 'Sneaky')->count());
    }

    public function test_classic_product_update_also_rejects_an_out_of_scope_admin_outlet(): void
    {
        $outOfScope = $this->makeUser('admin_outlet', [$this->kemang]);

        $this->actingAs($outOfScope)
            ->put(route('backoffice.products.update', $this->product), $this->generalPayload(['name' => 'Hacked', 'outlet_ids' => [$this->kemang->id]]))
            ->assertForbidden();

        $this->assertSame('Ube Latte', $this->product->fresh()->name);
        $this->assertEqualsCanonicalizing([$this->bxc->id, $this->bazaar->id], $this->product->outlets()->pluck('outlets.id')->all());

        // In-scope admin outlet keeps the classic behaviour.
        $inScope = $this->makeUser('admin_outlet', [$this->bxc]);
        $this->actingAs($inScope)
            ->put(route('backoffice.products.update', $this->product), $this->generalPayload(['outlet_ids' => [$this->bxc->id]]))
            ->assertRedirect(route('backoffice.products.index'));
    }

    public function test_unknown_product_on_workspace_endpoints_is_not_found(): void
    {
        $this->actingAs($this->owner)->putJson('/backoffice/products/999999/workspace/general', $this->generalPayload())->assertNotFound();
        $this->actingAs($this->owner)->postJson('/backoffice/products/999999/workspace/outlets/preview', [])->assertNotFound();
    }

    public function test_workspace_routes_keep_the_web_middleware_stack_with_csrf(): void
    {
        foreach (['general', 'outlets', 'outlets.preview', 'menu-categories.store'] as $name) {
            $middleware = \Illuminate\Support\Facades\Route::getRoutes()->getByName('backoffice.products.workspace.'.$name)->gatherMiddleware();

            $this->assertContains('web', $middleware, $name);
            $this->assertContains('auth', $middleware, $name);
            $this->assertContains(\App\Http\Middleware\ResolveBackofficeOutlet::class, $middleware, $name);
        }

        // CSRF itself lives in the framework's default web group (bootstrap/app.php adds nothing).
        $this->assertContains(
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web']
        );
    }

    protected function generalPayload(array $overrides = []): array
    {
        return array_merge([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->category->id,
            'name' => 'Ube Latte',
            'code' => 'UBE-LATTE',
            'description' => 'Purple yam latte',
            'is_active' => 1,
        ], $overrides);
    }

    // ---- helpers -------------------------------------------------------------------------------------

    protected function makeVariant(Product $product, string $name, string $code, float $dineIn, float $delivery, array $outlets): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'name' => $name, 'code' => $code,
            'price' => $dineIn, 'price_dine_in' => $dineIn, 'price_delivery' => $delivery, 'is_active' => true,
        ]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    protected function makeRecipe(ProductVariant $variant, bool $active, array $items): Recipe
    {
        $recipe = Recipe::create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'name' => 'Recipe '.$variant->name, 'is_active' => $active]);

        foreach ($items as [$ingredient, $qty]) {
            RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'qty' => $qty, 'unit' => $ingredient->unit]);
        }

        return $recipe;
    }

    protected function makePromo(string $name, ProductVariant $variant, array $outlets): Promo
    {
        $promo = Promo::create(['name' => $name, 'requirement_logic' => 'and', 'status' => 'active', 'is_active' => true, 'active_days' => ['monday']]);
        PromoRequirement::create(['promo_id' => $promo->id, 'product_variant_id' => $variant->id, 'qty' => 1]);
        $promo->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $promo;
    }

    protected function makeUser(string $roleCode, array $outlets): User
    {
        $user = User::create([
            'name' => $roleCode, 'username' => $roleCode.'-'.uniqid(), 'email' => $roleCode.uniqid().'@example.test',
            'password' => 'password', 'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id,
            'outlet_id' => $outlets[0]->id, 'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }

    protected function panelTag(string $html, string $section): string
    {
        preg_match('/<section class="pw-panel"\s+id="pw-section-'.$section.'"[^>]*>/s', $html, $match);
        $this->assertNotEmpty($match, 'panel '.$section);

        return $match[0];
    }

    protected function rowFor(string $html, string $marker): string
    {
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, $marker);

        return substr($html, $start, strpos($html, '</tr>', $start) - $start);
    }
}
