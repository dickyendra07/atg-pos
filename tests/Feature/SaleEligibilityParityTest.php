<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\StockBalance;
use App\Services\SaleEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\LegacySaleEligibilityService;
use Tests\TestCase;

/**
 * Batch 3 moved the per-variant rules into checkVariant(). UX may change, business rules may not:
 * every condition below must give the SAME allow/reject, the SAME exception message and the SAME
 * requirements (ingredient ids, quantities, float sums, order) as the frozen pre-refactor service.
 */
class SaleEligibilityParityTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] printed after the run (set PARITY_TABLE=1) so the before/after table can be reviewed */
    private static array $truthTable = [];

    public static function tearDownAfterClass(): void
    {
        if (getenv('PARITY_TABLE')) {
            fwrite(STDERR, "\n".implode("\n", self::$truthTable)."\n");
        }

        parent::tearDownAfterClass();
    }

    private Outlet $outlet;

    private Outlet $other;

    private Product $product;

    private ProductVariant $variant;

    private Ingredient $milk;

    private Ingredient $sugar;

    private Recipe $recipe;

    private Brand $brand;

    private ProductCategory $category;

    private IngredientCategory $ingredientCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $this->other = Outlet::create(['name' => 'Bazaar', 'code' => 'BZR', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->ingredientCategory = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);

        $this->milk = $this->ingredient('Milk', 'ml');
        $this->sugar = $this->ingredient('Sugar', 'gram');
        [$this->product, $this->variant] = $this->productWithVariant('Kafei Susu', 'Regular', 'KS');
        $this->recipe = $this->recipeFor($this->variant, [[$this->milk, 150], [$this->sugar, 7.5]]);
    }

    public static function conditions(): array
    {
        return [
            'eligible' => ['eligible'],
            'product inactive' => ['product_inactive'],
            'product unavailable at outlet' => ['product_not_at_outlet'],
            'variant missing' => ['variant_missing'],
            'variant inactive' => ['variant_inactive'],
            'variant unavailable at outlet' => ['variant_not_at_outlet'],
            'recipe missing' => ['recipe_missing'],
            'recipe inactive' => ['recipe_inactive'],
            'multiple active recipes' => ['recipe_ambiguous'],
            'recipe/product mismatch' => ['recipe_product_mismatch'],
            'recipe empty' => ['recipe_empty'],
            'invalid (orphaned) ingredient' => ['ingredient_invalid'],
            'ingredient inactive' => ['ingredient_inactive'],
            'ingredient unavailable at outlet' => ['ingredient_not_at_outlet'],
            'ingredient qty zero' => ['ingredient_qty_zero'],
            'ingredient qty negative' => ['ingredient_qty_negative'],
            'stock balance missing' => ['stock_missing'],
            'stock balance zero' => ['stock_zero'],
            'stock balance negative' => ['stock_negative'],
            'inactive recipe plus active one (single active wins)' => ['recipe_one_inactive_one_active'],
        ];
    }

    #[DataProvider('conditions')]
    public function test_decision_message_and_requirements_are_identical_to_the_pre_refactor_service(string $condition): void
    {
        $variantId = $this->apply($condition);
        $cart = [['variant_id' => $variantId, 'qty' => 2]];

        $before = $this->outcome(new LegacySaleEligibilityService, $cart, $this->outlet->id);
        $after = $this->outcome(app(SaleEligibilityService::class), $cart, $this->outlet->id);

        self::$truthTable[] = sprintf('%-54s before=%-7s after=%-7s %s', $condition, $before['ok'] ? 'ALLOW' : 'REJECT', $after['ok'] ? 'ALLOW' : 'REJECT', $before === $after ? 'SAME' : 'DIFFERENT');
        $this->assertSame($before, $after, $condition);

        // The UI snapshot agrees with the validator for the same condition (it can only explain, not decide).
        $status = app(SaleEligibilityService::class)->variantStatuses([$variantId], $this->outlet->id)[$variantId] ?? null;
        $this->assertSame($before['ok'], $status['eligible'], $condition.': variantStatuses vs requirementsForCart');
    }

    public function test_cart_level_checks_are_identical(): void
    {
        $carts = [
            'empty cart' => [[], $this->outlet->id],
            'null outlet' => [[['variant_id' => $this->variant->id, 'qty' => 1]], null],
            'unknown outlet' => [[['variant_id' => $this->variant->id, 'qty' => 1]], 999999],
            'zero qty' => [[['variant_id' => $this->variant->id, 'qty' => 0]], $this->outlet->id],
            'negative qty' => [[['variant_id' => $this->variant->id, 'qty' => -1]], $this->outlet->id],
            'bad variant id' => [[['variant_id' => 0, 'qty' => 1]], $this->outlet->id],
            'other outlet' => [[['variant_id' => $this->variant->id, 'qty' => 1]], $this->other->id],
        ];

        foreach ($carts as $name => [$cart, $outletId]) {
            $this->assertSame(
                $this->outcome(new LegacySaleEligibilityService, $cart, $outletId),
                $this->outcome(app(SaleEligibilityService::class), $cart, $outletId),
                $name
            );
        }
    }

    public function test_requirements_for_a_multi_line_cart_are_bit_for_bit_the_same(): void
    {
        // shared ingredients across variants, the same ingredient twice inside one recipe, awkward floats,
        // the same variant on two cart lines, and a fractional sold qty.
        [, $large] = $this->productWithVariant('Kafei Susu Besar', 'Large', 'KSB');
        $this->recipeFor($large, [[$this->milk, 0.1], [$this->sugar, 0.2], [$this->milk, 0.3], [$this->milk, 33.333]]);

        [, $tea] = $this->productWithVariant('Teh', 'Regular', 'TEH');
        $this->recipeFor($tea, [[$this->sugar, 0.7], [$this->ingredient('Tea Leaf', 'gram'), 5.55]]);

        $cart = [
            ['variant_id' => $this->variant->id, 'qty' => 3],
            ['variant_id' => $large->id, 'qty' => 0.7],
            ['variant_id' => $tea->id, 'qty' => 1.1],
            ['variant_id' => $this->variant->id, 'qty' => 0.5],   // same variant again: grouped
            ['variant_id' => $large->id, 'qty' => 2],
        ];

        $before = (new LegacySaleEligibilityService)->requirementsForCart($cart, $this->outlet->id);
        $after = app(SaleEligibilityService::class)->requirementsForCart($cart, $this->outlet->id);

        $this->assertSame($before, $after);
        $this->assertSame(array_keys($before), array_keys($after), 'same ingredient order');
        $this->assertSame(array_map(fn ($v) => sprintf('%.17g', $v), $before), array_map(fn ($v) => sprintf('%.17g', $v), $after), 'identical to the last bit');

        // and what StockDeductionService is handed from a stored transaction
        $this->assertSame(
            (new LegacySaleEligibilityService)->requirementsForCart($cart, $this->outlet->id),
            app(SaleEligibilityService::class)->requirementsForCart($cart, $this->outlet->id)
        );
    }

    public function test_known_requirement_values_stay_exact(): void
    {
        $requirements = app(SaleEligibilityService::class)->requirementsForCart([['variant_id' => $this->variant->id, 'qty' => 2]], $this->outlet->id);

        $this->assertSame([$this->milk->id => 300.0, $this->sugar->id => 15.0], $requirements);
    }

    // ---- helpers ---------------------------------------------------------------------------------

    /** @return array{ok: bool, requirements?: array, message?: string} */
    private function outcome(object $service, array $cart, ?int $outletId): array
    {
        try {
            return ['ok' => true, 'requirements' => $service->requirementsForCart($cart, $outletId)];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** Put the fixture in the named state; returns the Variant ID to put in the cart. */
    private function apply(string $condition): int
    {
        switch ($condition) {
            case 'product_inactive': $this->product->update(['is_active' => false]);
                break;
            case 'product_not_at_outlet': $this->product->outlets()->sync([$this->other->id]);
                break;
            case 'variant_missing': return 987654;
            case 'variant_inactive': $this->variant->update(['is_active' => false]);
                break;
            case 'variant_not_at_outlet': $this->variant->outlets()->sync([$this->other->id]);
                break;
            case 'recipe_missing': Recipe::query()->delete();
                break;
            case 'recipe_inactive': $this->recipe->update(['is_active' => false]);
                break;
            case 'recipe_ambiguous': Recipe::create(['product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'name' => 'Second', 'is_active' => true]);
                break;
            case 'recipe_product_mismatch': $this->recipe->update(['product_id' => $this->productWithVariant('Lain', 'R', 'LN')[0]->id]);
                break;
            case 'recipe_empty': $this->recipe->items()->delete();
                break;
            case 'ingredient_invalid':
                // FK checks are deferred to commit (which never happens: the test transaction rolls back).
                DB::statement('PRAGMA defer_foreign_keys = ON');
                $this->recipe->items()->where('ingredient_id', $this->milk->id)->update(['ingredient_id' => 424242]);
                break;
            case 'ingredient_inactive': $this->milk->update(['is_active' => false]);
                break;
            case 'ingredient_not_at_outlet': $this->milk->outlets()->sync([$this->other->id]);
                break;
            case 'ingredient_qty_zero': $this->recipe->items()->where('ingredient_id', $this->milk->id)->update(['qty' => 0]);
                break;
            case 'ingredient_qty_negative': $this->recipe->items()->where('ingredient_id', $this->milk->id)->update(['qty' => -5]);
                break;
            case 'stock_zero': StockBalance::create(['ingredient_id' => $this->milk->id, 'location_type' => 'outlet', 'location_id' => $this->outlet->id, 'qty_on_hand' => 0]);
                break;
            case 'stock_negative': StockBalance::create(['ingredient_id' => $this->milk->id, 'location_type' => 'outlet', 'location_id' => $this->outlet->id, 'qty_on_hand' => -500]);
                break;
            case 'recipe_one_inactive_one_active':
                Recipe::create(['product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'name' => 'Old', 'is_active' => false]);
                break;
            case 'eligible':
            case 'stock_missing':
                break;
        }

        return $this->variant->id;
    }

    private function ingredient(string $name, string $unit): Ingredient
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
        $ingredient->outlets()->sync([$this->outlet->id]);

        return $ingredient;
    }

    private function productWithVariant(string $productName, string $variantName, string $code): array
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->category->id,
            'name' => $productName,
            'code' => $code,
            'is_active' => true,
        ]);
        $product->outlets()->sync([$this->outlet->id]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => $variantName,
            'code' => $code.'-V',
            'price' => 20000,
            'price_dine_in' => 20000,
            'price_delivery' => 22000,
            'is_active' => true,
        ]);
        $variant->outlets()->sync([$this->outlet->id]);

        return [$product, $variant];
    }

    private function recipeFor(ProductVariant $variant, array $items): Recipe
    {
        $recipe = Recipe::create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'name' => 'Recipe '.$variant->name,
            'is_active' => true,
        ]);

        foreach ($items as [$ingredient, $qty]) {
            RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'qty' => $qty, 'unit' => $ingredient->unit]);
        }

        return $recipe;
    }
}
