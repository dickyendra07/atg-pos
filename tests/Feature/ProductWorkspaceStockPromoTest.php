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
use App\Models\StockBalance;
use App\Models\User;
use App\Services\ProductWorkspace;
use App\Services\SaleEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * UX-G: Stock & Readiness and Promo in the Product Workspace. Both sections only READ: configuration
 * readiness comes from SaleEligibilityService, stock is an observation that never decides readiness,
 * and Promos are only ever changed in the Promo editor.
 */
class ProductWorkspaceStockPromoTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $a;

    private Outlet $b;

    private Outlet $c;

    private IngredientCategory $dairy;

    private Product $product;

    private ProductVariant $regular;

    private ProductVariant $large;

    private Ingredient $milk;

    private Ingredient $sugar;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Outlet::create(['name' => 'Alpha', 'code' => 'A', 'is_active' => true]);
        $this->b = Outlet::create(['name' => 'Bravo', 'code' => 'B', 'is_active' => true]);
        $this->c = Outlet::create(['name' => 'Charlie', 'code' => 'C', 'is_active' => true]);
        $brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $category = ProductCategory::create(['brand_id' => $brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);

        $this->product = Product::create(['brand_id' => $brand->id, 'product_category_id' => $category->id, 'name' => 'Ube Latte', 'code' => 'UBE', 'is_active' => true]);
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);

        $this->regular = $this->variant($this->product, 'Regular', 'UBE-R', [$this->a, $this->b]);
        $this->large = $this->variant($this->product, 'Large', 'UBE-L', [$this->a, $this->b]);

        $this->dairy = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->milk = $this->ingredient('Fresh Milk', 'ml', [$this->a, $this->b], 500);
        $this->sugar = $this->ingredient('Liquid Sugar', 'ml', [$this->a, $this->b], 100);

        $this->owner = $this->user('owner', [$this->a, $this->b, $this->c]);
    }

    // ================================================================================================
    // STOCK & READINESS: configuration
    // ================================================================================================

    public function test_a_ready_variant_is_ready_per_outlet_with_its_ingredients_listed(): void
    {
        $this->recipe($this->regular, true, [[$this->milk, 150], [$this->sugar, 20]]);
        $this->balance($this->milk, $this->a, 900);
        $this->balance($this->sugar, $this->a, 300);
        $this->balance($this->milk, $this->b, 900);
        $this->balance($this->sugar, $this->b, 300);

        $html = $this->stock();
        $card = $this->variantCard($html, $this->regular);

        $this->assertStringContainsString('data-pw-config="ready"', $card);
        $this->assertStringContainsString('Konfigurasi: Siap jual', $card);
        $this->assertStringContainsString('Stok: normal', $card);
        $this->assertStringContainsString('2/2 outlet', $card);
        $this->assertStringContainsString('2 bahan', $card);
        $this->assertSame(0, substr_count($card, 'data-pw-readiness-issue'));
        // Large has no Recipe: configuration says so, stock offers no rows.
        $this->assertStringContainsString('data-pw-config="blocked"', $this->variantCard($html, $this->large));
    }

    public function test_every_recipe_problem_is_reported_with_the_sale_eligibility_reason(): void
    {
        $empty = $this->variant($this->product, 'Empty', 'UBE-E', [$this->a]);
        $inactive = $this->variant($this->product, 'Inactive', 'UBE-I', [$this->a]);
        $ambiguous = $this->variant($this->product, 'Ambiguous', 'UBE-A', [$this->a]);
        $this->recipe($empty, true, []);
        $this->recipe($inactive, false, [[$this->milk, 100]]);
        $this->recipe($ambiguous, true, [[$this->milk, 100]]);
        $this->recipe($ambiguous, true, [[$this->milk, 120]]);

        $html = $this->stock();

        $this->assertStringContainsString('data-pw-readiness-issue="recipe_missing"', $this->variantCard($html, $this->large));
        $this->assertStringContainsString('data-pw-readiness-issue="recipe_empty"', $this->variantCard($html, $empty));
        $this->assertStringContainsString('data-pw-readiness-issue="recipe_inactive"', $this->variantCard($html, $inactive));
        $this->assertStringContainsString('data-pw-readiness-issue="recipe_ambiguous"', $this->variantCard($html, $ambiguous));

        // An empty Recipe has no stock context: no misleading "Stok: normal", an explanation instead.
        $emptyCard = $this->variantCard($html, $empty);
        $this->assertStringNotContainsString('Stok: normal', $emptyCard);
        $this->assertStringNotContainsString('data-pw-stock-detail', $emptyCard);
        $this->assertStringContainsString('Recipe aktif belum memiliki bahan', $emptyCard);

        // Same answer as the Cashier's rule engine, outlet by outlet.
        $statuses = app(SaleEligibilityService::class)->variantStatuses([$empty->id, $inactive->id, $ambiguous->id, $this->large->id], $this->a->id);
        foreach ($statuses as $status) {
            $this->assertFalse($status['eligible']);
        }
    }

    public function test_ambiguous_variant_stays_not_ready_with_review_wording_and_no_fix_offered(): void
    {
        $ambiguous = $this->variant($this->product, 'Ambiguous', 'UBE-A', [$this->a]);
        $first = $this->recipe($ambiguous, true, [[$this->milk, 1179]]);
        $second = $this->recipe($ambiguous, true, [[$this->milk, 46084]]);
        $before = $this->recipeSnapshot();

        $html = $this->stock();
        $card = $this->variantCard($html, $ambiguous);

        $this->assertStringContainsString('data-pw-config="blocked"', $card);
        $this->assertStringContainsString(ProductWorkspace::AMBIGUOUS_MESSAGE, $card);
        $this->assertStringContainsString('Kebutuhan stok tidak ditampilkan', $card);
        // No Recipe is picked: neither Recipe's stored quantity shows up as a stock need.
        $this->assertStringNotContainsString('data-pw-stock-row', $card);
        // Pointer to the Recipe section only: no activate / deactivate / edit-form entry point in this panel.
        $panel = $this->panel($html, 'stock');
        $this->assertStringNotContainsString('/activate', $panel);
        $this->assertStringNotContainsString('/deactivate', $panel);
        $this->assertStringNotContainsString('recipes/'.$first->id, $panel);
        $this->assertStringNotContainsString('recipes/'.$second->id, $panel);
        $this->assertSame($before, $this->recipeSnapshot());
    }

    public function test_inactive_ingredient_and_ingredient_missing_from_an_outlet_block_the_variant(): void
    {
        $ice = $this->ingredient('Ice Cube', 'gram', [$this->a], 0);          // not assigned to Bravo
        $off = $this->ingredient('Old Syrup', 'ml', [$this->a, $this->b], 0, false);
        $v1 = $this->variant($this->product, 'Iced', 'UBE-IC', [$this->a, $this->b]);
        $v2 = $this->variant($this->product, 'Syrupy', 'UBE-SY', [$this->a]);
        $this->recipe($v1, true, [[$this->milk, 100], [$ice, 50]]);
        $this->recipe($v2, true, [[$off, 10]]);

        $html = $this->stock();

        $iced = $this->variantCard($html, $v1);
        $this->assertStringContainsString('data-pw-config="partial"', $iced);
        $this->assertStringContainsString('data-pw-readiness-issue="ingredient_not_at_outlet"', $iced);
        $this->assertStringContainsString('Outlet: Bravo', $iced);
        $this->assertStringContainsString('Tidak tersedia di outlet', $iced);

        $syrupy = $this->variantCard($html, $v2);
        $this->assertStringContainsString('data-pw-readiness-issue="ingredient_inactive"', $syrupy);
    }

    public function test_variant_and_product_outlet_intersection_is_respected(): void
    {
        // Large is not sold at Bravo: blocked there, no stock rows there; Charlie is not a Product outlet at all.
        $this->large->outlets()->sync([$this->a->id]);
        $this->recipe($this->large, true, [[$this->milk, 100]]);
        $this->balance($this->milk, $this->b, 777);

        $html = $this->stock();
        $card = $this->variantCard($html, $this->large);

        $this->assertStringContainsString('data-pw-readiness-issue="variant_not_at_outlet"', $card);
        $this->assertStringContainsString('data-pw-stock-outlet="'.$this->a->id.'"', $card);
        $this->assertStringNotContainsString('data-pw-stock-outlet="'.$this->b->id.'"', $card);
        $this->assertStringNotContainsString('777,00', $card);
        $this->assertStringNotContainsString('Charlie', $this->panel($html, 'stock'));
    }

    public function test_inactive_product_and_variant_are_reported_by_the_eligibility_service(): void
    {
        $this->recipe($this->regular, true, [[$this->milk, 100]]);
        $this->regular->update(['is_active' => false]);

        $this->assertStringContainsString('data-pw-readiness-issue="variant_inactive"', $this->variantCard($this->stock(), $this->regular));

        $this->regular->update(['is_active' => true]);
        $this->product->update(['is_active' => false]);

        $this->assertStringContainsString('data-pw-readiness-issue="product_inactive"', $this->variantCard($this->stock(), $this->regular));
    }

    public function test_readiness_cells_equal_the_sale_eligibility_service_for_every_variant_and_outlet(): void
    {
        $this->recipe($this->regular, true, [[$this->milk, 100]]);
        $this->large->outlets()->sync([$this->a->id]);

        $html = $this->stock();
        $service = app(SaleEligibilityService::class);
        $expected = [];

        foreach ($this->product->variants()->orderBy('name')->get() as $variant) {
            foreach ([$this->a, $this->b] as $outlet) {
                $status = $service->variantStatuses([$variant->id], $outlet->id)[$variant->id];
                $expected[] = $status['eligible'] ? 'eligible' : $status['reason'];
            }
        }

        preg_match_all('/data-pw-eligibility="([^"]+)"/', $html, $matches);
        $this->assertSame($expected, $matches[1]);
    }

    public function test_the_batch_eligibility_reader_matches_the_single_outlet_reader(): void
    {
        $this->recipe($this->regular, true, [[$this->milk, 100]]);
        $this->large->outlets()->sync([$this->a->id]);
        $service = app(SaleEligibilityService::class);
        $ids = [$this->regular->id, $this->large->id];

        $batch = $service->variantStatusesAtOutlets($ids, [$this->a->id, $this->b->id, $this->c->id]);

        foreach ([$this->a, $this->b, $this->c] as $outlet) {
            $this->assertSame($service->variantStatuses($ids, $outlet->id), $batch[$outlet->id]);
        }
        $this->assertSame([], $service->variantStatusesAtOutlets([], [$this->a->id]));
        $this->assertSame([], $service->variantStatusesAtOutlets($ids, []));
    }

    // ================================================================================================
    // STOCK & READINESS: stock observation (never decides readiness)
    // ================================================================================================

    public function test_stock_states_zero_negative_no_balance_low_and_normal(): void
    {
        $ice = $this->ingredient('Ice Cube', 'gram', [$this->a], 200);
        $tea = $this->ingredient('Tea Leaf', 'gram', [$this->a], 50);
        $cup = $this->ingredient('Cup', 'pcs', [$this->a], 10);
        $this->regular->outlets()->sync([$this->a->id]);
        $this->recipe($this->regular, true, [[$this->milk, 100], [$this->sugar, 10], [$ice, 50], [$tea, 5], [$cup, 1]]);

        $this->balance($this->milk, $this->a, 5000);   // normal
        $this->balance($this->sugar, $this->a, 100);   // low (<= minimum 100)
        $this->balance($ice, $this->a, 0);             // zero
        $this->balance($tea, $this->a, -10);           // negative
        // $cup: no balance row

        $card = $this->variantCard($this->stock(), $this->regular);

        $this->assertSame('normal', $this->rowState($card, $this->milk));
        $this->assertSame('low', $this->rowState($card, $this->sugar));
        $this->assertSame('zero', $this->rowState($card, $ice));
        $this->assertSame('negative', $this->rowState($card, $tea));
        $this->assertSame('none', $this->rowState($card, $cup));
        $this->assertStringContainsString('Stok nol', $card);
        $this->assertStringContainsString('Stok minus', $card);
        $this->assertStringContainsString('-10,00 gram', $card);
        $this->assertStringContainsString('Belum ada saldo', $card);
        $this->assertStringContainsString('Low Stock', $card);
        $this->assertStringContainsString('data-pw-stock-attention="4"', $card);
        $this->assertStringContainsString('Stok: perlu perhatian (4)', $card);
    }

    public function test_stock_never_changes_configuration_readiness(): void
    {
        $this->product->outlets()->sync([$this->a->id]);
        $this->regular->outlets()->sync([$this->a->id]);
        $this->recipe($this->regular, true, [[$this->milk, 150], [$this->sugar, 20]]);
        $service = app(SaleEligibilityService::class);

        $this->balance($this->milk, $this->a, 5000);
        $this->balance($this->sugar, $this->a, 5000);
        $ready = $this->variantCard($this->stock(), $this->regular);

        StockBalance::query()->update(['qty_on_hand' => -250]);
        $negative = $this->variantCard($this->stock(), $this->regular);

        StockBalance::query()->delete();
        $none = $this->variantCard($this->stock(), $this->regular);

        foreach ([$ready, $negative, $none] as $card) {
            $this->assertStringContainsString('data-pw-config="ready"', $card);
            $this->assertStringContainsString('data-pw-eligibility="eligible"', $card);
            $this->assertSame(0, substr_count($card, 'data-pw-readiness-issue'));
        }
        $this->assertStringContainsString('data-pw-stock-attention="0"', $ready);
        $this->assertStringContainsString('data-pw-stock-attention="2"', $negative);
        $this->assertStringContainsString('data-pw-stock-attention="2"', $none);
        $this->assertStringContainsString('Konfigurasi: Siap jual', $negative);
        $this->assertStringContainsString('Stok: perlu perhatian', $negative);
        $this->assertTrue($service->variantStatuses([$this->regular->id], $this->a->id)[$this->regular->id]['eligible']);
        $this->assertTrue((bool) $this->regular->fresh()->is_active);
        $this->assertTrue((bool) $this->recipeOf($this->regular)->is_active);
    }

    public function test_recipe_need_is_shown_exactly_as_stored_and_units_are_never_converted(): void
    {
        $this->regular->outlets()->sync([$this->a->id]);
        $this->recipe($this->regular, true, [[$this->milk, 46154, 'gr']]);   // old data with a different unit
        $before = $this->recipeSnapshot();

        $card = $this->variantCard($this->stock(), $this->regular);

        $this->assertStringContainsString('46.154,00 gr', $card);
        $this->assertStringContainsString('Satuan stok: ml (tidak dikonversi)', $card);
        $this->assertSame($before, $this->recipeSnapshot());
    }

    public function test_empty_states_are_helpful(): void
    {
        $html = $this->stock();

        $this->assertStringContainsString('Belum ada Recipe yang dapat digunakan untuk membaca kebutuhan stok.', $html);

        $this->product->outlets()->sync([]);
        $this->assertStringContainsString('Product belum tersedia di outlet aktif', $this->stock());
    }

    public function test_the_stock_panel_has_no_mutation_surface(): void
    {
        $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $this->balance($this->milk, $this->a, -5);

        $panel = $this->panel($this->stock(), 'stock');

        $this->assertStringNotContainsString('<form', $panel);
        $this->assertStringNotContainsString('<input', $panel);
        $this->assertStringNotContainsString('_method', $panel);
        $this->assertStringNotContainsString('adjustment', $panel);
        $this->assertStringNotContainsString('qty_on_hand', $panel);

        foreach (Route::getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'backoffice.products.workspace.')) {
                $this->assertStringNotContainsString('stock', (string) $route->getName());
            }
        }

        // Nothing but GET is reachable for stock under a Product.
        $this->actingAs($this->owner)->post('/backoffice/products/'.$this->product->id.'/workspace/stock', [])->assertNotFound();
    }

    // ================================================================================================
    // Permissions, limited users, Active Outlet
    // ================================================================================================

    public function test_a_limited_user_only_sees_stock_of_the_outlets_they_can_access(): void
    {
        $adminA = $this->user('admin_outlet', [$this->a]);
        $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $this->balance($this->milk, $this->a, 111);
        $this->balance($this->milk, $this->b, 777);

        $html = $this->actingAs($adminA)->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock']))->assertOk()->getContent();
        $panel = $this->panel($html, 'stock');

        $this->assertStringContainsString('111,00 ml', $panel);
        $this->assertStringNotContainsString('777,00', $panel);
        $this->assertStringNotContainsString('Bravo', $panel);
        $this->assertStringNotContainsString('Charlie', $panel);
        $this->assertStringContainsString('data-pw-eligibility', $panel);
        preg_match_all('/data-pw-eligibility="/', $panel, $cells);
        $this->assertCount(2, $cells[0], 'two variants x the one accessible outlet');
    }

    public function test_stock_section_shows_every_outlet_and_a_leftover_selection_changes_nothing(): void
    {
        $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $this->balance($this->milk, $this->a, 111);
        $this->balance($this->milk, $this->b, 777);
        $before = $this->dataSnapshot();

        // A selection left in a session from before the selector was removed is ignored.
        $html = $this->actingAs($this->owner)
            ->withSession(['active_backoffice_outlet_id' => $this->b->id])
            ->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock']))
            ->getContent();
        $panel = $this->panel($html, 'stock');

        $this->assertStringContainsString('777,00 ml', $panel);
        $this->assertStringContainsString('111,00 ml', $panel);
        $this->assertStringNotContainsString('data-pw-stock-scope', $panel);
        $this->assertStringNotContainsString('data-pw-stock-foreign-outlet', $panel);

        $this->assertSame($before, $this->dataSnapshot());
    }

    public function test_contextual_stock_links_return_to_the_workspace_stock_section(): void
    {
        $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $returnTo = ProductWorkspace::url($this->product, 'stock', null);

        $panel = $this->panel($this->stock(), 'stock');

        $this->assertStringContainsString(e(route('backoffice.stock-balances.index', ['ingredient_id' => $this->milk->id, 'return_to' => $returnTo], false)), $panel);
        $this->assertStringContainsString(e(route('backoffice.stock-movements.index', ['ingredient_id' => $this->milk->id, 'return_to' => $returnTo], false)), $panel);
        $this->assertStringContainsString(e(route('backoffice.stock-balances.index', ['return_to' => $returnTo], false)), $panel);
        $this->assertStringContainsString('data-pw-open-drawer="ingredient"', $panel);

        foreach (['stock-balances.index', 'stock-movements.index'] as $name) {
            $page = $this->actingAs($this->owner)->get(route('backoffice.'.$name, ['ingredient_id' => $this->milk->id, 'return_to' => $returnTo]))->assertOk()->getContent();

            $this->assertStringContainsString('data-return-to-back', $page);
            $this->assertStringContainsString('href="'.e($returnTo).'"', $page);
            $this->assertStringContainsString('<input type="hidden" name="return_to" value="'.e($returnTo).'">', $page);
        }
    }

    public function test_stock_pages_ignore_a_hostile_return_to_and_show_no_back_link_without_one(): void
    {
        foreach (['stock-balances.index', 'stock-movements.index'] as $name) {
            $hostile = $this->actingAs($this->owner)->get(route('backoffice.'.$name, ['return_to' => 'https://evil.example/x']))->assertOk()->getContent();
            $none = $this->get(route('backoffice.'.$name))->assertOk()->getContent();

            // Never turned into a back link or a hidden field.
            $this->assertStringNotContainsString('data-return-to-back', $hostile);
            $this->assertStringNotContainsString('<input type="hidden" name="return_to"', $hostile);
            $this->assertDoesNotMatchRegularExpression('/href="[^"]*return_to=[^"]*"[^>]*>Reset/', $hostile, 'not carried into the Reset links');
            $this->assertStringNotContainsString('data-return-to-back', $none);
        }
    }

    public function test_stock_and_promo_links_stay_behind_the_roles_that_own_those_pages(): void
    {
        $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $promo = $this->promo('Ube Deal', [$this->regular], [$this->a]);
        $adminA = $this->user('admin_outlet', [$this->a]);

        // admin_outlet may use Inventory Control (its own role list) but has no Promo role.
        $stock = $this->actingAs($adminA)->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock']))->getContent();
        $this->assertStringContainsString('data-pw-stock-link="balance"', $stock);

        $page = $this->get(route('backoffice.products.edit', [$this->product, 'section' => 'promo']))->getContent();
        $this->assertStringNotContainsString('data-pw-promo-create', $page);
        $this->assertStringNotContainsString('data-pw-promo-edit', $page);
        $this->assertStringContainsString('hanya dapat melihat Promo', $page);
        $this->get(route('backoffice.promos.edit', $promo))->assertRedirect(route('backoffice.index'));
        $this->get(route('backoffice.promos.create'))->assertRedirect(route('backoffice.index'));
    }

    public function test_stock_section_does_not_query_per_variant_or_per_ingredient(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock']))->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $count();   // the first request also pays one-off framework / session queries
        $small = $count();

        for ($i = 1; $i <= 5; $i++) {
            $variant = $this->variant($this->product, 'Extra '.$i, 'UBE-X'.$i, [$this->a, $this->b]);
            $ingredient = $this->ingredient('Extra Ingredient '.$i, 'gram', [$this->a, $this->b], 10);
            $this->balance($ingredient, $this->a, $i);
            $this->recipe($variant, true, [[$ingredient, 5], [$this->milk, 5]]);
        }

        $this->assertSame($small, $count(), 'query count must not grow with variants / ingredients / outlets');
    }

    // ================================================================================================
    // PROMO
    // ================================================================================================

    public function test_no_promo_shows_the_empty_state_and_the_create_action(): void
    {
        $html = $this->promoPage();

        $this->assertStringContainsString('Belum ada Promo yang menggunakan Variant dari Product ini.', $html);
        $this->assertStringContainsString('data-pw-promo-create', $html);
        $this->assertStringNotContainsString('data-pw-promo="', $html);
    }

    public function test_one_relevant_promo_shows_mechanism_outlets_schedule_and_status(): void
    {
        $promo = $this->promo('Ube Weekday Deal', [$this->regular], [$this->a, $this->b], rewards: [['discount_percent', 15]]);

        $html = $this->promoPage();
        $card = $this->promoCard($html, $promo);

        $this->assertStringContainsString('data-pw-promo-state="active"', $card);
        $this->assertStringContainsString('Aktif', $card);
        $this->assertStringContainsString('Syarat: Regular ×1', $card);
        $this->assertStringContainsString('Reward: Diskon 15%', $card);
        $this->assertStringContainsString('Regular (Syarat)', $card);
        $this->assertStringContainsString('1 Variant Product ini · 2 outlet', $card);
        $this->assertStringContainsString('Alpha, Bravo', $card);
        $this->assertStringContainsString('id="promo-'.$promo->id.'"', $html);
        $this->assertStringContainsString('1 Promo', $html);
        $this->assertStringContainsString('1 aktif', $html);
    }

    public function test_multiple_promos_are_each_classified_active_upcoming_expired_or_inactive(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $live = $this->promo('Live', [$this->regular], [$this->a], ['start_date' => '2026-06-01', 'end_date' => '2026-06-30']);
        $upcoming = $this->promo('Soon', [$this->regular], [$this->a], ['start_date' => '2026-07-01']);
        $expired = $this->promo('Old', [$this->regular], [$this->a], ['end_date' => '2026-06-14']);
        $draft = $this->promo('Idea', [$this->regular], [$this->a], ['status' => 'draft']);
        $stopped = $this->promo('Stopped', [$this->regular], [$this->a], ['status' => 'discontinued']);
        $off = $this->promo('Switched off', [$this->regular], [$this->a], ['is_active' => false]);

        $html = $this->promoPage();

        $this->assertStringContainsString('data-pw-promo-state="active"', $this->promoCard($html, $live));
        $this->assertStringContainsString('data-pw-promo-state="upcoming"', $this->promoCard($html, $upcoming));
        $this->assertStringContainsString('Akan datang', $this->promoCard($html, $upcoming));
        $this->assertStringContainsString('data-pw-promo-state="expired"', $this->promoCard($html, $expired));
        $this->assertStringContainsString('Berakhir', $this->promoCard($html, $expired));
        $this->assertStringContainsString('data-pw-promo-state="inactive"', $this->promoCard($html, $draft));
        $this->assertStringContainsString('Draft', $this->promoCard($html, $draft));
        $this->assertStringContainsString('Dihentikan', $this->promoCard($html, $stopped));
        $this->assertStringContainsString('Nonaktif', $this->promoCard($html, $off));
        $this->assertStringContainsString('6 Promo', $html);

        // The promo state helper is the single definition (inclusive date edges, like the Cashier).
        $this->assertSame('active', ProductWorkspace::promoState($live, Carbon::parse('2026-06-30 23:00'))['key']);
        $this->assertSame('expired', ProductWorkspace::promoState($live, Carbon::parse('2026-07-01 00:00'))['key']);
        $this->assertSame('upcoming', ProductWorkspace::promoState($live, Carbon::parse('2026-05-31 23:59'))['key']);

        Carbon::setTestNow();
    }

    public function test_live_promos_are_listed_before_upcoming_expired_and_inactive_ones(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->promo('A inactive', [$this->regular], [$this->a], ['status' => 'draft']);
        $this->promo('B expired', [$this->regular], [$this->a], ['end_date' => '2026-06-01']);
        $this->promo('C upcoming', [$this->regular], [$this->a], ['start_date' => '2026-07-01']);
        $this->promo('Z live', [$this->regular], [$this->a]);
        $this->promo('D live', [$this->regular], [$this->a]);

        preg_match_all('/data-pw-promo="\d+" data-pw-promo-state="([a-z]+)"/', $this->promoPage(), $matches);

        $this->assertSame(['active', 'active', 'upcoming', 'expired', 'inactive'], $matches[1]);

        Carbon::setTestNow();
    }

    public function test_promo_outside_its_day_window_says_so_without_changing_its_status(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');   // Monday
        $promo = $this->promo('Sunday only', [$this->regular], [$this->a], ['active_days' => ['sunday']]);

        $card = $this->promoCard($this->promoPage(), $promo);

        $this->assertStringContainsString('data-pw-promo-state="active"', $card);
        $this->assertStringContainsString('Saat ini di luar hari/jam Promo.', $card);

        Carbon::setTestNow();
    }

    public function test_a_promo_using_several_variants_of_the_product_reports_the_coverage(): void
    {
        $promo = $this->promo('Combo', [$this->regular, $this->large], [$this->a, $this->b], ['requirement_logic' => 'and'], [['free_item', 0, $this->large, 1]]);
        $foreign = $this->foreignVariant();
        PromoRequirement::create(['promo_id' => $promo->id, 'product_variant_id' => $foreign->id, 'qty' => 2]);

        $card = $this->promoCard($this->promoPage(), $promo);

        $this->assertStringContainsString('2 Variant Product ini · 2 outlet', $card);
        $this->assertStringContainsString('Regular (Syarat)', $card);
        $this->assertStringContainsString('Large (Syarat)', $card);
        $this->assertStringContainsString('Large (Reward)', $card);
        $this->assertStringContainsString('Gratis Large ×1', $card);
        $this->assertStringContainsString('Matcha - Regular ×2', $card, 'a Variant of another Product is named with its Product');
        $this->assertStringContainsString('(AND)', $card);
    }

    public function test_promos_of_other_products_are_not_listed(): void
    {
        $foreign = $this->foreignVariant();
        $other = $this->promo('Matcha Deal', [$foreign], [$this->a]);
        $mine = $this->promo('Ube Deal', [$this->regular], [$this->a]);

        $html = $this->promoPage();

        $this->assertStringContainsString('data-pw-promo="'.$mine->id.'"', $html);
        $this->assertStringNotContainsString('data-pw-promo="'.$other->id.'"', $html);
    }

    public function test_a_limited_user_never_sees_outlets_outside_their_access_nor_promos_only_there(): void
    {
        $adminA = $this->user('admin_outlet', [$this->a]);
        $shared = $this->promo('Shared Deal', [$this->regular], [$this->a, $this->b]);
        $elsewhere = $this->promo('Bravo Only Deal', [$this->regular], [$this->b]);
        $unassigned = $this->promo('No Outlet Yet', [$this->regular], [], ['status' => 'draft', 'is_active' => false]);

        $html = $this->actingAs($adminA)->get(route('backoffice.products.edit', [$this->product, 'section' => 'promo']))->assertOk()->getContent();
        $panel = $this->panel($html, 'promo');

        $card = $this->promoCard($panel, $shared);
        $this->assertStringContainsString('1 outlet (+1 outlet lain di luar akses)', $card);
        $this->assertStringNotContainsString('Bravo', $card);
        $this->assertStringNotContainsString('data-pw-promo="'.$elsewhere->id.'"', $panel);
        $this->assertStringNotContainsString('Bravo Only Deal', $panel);
        $this->assertStringContainsString('data-pw-promo="'.$unassigned->id.'"', $panel);
        $this->assertStringNotContainsString('Bravo', $panel);
        $this->assertStringNotContainsString('Charlie', $panel);
        $this->assertStringNotContainsString('data-pw-promo-edit', $panel);
        $this->assertStringNotContainsString('data-pw-promo-create', $panel);
    }

    public function test_promo_actions_are_offered_to_owner_and_admin_pusat_only(): void
    {
        $promo = $this->promo('Ube Deal', [$this->regular], [$this->a]);
        $returnTo = ProductWorkspace::url($this->product, 'promo', null);

        foreach (['owner', 'admin_pusat'] as $role) {
            $user = $this->user($role, [$this->a, $this->b]);
            $html = $this->actingAs($user)->get(route('backoffice.products.edit', [$this->product, 'section' => 'promo']))->getContent();

            $this->assertStringContainsString(e(route('backoffice.promos.edit', [$promo->id, 'return_to' => $returnTo], false)), $html, $role);
            $this->assertStringContainsString(e(route('backoffice.promos.create', ['return_to' => $returnTo], false)), $html, $role);
            $this->assertStringContainsString(e(route('backoffice.promos.create', ['variant_id' => $this->regular->id, 'return_to' => $returnTo], false)), $html, $role);
        }

        $adminOutlet = $this->user('admin_outlet', [$this->a]);
        $html = $this->actingAs($adminOutlet)->get(route('backoffice.products.edit', [$this->product, 'section' => 'promo']))->getContent();
        $this->assertStringContainsString('data-pw-promo="'.$promo->id.'"', $html, 'read-only information stays visible');
        $this->assertStringNotContainsString(route('backoffice.promos.edit', $promo->id, false), $html);
    }

    public function test_promo_list_covers_every_accessible_outlet_and_a_leftover_selection_changes_nothing(): void
    {
        $atA = $this->promo('At Alpha', [$this->regular], [$this->a]);
        $atB = $this->promo('At Bravo', [$this->regular], [$this->b]);

        $html = $this->actingAs($this->owner)
            ->withSession(['active_backoffice_outlet_id' => $this->a->id])
            ->get(route('backoffice.products.edit', [$this->product, 'section' => 'promo']))
            ->getContent();

        $this->assertStringContainsString('data-pw-promo="'.$atA->id.'"', $html);
        $this->assertStringContainsString('data-pw-promo="'.$atB->id.'"', $html);
        $this->assertStringNotContainsString('data-pw-promo-scope', $html);
    }

    public function test_promo_outlets_that_can_no_longer_sell_the_variant_are_flagged_not_fixed(): void
    {
        $this->large->outlets()->sync([$this->a->id]);
        $promo = $this->promo('Large Deal', [$this->large], [$this->a, $this->b]);
        $before = $this->promoSnapshot();

        $card = $this->promoCard($this->promoPage(), $promo);

        $this->assertStringContainsString('data-pw-promo-conflicts', $card);
        $this->assertStringContainsString('Large tidak tersedia di Bravo', $card);
        $this->assertStringContainsString('Promo tidak diubah otomatis', $card);
        $this->assertSame($before, $this->promoSnapshot());
    }

    public function test_legacy_only_promos_are_still_found_and_explained(): void
    {
        $legacy = Promo::create([
            'name' => 'Legacy Large Promo', 'requirement_logic' => 'and', 'requirement_product_variant_id' => $this->regular->id, 'requirement_qty' => 2,
            'reward_type' => 'free_item', 'reward_product_variant_id' => $this->large->id, 'reward_qty' => 1, 'status' => 'active', 'is_active' => true,
        ]);
        $legacy->outlets()->sync([$this->a->id]);

        $card = $this->promoCard($this->promoPage(), $legacy);

        $this->assertStringContainsString('Syarat: Regular ×2', $card);
        $this->assertStringContainsString('Gratis Large ×1', $card);
    }

    public function test_promo_without_outlets_is_flagged_when_it_is_meant_to_be_live(): void
    {
        $promo = $this->promo('Nowhere', [$this->regular], []);

        $this->assertStringContainsString('belum memiliki outlet', $this->promoCard($this->promoPage(), $promo));
    }

    // ---- create / edit through the existing Promo pages -----------------------------------------------

    public function test_create_promo_preselects_the_variant_only_and_saves_nothing(): void
    {
        $before = Promo::count();

        $html = $this->actingAs($this->owner)
            ->get(route('backoffice.promos.create', ['variant_id' => $this->regular->id, 'return_to' => ProductWorkspace::url($this->product, 'promo', null)]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('addRequirement({"product_variant_id":'.$this->regular->id.'})', $html);
        $this->assertStringContainsString('name="return_to" value="'.e(ProductWorkspace::url($this->product, 'promo', null)).'"', $html);
        // No outlet is ticked and nothing is stored.
        $this->assertStringNotContainsString(' checked', $this->between($html, 'data-promo-outlet-dropdown', 'requirement_logic'));
        $this->assertSame($before, Promo::count());

        foreach (['999999', 'abc', '0', ''] as $bad) {
            $page = $this->get(route('backoffice.promos.create', ['variant_id' => $bad]))->assertOk()->getContent();
            $this->assertStringContainsString('addRequirement([])', $page, 'unknown variant ids are ignored: '.$bad);
        }

        $inactive = $this->variant($this->product, 'Retired', 'UBE-OLD', [$this->a]);
        $inactive->update(['is_active' => false]);
        $this->assertStringContainsString('addRequirement([])', $this->get(route('backoffice.promos.create', ['variant_id' => $inactive->id]))->getContent());
    }

    public function test_saving_a_promo_from_the_workspace_context_returns_to_the_promo_section(): void
    {
        $returnTo = ProductWorkspace::url($this->product, 'promo', null);

        $response = $this->actingAs($this->owner)->post(route('backoffice.promos.store'), [
            'return_to' => $returnTo, 'name' => 'New Ube Deal', 'requirement_logic' => 'and', 'status' => 'active', 'is_active' => 1,
            'outlet_ids' => [$this->a->id], 'all_day' => 1,
            'requirements' => [['product_variant_id' => $this->regular->id, 'qty' => 1]],
            'rewards' => [['reward_type' => 'discount_amount', 'reward_value' => 5000]],
        ]);

        $promo = Promo::where('name', 'New Ube Deal')->firstOrFail();
        $response->assertRedirect($returnTo.'#promo-'.$promo->id);

        $html = $this->get($returnTo)->getContent();
        $this->assertStringContainsString('id="promo-'.$promo->id.'"', $html);
        $this->assertStringContainsString('Diskon Rp 5.000', $html);

        $this->put(route('backoffice.promos.update', $promo), [
            'return_to' => $returnTo, 'name' => 'Renamed', 'requirement_logic' => 'and', 'status' => 'active', 'is_active' => 1,
            'outlet_ids' => [$this->a->id], 'all_day' => 1,
            'requirements' => [['product_variant_id' => $this->regular->id, 'qty' => 1]],
            'rewards' => [['reward_type' => 'discount_amount', 'reward_value' => 5000]],
        ])->assertRedirect($returnTo.'#promo-'.$promo->id);

        $this->get(route('backoffice.promos.edit', [$promo, 'return_to' => 'https://evil.example']))->assertOk();
        $this->put(route('backoffice.promos.update', $promo), [
            'return_to' => 'https://evil.example', 'name' => 'Renamed', 'requirement_logic' => 'and', 'status' => 'active', 'is_active' => 1,
            'outlet_ids' => [$this->a->id], 'all_day' => 1,
            'requirements' => [['product_variant_id' => $this->regular->id, 'qty' => 1]],
            'rewards' => [['reward_type' => 'discount_amount', 'reward_value' => 5000]],
        ])->assertRedirect(route('backoffice.promos.index', [], false));
    }

    // ================================================================================================
    // Workspace saves never touch Promos; viewing never writes
    // ================================================================================================

    public function test_workspace_saves_do_not_mutate_promos(): void
    {
        $this->promo('Ube Bravo Deal', [$this->large], [$this->b], ['requirement_logic' => 'or'], [['discount_amount', 2500]]);
        $this->promo('Ube Both Deal', [$this->regular, $this->large], [$this->a, $this->b]);
        $before = $this->promoSnapshot();

        $this->actingAs($this->owner);

        $this->putJson(route('backoffice.products.workspace.general', $this->product), [
            'brand_id' => $this->product->brand_id, 'product_category_id' => $this->product->product_category_id,
            'name' => 'Ube Latte v2', 'code' => 'UBE', 'description' => 'x', 'is_active' => 1,
        ])->assertOk();
        $this->assertSame($before, $this->promoSnapshot(), 'general save');

        // Removing Bravo trims Variant outlets, but the Promos (and their Bravo assignment) stay as they are.
        $this->putJson(route('backoffice.products.workspace.outlets', $this->product), ['outlet_ids' => [$this->a->id]])->assertOk();
        $this->assertSame([$this->a->id], $this->large->outlets()->pluck('outlets.id')->all());
        $this->assertSame($before, $this->promoSnapshot(), 'outlet save');

        $this->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $this->regular]), [
            'name' => 'Regular', 'code' => 'UBE-R', 'price_dine_in' => 'Rp. 20.000', 'price_delivery' => 'Rp. 22.000',
            'outlet_ids' => [$this->a->id], 'is_active' => 1,
        ])->assertOk();
        $this->assertSame($before, $this->promoSnapshot(), 'variant save');

        $this->patchJson(route('backoffice.products.workspace.variants.deactivate', [$this->product, $this->regular]))->assertOk();
        $this->assertSame($before, $this->promoSnapshot(), 'variant deactivate');

        // ...and the section now tells the user about the consequence instead of fixing it.
        $html = $this->promoPage();
        $this->assertStringContainsString('data-pw-promo-conflicts', $html);
    }

    public function test_opening_the_workspace_sections_writes_nothing_and_leaves_suspicious_quantities_byte_identical(): void
    {
        // The historical quantities from the Recipe audit, on ambiguous and valid Variants alike.
        $ambiguous = $this->variant($this->product, 'Ambiguous', 'UBE-A', [$this->a]);
        $this->recipe($ambiguous, true, [[$this->milk, 1179]]);
        $this->recipe($ambiguous, true, [[$this->milk, 46084]]);
        $this->recipe($this->regular, true, [[$this->milk, 46145], [$this->sugar, 46154]]);
        $this->balance($this->milk, $this->a, -3);
        $this->promo('Ube Deal', [$this->regular], [$this->a]);
        $recipes = $this->recipeSnapshot();
        $data = $this->dataSnapshot();

        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|create)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        foreach (['general', 'outlets', 'variants', 'recipe', 'stock', 'promo'] as $section) {
            $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => $section]))->assertOk();
        }
        $this->get(route('backoffice.stock-balances.index', ['ingredient_id' => $this->milk->id]))->assertOk();
        $this->get(route('backoffice.stock-movements.index', ['ingredient_id' => $this->milk->id]))->assertOk();
        $this->get(route('backoffice.promos.create', ['variant_id' => $this->regular->id]))->assertOk();

        $this->assertSame([], $writes);
        $this->assertSame($recipes, $this->recipeSnapshot(), 'Recipe rows incl. qty and updated_at');
        $this->assertSame($data, $this->dataSnapshot());
        $stored = array_map('floatval', array_column($recipes['items'], 'qty'));
        foreach ([46154.0, 1179.0, 46084.0, 46145.0] as $quantity) {
            $this->assertContains($quantity, $stored);
        }
    }

    public function test_eligibility_semantics_are_unchanged_by_stock(): void
    {
        $this->regular->outlets()->sync([$this->a->id]);
        $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $service = app(SaleEligibilityService::class);

        // No balance row, negative balance: the Cashier rules still accept the sale.
        $this->assertSame([$this->milk->id => 150.0], $service->requirementsForCart([['variant_id' => $this->regular->id, 'qty' => 1]], $this->a->id));
        $this->balance($this->milk, $this->a, -1000);
        $this->assertSame([$this->milk->id => 300.0], $service->requirementsForCart([['variant_id' => $this->regular->id, 'qty' => 2]], $this->a->id));
        $this->stock();   // viewing the workspace changed none of it
        $this->assertSame(-1000.0, (float) StockBalance::first()->qty_on_hand);
    }

    // ---- helpers --------------------------------------------------------------------------------------

    private function stock(): string
    {
        return $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock']))->assertOk()->getContent();
    }

    private function promoPage(): string
    {
        return $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'promo']))->assertOk()->getContent();
    }

    private function panel(string $html, string $section): string
    {
        $from = strpos($html, 'id="pw-section-'.$section.'"');
        $this->assertNotFalse($from, 'panel '.$section);
        $to = strpos($html, '</section>', $from);

        return substr($html, $from, $to - $from);
    }

    private function variantCard(string $html, ProductVariant $variant): string
    {
        return $this->between($html, 'data-pw-stock-variant="'.$variant->id.'"', 'data-pw-stock-variant="', true);
    }

    private function promoCard(string $html, Promo $promo): string
    {
        return $this->between($html, 'data-pw-promo="'.$promo->id.'"', 'data-pw-promo="', true);
    }

    private function rowState(string $card, Ingredient $ingredient): string
    {
        preg_match('/data-pw-stock-row="'.$ingredient->id.'" data-pw-stock-state="([a-z]+)"/', $card, $match);
        $this->assertNotEmpty($match, 'row for '.$ingredient->name);

        return $match[1];
    }

    /** Text from $start up to the next $end (or the end of the string when there is none). */
    private function between(string $html, string $start, string $end, bool $toEndIfMissing = false): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from, $start);
        $to = strpos($html, $end, $from + strlen($start));

        if ($to === false) {
            $this->assertTrue($toEndIfMissing, $end);

            return substr($html, $from);
        }

        return substr($html, $from, $to - $from);
    }

    private function recipeSnapshot(): array
    {
        return [
            'recipes' => DB::table('recipes')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'items' => DB::table('recipe_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    private function promoSnapshot(): array
    {
        return [
            DB::table('promos')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('promo_outlet')->orderBy('promo_id')->orderBy('outlet_id')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('promo_requirements')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('promo_rewards')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    private function dataSnapshot(): array
    {
        return [
            'stock_balances' => DB::table('stock_balances')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'stock_movements' => DB::table('stock_movements')->count(),
            'products' => DB::table('products')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'variants' => DB::table('product_variants')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'ingredients' => DB::table('ingredients')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'outlet_pivots' => [
                DB::table('product_outlet')->orderBy('product_id')->orderBy('outlet_id')->get()->map(fn ($row) => (array) $row)->all(),
                DB::table('product_variant_outlet')->orderBy('product_variant_id')->orderBy('outlet_id')->get()->map(fn ($row) => (array) $row)->all(),
            ],
            'promos' => $this->promoSnapshot(),
        ];
    }

    private function variant(Product $product, string $name, string $code, array $outlets): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'name' => $name, 'code' => $code,
            'price' => 20000, 'price_dine_in' => 20000, 'price_delivery' => 22000, 'is_active' => true,
        ]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    private function foreignVariant(): ProductVariant
    {
        $other = Product::create(['brand_id' => $this->product->brand_id, 'product_category_id' => $this->product->product_category_id, 'name' => 'Matcha', 'code' => 'MAT', 'is_active' => true]);
        $other->outlets()->sync([$this->a->id, $this->b->id]);

        return $this->variant($other, 'Regular', 'MAT-R', [$this->a, $this->b]);
    }

    private function ingredient(string $name, string $unit, array $outlets, float $minimum = 0, bool $active = true): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->dairy->id, 'name' => $name, 'code' => strtoupper(str_replace(' ', '_', $name)), 'unit' => $unit,
            'ingredient_type' => 'raw', 'minimum_stock' => $minimum, 'cost_per_unit' => 10, 'is_active' => $active,
        ]);
        $ingredient->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $ingredient;
    }

    /** @param  array<int, array{0: Ingredient, 1: float|int, 2?: string}>  $items */
    private function recipe(ProductVariant $variant, bool $active, array $items): Recipe
    {
        $recipe = Recipe::create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'name' => 'Recipe '.$variant->name.' '.uniqid(), 'is_active' => $active]);

        foreach ($items as $item) {
            RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $item[0]->id, 'qty' => $item[1], 'unit' => $item[2] ?? $item[0]->unit]);
        }

        return $recipe;
    }

    private function recipeOf(ProductVariant $variant): Recipe
    {
        return Recipe::where('product_variant_id', $variant->id)->firstOrFail();
    }

    private function balance(Ingredient $ingredient, Outlet $outlet, float $qty): void
    {
        StockBalance::create(['ingredient_id' => $ingredient->id, 'location_type' => 'outlet', 'location_id' => $outlet->id, 'qty_on_hand' => $qty]);
    }

    /**
     * @param  ProductVariant[]  $requirementVariants
     * @param  Outlet[]  $outlets
     * @param  array<int, array>  $rewards  [type, value] or ['free_item', 0, Variant, qty]
     */
    private function promo(string $name, array $requirementVariants, array $outlets, array $attributes = [], array $rewards = []): Promo
    {
        $promo = Promo::create(array_merge(['name' => $name, 'requirement_logic' => 'and', 'status' => 'active', 'is_active' => true, 'active_days' => []], $attributes));

        foreach ($requirementVariants as $variant) {
            PromoRequirement::create(['promo_id' => $promo->id, 'product_variant_id' => $variant->id, 'qty' => 1]);
        }

        foreach ($rewards as $reward) {
            PromoReward::create([
                'promo_id' => $promo->id, 'reward_type' => $reward[0], 'reward_value' => $reward[1],
                'product_variant_id' => ($reward[2] ?? null)?->id, 'qty' => $reward[3] ?? 1,
            ]);
        }

        $promo->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $promo;
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
