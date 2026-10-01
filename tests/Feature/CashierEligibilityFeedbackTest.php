<?php

namespace Tests\Feature;

use App\Exceptions\SaleNotEligibleException;
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
use App\Models\StockBalance;
use App\Models\User;
use App\Services\SaleEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Batch 3: the Cashier explains WHY a Variant can not be sold. SaleEligibilityService stays the only
 * rule book; these tests also pin that stock on hand is not a rule.
 */
class CashierEligibilityFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $bxc;

    private Outlet $other;

    private Product $product;

    private ProductVariant $variant;

    private Ingredient $milk;

    private User $cashier;

    private Brand $brand;

    private ProductCategory $category;

    private IngredientCategory $ingredientCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bxc = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $this->other = Outlet::create(['name' => 'Bazaar', 'code' => 'BZR', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->ingredientCategory = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);

        $this->milk = $this->makeIngredient('Milk', [$this->bxc]);
        [$this->product, $this->variant] = $this->makeProductWithVariant('Kafei Susu', 'Regular', 'KS');
        $this->makeRecipe($this->variant, [$this->milk]);

        $this->cashier = User::create([
            'name' => 'Kasir',
            'username' => 'kasir',
            'email' => 'kasir@example.test',
            'password' => 'password',
            'role_id' => Role::create(['name' => 'Owner', 'code' => 'owner'])->id,
            'outlet_id' => $this->bxc->id,
            'is_active' => true,
        ]);
        $this->cashier->outlets()->sync([$this->bxc->id, $this->other->id]);

        CashierShift::create(['user_id' => $this->cashier->id, 'outlet_id' => $this->bxc->id, 'started_at' => now(), 'opening_cash' => 0, 'status' => 'open']);
    }

    // ---- A: eligible stays as it was -------------------------------------------------------------

    public function test_fully_eligible_variant_is_sellable_and_adds_to_the_cart(): void
    {
        $this->assertSame(['eligible' => true, 'reason' => null, 'message' => null], $this->saleStatus($this->variant));

        $this->addToCart($this->variant)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Item berhasil masuk ke keranjang.');
    }

    // ---- B..H: every reason SaleEligibilityService really has ------------------------------------

    public static function reasons(): array
    {
        return [
            'product inactive' => ['product_inactive', 'Product sedang nonaktif.'],
            'product not at outlet' => ['product_not_at_outlet', 'Product tidak tersedia di BXC.'],
            'variant inactive' => ['variant_inactive', 'Variant sedang nonaktif.'],
            'variant not at outlet' => ['variant_not_at_outlet', 'Variant tidak tersedia di BXC.'],
            'recipe missing' => ['recipe_missing', 'Recipe belum tersedia.'],
            'recipe inactive' => ['recipe_inactive', 'Recipe sedang nonaktif.'],
            'recipe ambiguous' => ['recipe_ambiguous', 'Ada lebih dari satu Recipe aktif. Hubungi Back Office.'],
            'recipe without items' => ['recipe_empty', 'Recipe belum memiliki bahan.'],
            'recipe product mismatch' => ['recipe_product_mismatch', 'Recipe tidak terhubung ke product yang benar. Hubungi Back Office.'],
            'ingredient inactive' => ['ingredient_inactive', 'Ingredient Milk sedang nonaktif.'],
            'ingredient not at outlet' => ['ingredient_not_at_outlet', 'Ingredient Milk belum tersedia di BXC.'],
            'ingredient qty zero' => ['ingredient_qty_invalid', 'Qty ingredient Milk pada Recipe harus lebih dari 0.'],
        ];
    }

    #[DataProvider('reasons')]
    public function test_status_gives_a_short_human_reason_and_matches_what_add_to_cart_enforces(string $reason, string $message): void
    {
        $this->break($reason, $this->variant);

        $status = $this->saleStatus($this->variant);

        $this->assertFalse($status['eligible']);
        $this->assertSame($reason, $status['reason']);
        $this->assertSame($message, $status['message']);

        // Same verdict from the validator itself (single source of truth), and its long-standing
        // exception message is untouched.
        try {
            app(SaleEligibilityService::class)->requirementsForCart($this->cartFor($this->variant), $this->bxc->id);
            $this->fail('requirementsForCart should have refused this Variant.');
        } catch (SaleNotEligibleException $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertSame($message, $e->cashierMessage);
            $this->assertNotSame('', $e->getMessage(), 'long-standing exception message is kept');
        }

        // The server refuses the add with the fresh reason, and tells the open modal which Variant it was.
        $this->addToCart($this->variant)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', $reason)
            ->assertJsonPath('cashier_message', 'Kafei Susu - Regular: '.$message)
            ->assertJsonPath('variant_status.eligible', false)
            ->assertJsonPath('variant_status.message', $message);
    }

    public function test_reasons_never_expose_internal_details(): void
    {
        foreach (self::reasons() as [$reason, $message]) {
            $this->assertDoesNotMatchRegularExpression('/exception|sql|constraint|stack|_id|\\\\|#\d/i', $message, $reason);
        }
    }

    // ---- L: stock is not a rule ------------------------------------------------------------------

    public function test_zero_missing_or_negative_stock_balance_does_not_block_a_sale(): void
    {
        // no StockBalance row at all
        $this->assertTrue($this->saleStatus($this->variant)['eligible']);

        foreach ([0, -25] as $qty) {
            StockBalance::updateOrCreate(
                ['ingredient_id' => $this->milk->id, 'location_type' => 'outlet', 'location_id' => $this->bxc->id],
                ['qty_on_hand' => $qty]
            );

            $this->assertTrue($this->saleStatus($this->variant)['eligible'], 'qty_on_hand '.$qty);
            $this->addToCart($this->variant)->assertOk()->assertJsonPath('success', true);
        }
    }

    // ---- I / J: what the Cashier page shows ------------------------------------------------------

    public function test_product_with_mixed_variants_marks_each_variant_and_keeps_the_product_sellable(): void
    {
        $missingRecipe = $this->makeVariant($this->product, 'Large', 'KS-L');                       // no Recipe
        $badIngredient = $this->makeVariant($this->product, 'XL', 'KS-XL');                         // ingredient not at outlet
        $this->makeRecipe($badIngredient, [$this->makeIngredient('Syrup', [$this->other])]);

        $html = $this->cashierPage();

        $this->assertSame('1', $this->variantAttr($html, $this->variant, 'eligible'));
        $this->assertSame('', $this->variantAttr($html, $this->variant, 'reason-message'));
        $this->assertSame('0', $this->variantAttr($html, $missingRecipe, 'eligible'));
        $this->assertSame('Recipe belum tersedia.', $this->variantAttr($html, $missingRecipe, 'reason-message'));
        $this->assertSame('0', $this->variantAttr($html, $badIngredient, 'eligible'));
        $this->assertSame('Ingredient Syrup belum tersedia di BXC.', $this->variantAttr($html, $badIngredient, 'reason-message'));

        // The Product itself is NOT greyed out: one Variant can still be sold.
        $card = $this->productCard($html, $this->product);
        $this->assertStringNotContainsString('is-unavailable', $card);
        $this->assertStringContainsString('3 variant aktif • 2 tidak tersedia', $this->squash($card));
        $this->assertStringContainsString('Pilih Variant', $card);

        // and the eligible Variant still adds normally while an invalid one is refused
        $this->addToCart($this->variant)->assertOk();
        $this->addToCart($missingRecipe)->assertStatus(422)->assertJsonPath('reason', 'recipe_missing');
    }

    public function test_product_whose_variants_are_all_invalid_shows_unavailable_with_a_reason(): void
    {
        $second = $this->makeVariant($this->product, 'Large', 'KS-L');   // also no Recipe
        Recipe::where('product_variant_id', $this->variant->id)->update(['is_active' => false]);

        $card = $this->productCard($this->cashierPage(), $this->product);

        $this->assertStringContainsString('is-unavailable', $card);
        $this->assertStringContainsString('Tidak dapat dijual', $card);
        $this->assertStringContainsString('Lihat Alasan', $card);
        // two different reasons -> point at the per-variant list instead of picking one
        $this->assertStringContainsString('Lihat alasan di setiap variant.', $card);
        $this->assertSame('Recipe sedang nonaktif.', $this->variantAttr($this->cashierPage(), $this->variant, 'reason-message'));
        $this->assertSame('Recipe belum tersedia.', $this->variantAttr($this->cashierPage(), $second, 'reason-message'));
    }

    public function test_single_variant_product_that_is_invalid_shows_its_reason_on_the_card(): void
    {
        Recipe::where('product_variant_id', $this->variant->id)->delete();

        $card = $this->productCard($this->cashierPage(), $this->product);

        $this->assertStringContainsString('is-unavailable', $card);
        $this->assertStringContainsString('Tidak dapat dijual', $card);
        $this->assertStringContainsString('Recipe belum tersedia.', $card, 'the reason itself, not a generic message');
    }

    public function test_page_script_explains_a_single_invalid_variant_without_opening_an_empty_modal(): void
    {
        $html = $this->cashierPage();

        // Single Variant + not eligible -> toast with the reason, no modal.
        $this->assertStringContainsString("sourceItems.length === 1 && sourceItems[0].dataset.eligible === '0'", $html);
        // The rows the modal renders for a refused Variant are disabled and carry the reason.
        $this->assertStringContainsString('variant-unavailable-btn" disabled', $html);
        $this->assertStringContainsString('variant-unavailable-reason', $html);
    }

    public function test_eligible_cards_are_unchanged_for_the_normal_flow(): void
    {
        $card = $this->productCard($this->cashierPage(), $this->product);

        $this->assertStringNotContainsString('is-unavailable', $card);
        $this->assertStringContainsString('1 variant aktif', $card);
        $this->assertStringContainsString('Pilih Variant', $card);
        $this->assertStringContainsString('data-open-variant-modal', $card);
    }

    // ---- K: stale page ---------------------------------------------------------------------------

    public function test_server_refusal_after_the_page_loaded_gives_the_fresh_reason(): void
    {
        $html = $this->cashierPage();
        $this->assertSame('1', $this->variantAttr($html, $this->variant, 'eligible'), 'page said sellable');

        // Back Office changes things after the page was rendered.
        Recipe::where('product_variant_id', $this->variant->id)->update(['is_active' => false]);

        $this->addToCart($this->variant)
            ->assertStatus(422)
            ->assertJsonPath('reason', 'recipe_inactive')
            ->assertJsonPath('cashier_message', 'Kafei Susu - Regular: Recipe sedang nonaktif.')
            ->assertJsonPath('variant_status.message', 'Recipe sedang nonaktif.');

        // ...and then the Ingredient changes: the newest answer wins again.
        Recipe::where('product_variant_id', $this->variant->id)->update(['is_active' => true]);
        $this->milk->update(['is_active' => false]);

        $this->addToCart($this->variant)
            ->assertStatus(422)
            ->assertJsonPath('reason', 'ingredient_inactive');
    }

    public function test_a_stale_cart_line_is_named_when_it_is_the_one_that_blocks_the_add(): void
    {
        $other = $this->makeVariant($this->product, 'Large', 'KS-L');
        $this->makeRecipe($other, [$this->milk]);

        $this->addToCart($other)->assertOk();                         // Large is in the cart
        Recipe::where('product_variant_id', $other->id)->update(['is_active' => false]);

        $this->addToCart($this->variant, keepCart: true)              // adding Regular is refused because of Large
            ->assertStatus(422)
            ->assertJsonPath('cashier_message', 'Kafei Susu - Large: Recipe sedang nonaktif.')
            ->assertJsonPath('variant_status', null);                 // Regular itself is not marked unavailable
    }

    public function test_non_json_add_keeps_the_existing_flash_error(): void
    {
        $this->break('recipe_missing', $this->variant);

        $this->actingAs($this->cashier)
            ->withSession($this->cashierSession())
            ->post(route('cashier.cart.add', $this->variant), ['order_type' => 'dine_in'])
            ->assertRedirect(route('cashier.index'))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'belum memiliki recipe'));
    }

    // ---- C/E: outlet context ---------------------------------------------------------------------

    public function test_status_follows_the_cashier_outlet_not_another_outlet(): void
    {
        $this->product->outlets()->sync([$this->bxc->id, $this->other->id]);
        $this->variant->outlets()->sync([$this->bxc->id, $this->other->id]);
        $this->milk->outlets()->sync([$this->bxc->id]);               // Milk only at BXC

        $this->assertTrue($this->saleStatus($this->variant, $this->bxc)['eligible']);

        $atOther = $this->saleStatus($this->variant, $this->other);
        $this->assertFalse($atOther['eligible']);
        $this->assertSame('Ingredient Milk belum tersedia di Bazaar.', $atOther['message'], 'names the outlet being checked');

        // A Cashier working at Bazaar gets that verdict from the real endpoint too.
        $this->actingAs($this->cashier)
            ->withSession($this->cashierSession($this->other))
            ->postJson(route('cashier.cart.add', $this->variant), ['order_type' => 'dine_in'])
            ->assertStatus(422);
    }

    public function test_inactive_or_out_of_outlet_products_stay_off_the_cashier_list_and_are_still_refused_if_posted(): void
    {
        $this->product->update(['is_active' => false]);

        $this->assertNull($this->productCard($this->cashierPage(), $this->product), 'unchanged: not listed');

        $this->addToCart($this->variant)
            ->assertStatus(422)
            ->assertJsonPath('reason', 'product_inactive')
            ->assertJsonPath('cashier_message', 'Kafei Susu - Regular: Product sedang nonaktif.');
    }

    // ---- error visibility ------------------------------------------------------------------------

    public function test_cashier_toast_sits_above_every_modal_layer_and_error_alerts_use_it(): void
    {
        $html = $this->cashierPage();

        preg_match_all('/z-index:\s*(\d+)/', $html, $matches);
        $this->assertContains('2147483647', $matches[1], 'toast layer');
        $this->assertGreaterThan(2147483001, 2147483647, 'above .variant-modal');

        $this->assertStringContainsString('id="cashier-toast-region"', $html);
        $this->assertStringContainsString('aria-live="assertive"', $html);
        $this->assertStringContainsString("window.CashierToast.show('error', message)", $html, 'showAlert routes errors to the toast');
        $this->assertStringNotContainsString('alert(', preg_replace('/showAlert\(|\.alert\b|\balert-|cashier-alert|class="alert/', '', $html) ?? '', 'no browser alert()');
    }

    // ---- performance -----------------------------------------------------------------------------

    public function test_variant_statuses_use_a_constant_number_of_queries(): void
    {
        $ids = [$this->variant->id];

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(SaleEligibilityService::class)->variantStatuses($ids, $this->bxc->id);
        $few = count(DB::getQueryLog());

        for ($i = 0; $i < 30; $i++) {
            $variant = $this->makeVariant($this->product, 'V'.$i, 'KS-'.$i);
            $this->makeRecipe($variant, [$this->milk, $this->makeIngredient('Extra '.$i, [$this->bxc])]);
            $ids[] = $variant->id;
        }

        DB::flushQueryLog();
        app(SaleEligibilityService::class)->variantStatuses($ids, $this->bxc->id);
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($few, $many, 'query count must not grow with the number of Variants');
        $this->assertLessThanOrEqual(10, $many);
    }

    public function test_cashier_page_query_count_does_not_grow_with_catalog_size(): void
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->cashierPage();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->cashierPage(); // warm any lazy session/user work
        $before = $count();

        for ($i = 0; $i < 12; $i++) {
            [, $variant] = $this->makeProductWithVariant('Extra '.$i, 'R', 'EX'.$i);
            $this->makeRecipe($variant, [$this->milk]);
        }

        $this->assertSame($before, $count());
    }

    // ---- helpers ---------------------------------------------------------------------------------

    private function saleStatus(ProductVariant $variant, ?Outlet $outlet = null): array
    {
        return app(SaleEligibilityService::class)->variantStatuses([$variant->id], ($outlet ?? $this->bxc)->id)[$variant->id];
    }

    /** Put $variant into the failing state named by $reason. */
    private function break(string $reason, ProductVariant $variant): void
    {
        $recipe = Recipe::where('product_variant_id', $variant->id)->first();

        match ($reason) {
            'product_inactive' => $variant->product->update(['is_active' => false]),
            'product_not_at_outlet' => $variant->product->outlets()->sync([$this->other->id]),
            'variant_inactive' => $variant->update(['is_active' => false]),
            'variant_not_at_outlet' => $variant->outlets()->sync([$this->other->id]),
            'recipe_missing' => Recipe::where('product_variant_id', $variant->id)->delete(),
            'recipe_inactive' => $recipe->update(['is_active' => false]),
            'recipe_ambiguous' => Recipe::create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'name' => 'Second', 'is_active' => true]),
            'recipe_empty' => $recipe->items()->delete(),
            'recipe_product_mismatch' => $recipe->update(['product_id' => $this->makeProductWithVariant('Lain', 'R', 'LN')[0]->id]),
            'ingredient_inactive' => $this->milk->update(['is_active' => false]),
            'ingredient_not_at_outlet' => $this->milk->outlets()->sync([$this->other->id]),
            'ingredient_qty_invalid' => $recipe->items()->update(['qty' => 0]),
        };
    }

    private function cashierSession(?Outlet $outlet = null): array
    {
        return ['auth_portal' => 'cashier', 'cashier_outlet_id' => ($outlet ?? $this->bxc)->id, 'cashier_order_type' => 'dine_in'];
    }

    private function cashierPage(): string
    {
        return $this->actingAs($this->cashier)
            ->withSession($this->cashierSession())
            ->get(route('cashier.index'))
            ->assertOk()
            ->getContent();
    }

    private function addToCart(ProductVariant $variant, bool $keepCart = false)
    {
        $session = $this->cashierSession();

        if (! $keepCart) {
            $session['cashier_cart'] = [];
        }

        return $this->actingAs($this->cashier)
            ->withSession($keepCart ? [] : $session)
            ->postJson(route('cashier.cart.add', $variant), ['order_type' => 'dine_in']);
    }

    private function cartFor(ProductVariant $variant): array
    {
        return ['k' => ['variant_id' => $variant->id, 'qty' => 1]];
    }

    /** The markup of one Product card on the Cashier page, or null when it is not listed. */
    private function productCard(string $html, Product $product): ?string
    {
        $pos = strpos($html, '<div class="product-name">'.e($product->name).'</div>');

        if ($pos === false) {
            return null;
        }

        $start = strrpos(substr($html, 0, $pos), 'class="product-card');
        $ends = array_filter([
            strpos($html, 'class="product-card', $pos),
            strpos($html, 'product-category-empty', $pos),
        ], fn ($end) => $end !== false);

        return substr($html, $start, min($ends) - $start);
    }

    private function variantAttr(string $html, ProductVariant $variant, string $attr): ?string
    {
        $url = e(route('cashier.cart.add', $variant));
        $pos = strpos($html, 'data-url="'.$url.'"');

        if ($pos === false) {
            return null;
        }

        $tag = substr($html, $pos, 400);

        return preg_match('/data-'.preg_quote($attr, '/').'="([^"]*)"/', $tag, $m) ? html_entity_decode($m[1]) : null;
    }

    private function squash(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    private function makeIngredient(string $name, array $outlets): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->ingredientCategory->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $name)),
            'unit' => 'ml',
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => true,
        ]);
        $ingredient->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $ingredient;
    }

    private function makeProductWithVariant(string $productName, string $variantName, string $code): array
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->category->id,
            'name' => $productName,
            'code' => $code,
            'is_active' => true,
        ]);
        $product->outlets()->sync([$this->bxc->id]);

        return [$product, $this->makeVariant($product, $variantName, $code.'-V')];
    }

    private function makeVariant(Product $product, string $name, string $code): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => $name,
            'code' => $code,
            'price' => 20000,
            'price_dine_in' => 20000,
            'price_delivery' => 22000,
            'is_active' => true,
        ]);
        $variant->outlets()->sync([$this->bxc->id]);

        return $variant;
    }

    private function makeRecipe(ProductVariant $variant, array $ingredients): Recipe
    {
        $recipe = Recipe::create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'name' => 'Recipe '.$variant->name,
            'is_active' => true,
        ]);

        foreach ($ingredients as $ingredient) {
            RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'qty' => 10, 'unit' => 'ml']);
        }

        return $recipe;
    }
}
