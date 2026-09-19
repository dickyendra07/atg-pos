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
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRevisionTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $bxc;

    private Outlet $bazaar;

    private Brand $brand;

    private ProductCategory $menuCategory;

    private IngredientCategory $ingredientCategory;

    private Product $product;

    private ProductVariant $variant;

    private Ingredient $air;

    private Ingredient $boba;

    private User $owner;

    private User $bxcCashier;

    private User $bazaarCashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bxc = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $this->bazaar = Outlet::create(['name' => 'Bazaar', 'code' => 'BZR', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->menuCategory = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Astral', 'code' => 'ASTRAL', 'is_active' => true]);
        $this->ingredientCategory = IngredientCategory::create(['name' => 'Powder', 'code' => 'POWDER', 'is_active' => true]);

        $this->product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->menuCategory->id,
            'name' => 'Astral Latte',
            'code' => 'ASTRAL-LATTE',
            'is_active' => true,
        ]);
        $this->product->outlets()->sync([$this->bxc->id, $this->bazaar->id]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Regular',
            'code' => 'ASTRAL-REG',
            'price' => 30000,
            'price_dine_in' => 30000,
            'price_delivery' => 33000,
            'is_active' => true,
        ]);
        $this->variant->outlets()->sync([$this->bxc->id, $this->bazaar->id]);

        $this->air = $this->makeIngredient('Air Mineral', 'ml');
        $this->boba = $this->makeIngredient('Boba', 'gram');

        $owner = Role::create(['name' => 'Owner', 'code' => 'owner']);
        $cashierRole = Role::create(['name' => 'Kasir', 'code' => 'kasir']);

        $this->owner = $this->makeUser('owner', $owner, $this->bxc, [$this->bxc, $this->bazaar]);
        $this->bxcCashier = $this->makeUser('cashier-bxc', $cashierRole, $this->bxc, [$this->bxc]);
        $this->bazaarCashier = $this->makeUser('cashier-bzr', $cashierRole, $this->bazaar, [$this->bazaar]);

        foreach ([$this->bxcCashier, $this->bazaarCashier] as $cashier) {
            CashierShift::create([
                'user_id' => $cashier->id,
                'outlet_id' => $cashier->outlet_id,
                'started_at' => now(),
                'opening_cash' => 0,
                'status' => 'open',
            ]);
        }
    }

    // ---- Ingredient Category ------------------------------------------------------------

    public function test_ingredient_category_crud_search_counts_and_duplicate_normalization(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backoffice.ingredient-categories.store'), ['name' => '  QA   Powder ', 'is_active' => 1])
            ->assertRedirect(route('backoffice.ingredient-categories.index'));

        $category = IngredientCategory::where('name', 'QA Powder')->firstOrFail();

        foreach (['qa powder', ' QA POWDER  ', 'QA  powder'] as $dup) {
            $this->post(route('backoffice.ingredient-categories.store'), ['name' => $dup, 'is_active' => 1])
                ->assertSessionHasErrors('name');
        }
        $this->assertSame(1, IngredientCategory::whereRaw('LOWER(name) = ?', ['qa powder'])->count());

        $this->post(route('backoffice.ingredients.store'), $this->ingredientPayload(['ingredient_category_id' => $category->id, 'name' => 'Matcha QA', 'unit' => 'gram']))
            ->assertSessionHasNoErrors();

        $this->get(route('backoffice.ingredient-categories.index', ['search' => 'QA Pow']))
            ->assertOk()->assertSee('QA Powder')->assertDontSee('Air Mineral');

        $this->get(route('backoffice.ingredient-categories.edit', $category->id))->assertOk();
        $this->put(route('backoffice.ingredient-categories.update', $category->id), ['name' => 'QA Powder', 'is_active' => 0])
            ->assertSessionHasNoErrors();
        $this->assertFalse($category->fresh()->is_active);

        // Inactive category is no longer offered for new ingredients.
        $this->post(route('backoffice.ingredients.store'), $this->ingredientPayload(['ingredient_category_id' => $category->id, 'name' => 'Another']))
            ->assertSessionHasErrors('ingredient_category_id');
    }

    public function test_ingredient_and_menu_categories_are_separate_domains(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backoffice.menu-categories.store'), ['name' => 'QA Astral', 'brand_id' => $this->brand->id, 'is_active' => 1])
            ->assertSessionHasNoErrors();

        $this->get(route('backoffice.ingredients.create'))->assertOk()->assertSee('Powder')->assertDontSee('QA Astral');
        $this->get(route('backoffice.products.create'))->assertOk()->assertSee('QA Astral')->assertDontSee('>Powder<', false);

        $this->get(route('backoffice.menu-categories.index'))->assertOk()->assertSee('QA Astral')->assertSee('Astral');
    }

    // ---- Unit ---------------------------------------------------------------------------

    public function test_unit_is_select_and_forged_units_are_rejected_by_backend(): void
    {
        $this->actingAs($this->owner);

        $this->get(route('backoffice.ingredients.create'))->assertOk()->assertSee('<select name="unit"', false);

        $this->post(route('backoffice.ingredients.store'), $this->ingredientPayload(['name' => 'Forged', 'unit' => 'karung']))
            ->assertSessionHasErrors('unit');
        $this->assertDatabaseMissing('ingredients', ['name' => 'Forged']);

        $this->put(route('backoffice.ingredients.update', $this->air), $this->ingredientPayload(['name' => 'Air Mineral', 'unit' => 'liter']))
            ->assertSessionHasErrors('unit');

        $this->put(route('backoffice.ingredients.update', $this->air), $this->ingredientPayload(['name' => 'Air Mineral', 'unit' => 'ml']))
            ->assertSessionHasNoErrors();
        $this->assertSame('ml', $this->air->fresh()->unit);
    }

    public function test_legacy_unit_stays_selected_on_edit_and_can_be_kept_but_not_newly_typed(): void
    {
        $this->actingAs($this->owner);
        $legacy = $this->makeIngredient('Legacy Cup', 'sachet');

        $this->get(route('backoffice.ingredients.edit', $legacy))
            ->assertOk()
            ->assertSee('<option value="sachet" selected>sachet</option>', false);

        $this->put(route('backoffice.ingredients.update', $legacy), $this->ingredientPayload(['name' => 'Legacy Cup', 'unit' => 'sachet']))
            ->assertSessionHasNoErrors();
        $this->put(route('backoffice.ingredients.update', $legacy), $this->ingredientPayload(['name' => 'Legacy Cup', 'unit' => 'box']))
            ->assertSessionHasErrors('unit');
    }

    // ---- Adjustment ---------------------------------------------------------------------

    public function test_multi_item_adjustment_uses_one_reference_one_note_and_records_movements(): void
    {
        $this->seedStock($this->air, $this->bxc, 100);
        $this->seedStock($this->boba, $this->bxc, 200);

        $this->actingAs($this->owner)
            ->withSession(['active_backoffice_outlet_id' => $this->bxc->id])
            ->post(route('backoffice.stock-balances.adjustment.store'), [
                'location_type' => 'outlet',
                'location_id' => $this->bxc->id,
                'note' => 'Koreksi stock fisik closing',
                'items' => [
                    ['ingredient_id' => $this->air->id, 'actual_qty' => 55],
                    ['ingredient_id' => $this->boba->id, 'actual_qty' => 140],
                ],
            ])
            ->assertSessionHasNoErrors();

        $adjustment = StockAdjustment::with('items')->firstOrFail();
        $this->assertSame(1, StockAdjustment::count());
        $this->assertMatchesRegularExpression('/^ADJ-\d{8}-0001$/', $adjustment->reference);
        $this->assertSame('Koreksi stock fisik closing', $adjustment->note);
        $this->assertSame($this->owner->id, $adjustment->user_id);
        $this->assertSame(55.0, $this->balance($this->air, $this->bxc));
        $this->assertSame(140.0, $this->balance($this->boba, $this->bxc));

        $airItem = $adjustment->items->firstWhere('ingredient_id', $this->air->id);
        $this->assertEquals([100.0, 55.0, -45.0], [$airItem->system_qty, $airItem->actual_qty, $airItem->difference]);
        $this->assertSame('ml', $airItem->unit);
        $bobaItem = $adjustment->items->firstWhere('ingredient_id', $this->boba->id);
        $this->assertEquals([200.0, 140.0, -60.0], [$bobaItem->system_qty, $bobaItem->actual_qty, $bobaItem->difference]);

        $this->assertSame(2, StockMovement::where('movement_type', 'stock_adjustment')->count());
        $movement = StockMovement::findOrFail($airItem->stock_movement_id);
        $this->assertEquals(45.0, (float) $movement->qty_out);
        $this->assertEquals(0.0, (float) $movement->qty_in);
        $this->assertSame($adjustment->id, (int) $movement->reference_id);
    }

    public function test_adjustment_note_is_optional_and_positive_difference_adds_stock(): void
    {
        $this->seedStock($this->air, $this->bxc, 100);

        $this->actingAs($this->owner)
            ->post(route('backoffice.stock-balances.adjustment.store'), [
                'location_type' => 'outlet',
                'location_id' => $this->bxc->id,
                'items' => [['ingredient_id' => $this->air->id, 'actual_qty' => 130]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(StockAdjustment::firstOrFail()->note);
        $this->assertSame(130.0, $this->balance($this->air, $this->bxc));
        $movement = StockMovement::where('movement_type', 'stock_adjustment')->firstOrFail();
        $this->assertEquals(30.0, (float) $movement->qty_in);
    }

    public function test_adjustment_rejects_ingredient_not_available_at_outlet_and_duplicates(): void
    {
        $this->air->outlets()->sync([$this->bxc->id]);
        $this->actingAs($this->owner);

        $this->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'outlet',
            'location_id' => $this->bazaar->id,
            'items' => [['ingredient_id' => $this->air->id, 'actual_qty' => 5]],
        ])->assertSessionHasErrors('items');

        $this->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'outlet',
            'location_id' => $this->bxc->id,
            'items' => [
                ['ingredient_id' => $this->air->id, 'actual_qty' => 5],
                ['ingredient_id' => $this->air->id, 'actual_qty' => 6],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, StockAdjustment::count());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_adjustment_history_is_outlet_aware_filterable_and_shows_detail(): void
    {
        $this->seedStock($this->air, $this->bxc, 100);
        $this->seedStock($this->air, $this->bazaar, 10);
        $this->air->outlets()->sync([$this->bxc->id, $this->bazaar->id]);

        $this->actingAs($this->owner);
        $this->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'outlet', 'location_id' => $this->bxc->id, 'note' => 'Catatan BXC',
            'items' => [['ingredient_id' => $this->air->id, 'actual_qty' => 55]],
        ]);
        $this->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'outlet', 'location_id' => $this->bazaar->id, 'note' => 'Catatan Bazaar',
            'items' => [['ingredient_id' => $this->air->id, 'actual_qty' => 20]],
        ]);
        $bxc = StockAdjustment::where('location_id', $this->bxc->id)->firstOrFail();

        $this->withSession(['active_backoffice_outlet_id' => $this->bxc->id])
            ->get(route('backoffice.stock-adjustments.index'))
            ->assertOk()->assertSee('Catatan BXC')->assertDontSee('Catatan Bazaar');

        $this->withSession(['active_backoffice_outlet_id' => $this->bazaar->id])
            ->get(route('backoffice.stock-adjustments.index'))
            ->assertOk()->assertSee('Catatan Bazaar')->assertDontSee('Catatan BXC');

        $this->get(route('backoffice.stock-adjustments.index', ['outlet_id' => 'all', 'search' => $bxc->reference]))
            ->assertOk()->assertSee('Catatan BXC')->assertDontSee('Catatan Bazaar');

        $this->get(route('backoffice.stock-adjustments.index', ['outlet_id' => 'all', 'date_from' => now()->addDay()->toDateString()]))
            ->assertOk()->assertDontSee('Catatan BXC');

        $this->get(route('backoffice.stock-adjustments.show', $bxc))
            ->assertOk()->assertSee('Air Mineral')->assertSee('Powder')->assertSee('ml')
            ->assertSee('100,00')->assertSee('55,00')->assertSee('-45,00')->assertSee('MOV-')->assertSee('Catatan BXC');
    }

    public function test_adjustment_history_does_not_leak_outlets_user_cannot_access(): void
    {
        $adminRole = Role::create(['name' => 'Admin Outlet', 'code' => 'admin_outlet']);
        $admin = $this->makeUser('admin-bxc', $adminRole, $this->bxc, [$this->bxc]);
        $adjustment = StockAdjustment::create([
            'reference' => 'ADJ-20260101-0001', 'location_type' => 'outlet', 'location_id' => $this->bazaar->id, 'note' => 'Rahasia Bazaar',
        ]);

        $this->actingAs($admin)->get(route('backoffice.stock-adjustments.show', $adjustment))->assertForbidden();
        $this->get(route('backoffice.stock-adjustments.index', ['outlet_id' => $this->bazaar->id]))
            ->assertOk()->assertDontSee('Rahasia Bazaar');
    }

    public function test_all_stock_balances_section_is_hidden_but_stock_engine_still_works(): void
    {
        $this->seedStock($this->air, $this->bxc, 10);

        $this->actingAs($this->owner)->get(route('backoffice.stock-balances.index'))
            ->assertOk()->assertDontSee('All Stock Balances')->assertSee('Stock Summary');

        $this->assertSame(10.0, $this->balance($this->air, $this->bxc));
    }

    // ---- Menu category ------------------------------------------------------------------

    public function test_variant_and_product_category_filters_and_cashier_grouping_use_menu_category(): void
    {
        $other = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Waspffle', 'code' => 'WASP', 'is_active' => true]);
        $waffle = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $other->id, 'name' => 'Waffle Salty', 'code' => 'WS', 'is_active' => true]);
        $waffle->outlets()->sync([$this->bxc->id]);
        $waffleVariant = ProductVariant::create(['product_id' => $waffle->id, 'name' => 'Std', 'code' => 'WS-1', 'price' => 1, 'price_dine_in' => 1, 'price_delivery' => 1, 'is_active' => true]);
        $waffleVariant->outlets()->sync([$this->bxc->id]);

        $this->actingAs($this->owner)->withSession(['active_backoffice_outlet_id' => $this->bxc->id]);

        $this->get(route('backoffice.variants.index', ['category_id' => $this->menuCategory->id]))
            ->assertOk()->assertSee('Astral Latte')->assertDontSee('Waffle Salty');
        $this->get(route('backoffice.products.index', ['category_id' => $other->id]))
            ->assertOk()->assertSee('Waffle Salty')->assertDontSee('Astral Latte');

        // Outlet stays the availability authority: category does not expose Waffle at Bazaar.
        $this->withSession(['active_backoffice_outlet_id' => $this->bazaar->id])
            ->get(route('backoffice.variants.index', ['category_id' => $other->id]))
            ->assertOk()->assertDontSee('Waffle Salty');

        $this->actingAs($this->bazaarCashier)
            ->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->bazaar->id])
            ->get(route('cashier.index'))
            ->assertOk()->assertSee('Astral Latte')->assertDontSee('Waffle Salty');

        $this->actingAs($this->bxcCashier)
            ->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->bxc->id])
            ->get(route('cashier.index'))
            ->assertOk()->assertSee('Astral Latte')->assertSee('Waffle Salty');
    }

    // ---- Promo --------------------------------------------------------------------------

    public function test_promo_requires_outlet_and_valid_product_availability_per_outlet(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('backoffice.promos.store'), $this->promoPayload([]))->assertSessionHasErrors('outlet_ids');
        $this->assertSame(0, Promo::count());

        $this->post(route('backoffice.promos.store'), $this->promoPayload([$this->bxc->id], ['status' => 'draft']) + ['is_active' => 0])
            ->assertSessionHasNoErrors();

        $this->variant->outlets()->sync([$this->bxc->id]);
        $this->post(route('backoffice.promos.store'), $this->promoPayload([$this->bxc->id, $this->bazaar->id], ['name' => 'Both']))
            ->assertSessionHasErrors('outlet_ids');
        $this->assertDatabaseMissing('promos', ['name' => 'Both']);

        $this->post(route('backoffice.promos.store'), $this->promoPayload([$this->bxc->id], ['name' => 'BXC Only']))
            ->assertSessionHasNoErrors();
        $promo = Promo::where('name', 'BXC Only')->firstOrFail();
        $this->assertSame([$this->bxc->id], $promo->outlets()->pluck('outlets.id')->all());
    }

    public function test_promo_form_keeps_requirement_and_reward_rows_after_validation_failure(): void
    {
        $this->actingAs($this->owner);
        $payload = $this->promoPayload([], [
            'rewards' => [['reward_type' => 'free_item', 'reward_value' => 0, 'product_variant_id' => $this->variant->id, 'qty' => 2]],
        ]);

        $this->from(route('backoffice.promos.create'))
            ->post(route('backoffice.promos.store'), $payload)
            ->assertRedirect(route('backoffice.promos.create'))
            ->assertSessionHasErrors('outlet_ids');

        $this->get(route('backoffice.promos.create'))
            ->assertOk()
            ->assertSee('const oldRequirements = [{"product_variant_id":'.$this->variant->id, false)
            ->assertSee('"reward_type":"free_item"', false);
    }

    public function test_promo_index_defaults_to_active_outlet(): void
    {
        $this->makePromo('Promo A', [$this->bxc]);
        $this->makePromo('Promo B', [$this->bazaar]);
        $this->makePromo('Promo Legacy', []);

        $this->actingAs($this->owner)->withSession(['active_backoffice_outlet_id' => $this->bxc->id])
            ->get(route('backoffice.promos.index'))
            ->assertOk()->assertSee('Promo A')->assertDontSee('Promo B')->assertDontSee('Promo Legacy');

        $this->withSession(['active_backoffice_outlet_id' => $this->bazaar->id])
            ->get(route('backoffice.promos.index'))
            ->assertOk()->assertSee('Promo B')->assertDontSee('Promo A');

        $this->get(route('backoffice.promos.index', ['outlet_id' => 'unassigned']))->assertOk()->assertSee('Promo Legacy');
    }

    public function test_cashier_only_sees_and_uses_promos_of_own_outlet(): void
    {
        $a = $this->makePromo('Promo A', [$this->bxc]);
        $b = $this->makePromo('Promo B', [$this->bazaar]);
        $legacy = $this->makePromo('Promo Legacy', []);

        $this->actingAs($this->bxcCashier)->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->bxc->id])
            ->get(route('cashier.index'))
            ->assertOk()->assertSee('Promo A')->assertDontSee('Promo B')->assertDontSee('Promo Legacy');

        $this->actingAs($this->bazaarCashier)->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->bazaar->id])
            ->get(route('cashier.index'))
            ->assertOk()->assertSee('Promo B')->assertDontSee('Promo A');

        // Forged apply / checkout from the wrong outlet is rejected by the backend.
        $this->actingAs($this->bxcCashier)->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->bxc->id])
            ->postJson(route('cashier.promo.apply', $b))->assertStatus(422);
        $this->postJson(route('cashier.promo.apply', $legacy))->assertStatus(422);

        $this->seedStock($this->air, $this->bxc, 1000);
        $this->makeRecipeFor($this->variant, $this->air, 100);

        foreach ([$b, $legacy] as $forged) {
            $this->actingAs($this->bxcCashier)
                ->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->bxc->id, 'cashier_order_type' => 'dine_in', 'cashier_cart' => $this->cart()])
                ->post(route('cashier.checkout'), ['payment_method' => 'qris', 'amount_paid' => 0, 'order_type' => 'dine_in', 'promo_id' => $forged->id])
                ->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak berlaku untuk outlet'));
        }
        $this->assertDatabaseCount('sales_transactions', 0);
        $this->assertSame(1000.0, $this->balance($this->air, $this->bxc));

        $this->actingAs($this->bxcCashier)
            ->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->bxc->id, 'cashier_order_type' => 'dine_in', 'cashier_cart' => $this->cart()])
            ->post(route('cashier.checkout'), ['payment_method' => 'qris', 'amount_paid' => 0, 'order_type' => 'dine_in', 'promo_id' => $a->id])
            ->assertSessionHas('success');
        $this->assertDatabaseCount('sales_transactions', 1);
    }

    public function test_promo_outlet_filter_combines_with_schedule(): void
    {
        $this->makePromo('Future', [$this->bxc], ['start_date' => now()->addDays(2)->toDateString()]);
        $this->makePromo('Expired', [$this->bxc], ['end_date' => now()->subDays(2)->toDateString()]);
        $this->makePromo('Wrong Day', [$this->bxc], ['active_days' => [strtolower(now()->addDay()->format('l'))]]);
        $this->makePromo('Inactive', [$this->bxc], ['is_active' => false]);
        $this->makePromo('Draft', [$this->bxc], ['status' => 'draft']);
        $this->makePromo('Today Ok', [$this->bxc], ['active_days' => [strtolower(now()->format('l'))], 'start_time' => '00:00:00', 'end_time' => '23:59:59']);
        $this->makePromo('Bazaar Ok', [$this->bazaar]);

        $html = $this->actingAs($this->bxcCashier)->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->bxc->id])
            ->get(route('cashier.index'))->assertOk();

        $html->assertSee('Today Ok')->assertDontSee('Bazaar Ok');
        foreach (['Future', 'Expired', 'Wrong Day', 'Inactive', 'Draft'] as $hidden) {
            $html->assertDontSee('>'.$hidden.'<', false);
        }
    }

    public function test_free_item_reward_from_outlet_promo_still_deducts_outlet_stock(): void
    {
        $promo = $this->makePromo('Free Astral', [$this->bxc]);
        $this->seedStock($this->air, $this->bxc, 500);
        $this->makeRecipeFor($this->variant, $this->air, 100);

        $cart = $this->cart(2);
        $key = array_key_first($cart);
        $cart[$key]['price'] = 0;
        $cart[$key]['line_total'] = 0;
        $cart[$key]['is_promo_reward'] = true;
        $cart[$key]['promo_id'] = $promo->id;

        $this->actingAs($this->bxcCashier)
            ->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->bxc->id, 'cashier_order_type' => 'dine_in', 'cashier_cart' => $cart])
            ->post(route('cashier.checkout'), ['payment_method' => 'qris', 'amount_paid' => 0, 'order_type' => 'dine_in', 'promo_id' => $promo->id])
            ->assertSessionHas('success');

        $this->assertSame(300.0, $this->balance($this->air, $this->bxc));
        $this->assertDatabaseHas('stock_movements', ['ingredient_id' => $this->air->id, 'location_id' => $this->bxc->id, 'qty_out' => 200]);
    }

    // ---- helpers ------------------------------------------------------------------------

    private function makeIngredient(string $name, string $unit): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->ingredientCategory->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $name)),
            'unit' => $unit,
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => true,
        ]);
        $ingredient->outlets()->sync([$this->bxc->id, $this->bazaar->id]);

        return $ingredient;
    }

    private function makeUser(string $username, Role $role, Outlet $outlet, array $outlets): User
    {
        $user = User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'role_id' => $role->id,
            'outlet_id' => $outlet->id,
            'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }

    private function seedStock(Ingredient $ingredient, Outlet $outlet, float $qty): void
    {
        StockBalance::updateOrCreate(
            ['ingredient_id' => $ingredient->id, 'location_type' => 'outlet', 'location_id' => $outlet->id],
            ['qty_on_hand' => $qty]
        );
    }

    private function balance(Ingredient $ingredient, Outlet $outlet): float
    {
        return (float) StockBalance::where(['ingredient_id' => $ingredient->id, 'location_type' => 'outlet', 'location_id' => $outlet->id])->value('qty_on_hand');
    }

    private function makeRecipeFor(ProductVariant $variant, Ingredient $ingredient, float $qty): void
    {
        $recipe = Recipe::create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'name' => 'Recipe', 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'qty' => $qty, 'unit' => $ingredient->unit]);
    }

    private function cart(float $qty = 1): array
    {
        return ['variant_'.$this->variant->id.'_dine_in' => [
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'product_name' => $this->product->name,
            'variant_name' => $this->variant->name,
            'order_type' => 'dine_in',
            'qty' => $qty,
            'price' => 30000,
            'line_total' => 30000 * $qty,
        ]];
    }

    private function ingredientPayload(array $overrides = []): array
    {
        return array_merge([
            'ingredient_category_id' => $this->ingredientCategory->id,
            'name' => 'Air Mineral',
            'unit' => 'ml',
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => 1,
            'outlet_ids' => [$this->bxc->id, $this->bazaar->id],
        ], $overrides);
    }

    private function promoPayload(array $outletIds, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Promo QA',
            'requirement_logic' => 'and',
            'requirements' => [['product_variant_id' => $this->variant->id, 'qty' => 1]],
            'rewards' => [['reward_type' => 'discount_percent', 'reward_value' => 10]],
            'status' => 'active',
            'is_active' => 1,
            'outlet_ids' => $outletIds,
        ], $overrides);
    }

    private function makePromo(string $name, array $outlets, array $attributes = []): Promo
    {
        $promo = Promo::create(array_merge([
            'name' => $name,
            'requirement_logic' => 'and',
            'reward_type' => 'discount_percent',
            'reward_value' => 10,
            'status' => 'active',
            'is_active' => true,
        ], $attributes));
        $promo->outlets()->sync(collect($outlets)->pluck('id')->all());
        $promo->requirements()->create(['product_variant_id' => $this->variant->id, 'qty' => 1]);
        $promo->rewards()->create(['reward_type' => 'discount_percent', 'reward_value' => 10, 'qty' => 1]);

        return $promo;
    }
}
