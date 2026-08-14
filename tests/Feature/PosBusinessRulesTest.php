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
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\SaleEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PosBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outletA;

    private Outlet $outletB;

    private Brand $brand;

    private ProductCategory $productCategory;

    private IngredientCategory $ingredientCategory;

    private Product $product;

    private ProductVariant $variant;

    private Ingredient $ingredient;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outletA = Outlet::create(['name' => 'Outlet A', 'code' => 'OA', 'is_active' => true]);
        $this->outletB = Outlet::create(['name' => 'Outlet B', 'code' => 'OB', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->productCategory = ProductCategory::create([
            'brand_id' => $this->brand->id,
            'name' => 'Drinks',
            'code' => 'DRINK',
            'is_active' => true,
        ]);
        $this->ingredientCategory = IngredientCategory::create([
            'brand_id' => $this->brand->id,
            'name' => 'Liquid',
            'code' => 'LIQUID',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->productCategory->id,
            'name' => 'Matcha Kafei',
            'code' => 'MATCHA',
            'is_active' => true,
        ]);
        $this->product->outlets()->sync([$this->outletA->id]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => '1L',
            'code' => 'MATCHA-1L',
            'price' => 50000,
            'price_dine_in' => 50000,
            'price_delivery' => 55000,
            'is_active' => true,
        ]);

        $this->ingredient = Ingredient::create([
            'ingredient_category_id' => $this->ingredientCategory->id,
            'name' => 'Fresh Milk',
            'code' => 'FRESH-MILK',
            'unit' => 'ml',
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => true,
        ]);
        $this->ingredient->outlets()->sync([$this->outletA->id]);

        $role = Role::create(['name' => 'Owner', 'code' => 'owner']);
        $this->user = User::create([
            'name' => 'POS Owner',
            'username' => 'pos-owner',
            'email' => 'owner@example.test',
            'password' => 'password',
            'role_id' => $role->id,
            'outlet_id' => $this->outletA->id,
            'is_active' => true,
        ]);
        $this->user->outlets()->sync([$this->outletA->id, $this->outletB->id]);

        CashierShift::create([
            'user_id' => $this->user->id,
            'outlet_id' => $this->outletA->id,
            'started_at' => now(),
            'opening_cash' => 0,
            'status' => 'open',
        ]);
    }

    public function test_product_without_recipe_cannot_checkout_and_creates_no_transaction(): void
    {
        $response = $this->checkout($this->outletA, 1);

        $response->assertRedirect(route('cashier.index'));
        $response->assertSessionHas('error', fn ($message) => str_contains($message, 'belum memiliki recipe'));
        $this->assertDatabaseCount('sales_transactions', 0);
    }

    public function test_inactive_recipe_is_rejected(): void
    {
        $this->makeRecipe(false, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum memiliki recipe aktif');

        app(SaleEligibilityService::class)->requirementsForCart($this->cart(1), $this->outletA->id);
    }

    public function test_inactive_product_is_rejected(): void
    {
        $this->makeRecipe();
        $this->product->update(['is_active' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak aktif');

        app(SaleEligibilityService::class)->requirementsForCart($this->cart(1), $this->outletA->id);
    }

    public function test_inactive_variant_is_rejected(): void
    {
        $this->makeRecipe();
        $this->variant->update(['is_active' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Variant “Matcha Kafei - 1L” tidak aktif');

        app(SaleEligibilityService::class)->requirementsForCart($this->cart(1), $this->outletA->id);
    }

    public function test_inactive_ingredient_is_rejected(): void
    {
        $this->makeRecipe();
        $this->ingredient->update(['is_active' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ingredient “Fresh Milk” tidak aktif');

        app(SaleEligibilityService::class)->requirementsForCart($this->cart(1), $this->outletA->id);
    }

    public function test_active_recipe_without_items_is_rejected(): void
    {
        $this->makeRecipe(true, false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum memiliki bahan');

        app(SaleEligibilityService::class)->requirementsForCart($this->cart(1), $this->outletA->id);
    }

    public function test_ingredient_unavailable_at_transaction_outlet_is_rejected(): void
    {
        $this->makeRecipe();
        $this->ingredient->outlets()->sync([$this->outletB->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum tersedia untuk Outlet A');

        app(SaleEligibilityService::class)->requirementsForCart($this->cart(1), $this->outletA->id);
    }

    public function test_product_unavailable_at_transaction_outlet_is_rejected(): void
    {
        $this->makeRecipe();
        $this->product->outlets()->sync([$this->outletB->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Produk “Matcha Kafei - 1L” tidak tersedia untuk Outlet A');

        app(SaleEligibilityService::class)->requirementsForCart($this->cart(1), $this->outletA->id);
    }

    public function test_variant_unavailable_at_transaction_outlet_is_rejected(): void
    {
        $this->makeRecipe();
        $this->product->outlets()->sync([$this->outletA->id, $this->outletB->id]);
        $this->variant->outlets()->sync([$this->outletB->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Variant “Matcha Kafei - 1L” tidak tersedia untuk Outlet A');

        app(SaleEligibilityService::class)->requirementsForCart($this->cart(1), $this->outletA->id);
    }

    public function test_valid_checkout_deducts_stock_at_correct_outlet_with_quantity_multiplier(): void
    {
        $this->makeRecipe(true, true, 100);
        StockBalance::create([
            'ingredient_id' => $this->ingredient->id,
            'location_type' => 'outlet',
            'location_id' => $this->outletA->id,
            'qty_on_hand' => 1000,
        ]);
        StockBalance::create([
            'ingredient_id' => $this->ingredient->id,
            'location_type' => 'outlet',
            'location_id' => $this->outletB->id,
            'qty_on_hand' => 800,
        ]);

        $response = $this->checkout($this->outletA, 3);

        $response->assertRedirect(route('cashier.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('sales_transactions', ['status' => 'completed', 'outlet_id' => $this->outletA->id]);
        $this->assertDatabaseHas('stock_balances', [
            'ingredient_id' => $this->ingredient->id,
            'location_type' => 'outlet',
            'location_id' => $this->outletA->id,
            'qty_on_hand' => 700,
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'ingredient_id' => $this->ingredient->id,
            'location_type' => 'outlet',
            'location_id' => $this->outletB->id,
            'qty_on_hand' => 800,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'ingredient_id' => $this->ingredient->id,
            'location_type' => 'outlet',
            'location_id' => $this->outletA->id,
            'movement_type' => 'sales_usage',
            'qty_out' => 300,
        ]);
    }

    public function test_insufficient_stock_rolls_back_completed_sale(): void
    {
        $this->makeRecipe(true, true, 100);
        StockBalance::create([
            'ingredient_id' => $this->ingredient->id,
            'location_type' => 'outlet',
            'location_id' => $this->outletA->id,
            'qty_on_hand' => 50,
        ]);

        $response = $this->checkout($this->outletA, 1);

        $response->assertSessionHas('error', fn ($message) => str_contains($message, 'tidak cukup'));
        $this->assertDatabaseCount('sales_transactions', 0);
        $this->assertDatabaseCount('sales_transaction_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_free_item_still_deducts_recipe_stock(): void
    {
        $this->makeRecipe(true, true, 25);
        StockBalance::create([
            'ingredient_id' => $this->ingredient->id,
            'location_type' => 'outlet',
            'location_id' => $this->outletA->id,
            'qty_on_hand' => 100,
        ]);

        $freeCart = $this->cart(2);
        $cartKey = array_key_first($freeCart);
        $freeCart[$cartKey]['price'] = 0;
        $freeCart[$cartKey]['line_total'] = 0;
        $freeCart[$cartKey]['is_promo_reward'] = true;

        $this->actingAs($this->user)
            ->withSession([
                'auth_portal' => 'cashier',
                'cashier_outlet_id' => $this->outletA->id,
                'cashier_order_type' => 'dine_in',
                'cashier_cart' => $freeCart,
            ])
            ->post(route('cashier.checkout'), [
                'payment_method' => 'qris',
                'amount_paid' => 0,
                'order_type' => 'dine_in',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('stock_balances', [
            'ingredient_id' => $this->ingredient->id,
            'location_id' => $this->outletA->id,
            'qty_on_hand' => 50,
        ]);
    }

    public function test_void_restores_exact_recorded_deduction_even_after_recipe_is_inactivated(): void
    {
        $recipe = $this->makeRecipe(true, true, 100);
        StockBalance::create([
            'ingredient_id' => $this->ingredient->id,
            'location_type' => 'outlet',
            'location_id' => $this->outletA->id,
            'qty_on_hand' => 500,
        ]);
        $this->checkout($this->outletA, 2)->assertSessionHas('success');
        $transaction = SalesTransaction::firstOrFail();

        $recipe->update(['is_active' => false]);
        $recipe->items()->firstOrFail()->update(['qty' => 999]);

        $this->actingAs($this->user)
            ->post(route('backoffice.transactions.void', $transaction), ['void_reason' => 'Regression test'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('sales_transactions', ['id' => $transaction->id, 'status' => 'void']);
        $this->assertDatabaseHas('stock_balances', [
            'ingredient_id' => $this->ingredient->id,
            'location_id' => $this->outletA->id,
            'qty_on_hand' => 500,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'reference_id' => $transaction->id,
            'movement_type' => 'sales_void_restore',
            'qty_in' => 200,
        ]);
    }

    public function test_product_outlet_relations_persist_across_add_and_remove_updates(): void
    {
        $this->actingAs($this->user);

        $this->put(route('backoffice.products.update', $this->product), $this->productPayload([$this->outletA->id, $this->outletB->id]))
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$this->outletA->id, $this->outletB->id], $this->product->fresh()->outlets()->pluck('outlets.id')->all());

        $this->put(route('backoffice.products.update', $this->product), $this->productPayload([$this->outletB->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame([$this->outletB->id], $this->product->fresh()->outlets()->pluck('outlets.id')->all());
    }

    public function test_ingredient_outlet_relations_persist_across_add_and_remove_updates(): void
    {
        $this->actingAs($this->user);

        $this->put(route('backoffice.ingredients.update', $this->ingredient), $this->ingredientPayload([$this->outletA->id, $this->outletB->id]))
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$this->outletA->id, $this->outletB->id], $this->ingredient->fresh()->outlets()->pluck('outlets.id')->all());

        $this->put(route('backoffice.ingredients.update', $this->ingredient), $this->ingredientPayload([$this->outletB->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame([$this->outletB->id], $this->ingredient->fresh()->outlets()->pluck('outlets.id')->all());
    }

    public function test_recipe_active_inactive_persists_and_inline_qty_update_keeps_same_item(): void
    {
        $recipe = $this->makeRecipe();
        $item = $recipe->items()->firstOrFail();
        $this->actingAs($this->user);

        $this->put(route('backoffice.recipes.update', $recipe), [
            'product_variant_id' => $this->variant->id,
            'name' => $recipe->name,
            'is_active' => 0,
        ])->assertRedirect(route('backoffice.recipes.index'));
        $this->assertFalse($recipe->fresh()->is_active);

        $this->put(route('backoffice.recipes.update', $recipe), [
            'product_variant_id' => $this->variant->id,
            'name' => $recipe->name,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($recipe->fresh()->is_active);

        $this->put(route('backoffice.recipes.items.update', [$recipe, $item]), ['qty' => 125])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->ingredient->id, $item->fresh()->ingredient_id);
        $this->assertSame(125.0, (float) $item->fresh()->qty);
        $this->assertSame(1, $recipe->items()->count());
    }

    public function test_cashier_catalog_respects_parent_product_outlet(): void
    {
        $this->actingAs($this->user);

        $atA = $this->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->outletA->id])
            ->get(route('cashier.index'));
        $this->assertTrue($atA->viewData('products')->contains('id', $this->product->id));

        $atB = $this->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->outletB->id])
            ->get(route('cashier.index'));
        $this->assertFalse($atB->viewData('products')->contains('id', $this->product->id));
    }

    public function test_variant_outlet_must_be_subset_of_product_outlets(): void
    {
        $this->actingAs($this->user)
            ->post(route('backoffice.variants.store'), [
                'product_id' => $this->product->id,
                'variants' => [[
                    'name' => 'Invalid Outlet Variant',
                    'code' => 'INVALID-OUTLET',
                    'outlet_ids' => [$this->outletB->id],
                    'price_dine_in' => 10000,
                    'price_delivery' => 12000,
                    'is_active' => 1,
                ]],
            ])
            ->assertSessionHasErrors('variants.0.outlet_ids');

        $this->assertDatabaseMissing('product_variants', ['code' => 'INVALID-OUTLET']);
    }

    public function test_removing_parent_outlet_cleans_child_scope_and_deactivates_orphaned_variant(): void
    {
        $this->product->outlets()->sync([$this->outletA->id, $this->outletB->id]);
        $this->variant->outlets()->sync([$this->outletB->id]);

        $this->actingAs($this->user)
            ->put(route('backoffice.products.update', $this->product), $this->productPayload([$this->outletA->id]))
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $this->variant->fresh()->outlets);
        $this->assertFalse($this->variant->fresh()->is_active);
    }

    public function test_recipe_search_uses_recipe_product_variant_and_code_but_not_ingredient_name(): void
    {
        $recipe = $this->makeRecipe();
        $this->actingAs($this->user);

        $ingredientOnlySearch = $this->get(route('backoffice.recipes.index', ['search' => 'Fresh Milk']));
        $this->assertCount(0, $ingredientOnlySearch->viewData('recipes'));

        $variantCodeSearch = $this->get(route('backoffice.recipes.index', ['search' => 'MATCHA-1L']));
        $this->assertTrue($variantCodeSearch->viewData('recipes')->contains('id', $recipe->id));
    }

    private function makeRecipe(bool $active = true, bool $withItem = true, float $qty = 100): Recipe
    {
        $recipe = Recipe::create([
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'name' => 'Recipe Matcha 1L',
            'is_active' => $active,
        ]);

        if ($withItem) {
            RecipeItem::create([
                'recipe_id' => $recipe->id,
                'ingredient_id' => $this->ingredient->id,
                'qty' => $qty,
                'unit' => $this->ingredient->unit,
            ]);
        }

        return $recipe;
    }

    private function cart(float $qty): array
    {
        return ['variant_'.$this->variant->id.'_dine_in' => [
            'cart_key' => 'variant_'.$this->variant->id.'_dine_in',
            'variant_id' => $this->variant->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'variant_name' => $this->variant->name,
            'order_type' => 'dine_in',
            'qty' => $qty,
            'price' => 50000,
            'line_total' => 50000 * $qty,
        ]];
    }

    private function checkout(Outlet $outlet, float $qty)
    {
        return $this->actingAs($this->user)
            ->withSession([
                'auth_portal' => 'cashier',
                'cashier_outlet_id' => $outlet->id,
                'cashier_order_type' => 'dine_in',
                'cashier_cart' => $this->cart($qty),
            ])
            ->post(route('cashier.checkout'), [
                'payment_method' => 'cash',
                'amount_paid' => 50000 * $qty,
                'order_type' => 'dine_in',
            ]);
    }

    private function productPayload(array $outletIds): array
    {
        return [
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->productCategory->id,
            'name' => $this->product->name,
            'code' => $this->product->code,
            'description' => null,
            'is_active' => 1,
            'outlet_ids' => $outletIds,
        ];
    }

    private function ingredientPayload(array $outletIds): array
    {
        return [
            'ingredient_category_id' => $this->ingredientCategory->id,
            'name' => $this->ingredient->name,
            'unit' => $this->ingredient->unit,
            'ingredient_type' => $this->ingredient->ingredient_type,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => 1,
            'outlet_ids' => $outletIds,
        ];
    }
}
