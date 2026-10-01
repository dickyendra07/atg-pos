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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Batch 3 review: backward compatible JSON, checkout still re-validates, the UI snapshot is only a hint,
 * catalog visibility is unchanged, output is escaped, and the outlet is the CASHIER's.
 */
class CashierEligibilityContractTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $bxc;

    private Outlet $other;

    private Brand $brand;

    private ProductCategory $category;

    private IngredientCategory $ingredientCategory;

    private Product $product;

    private ProductVariant $variant;

    private Ingredient $milk;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bxc = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $this->other = Outlet::create(['name' => 'Bazaar', 'code' => 'BZR', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->ingredientCategory = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);

        $this->milk = $this->ingredient('Milk', [$this->bxc]);
        [$this->product, $this->variant] = $this->productWithVariant('Kafei Susu', 'Regular', 'KS', [$this->bxc]);
        $this->recipe($this->variant, [$this->milk]);

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

        foreach ([$this->bxc, $this->other] as $outlet) {
            CashierShift::create(['user_id' => $this->cashier->id, 'outlet_id' => $outlet->id, 'started_at' => now(), 'opening_cash' => 0, 'status' => 'open']);
        }
    }

    // ---- JSON backward compatibility ---------------------------------------------------------------

    public function test_rejection_json_keeps_success_and_message_and_only_adds_fields(): void
    {
        Recipe::query()->delete();

        $response = $this->addJson($this->variant)->assertStatus(422);
        $json = $response->json();

        // what an older client reads today
        $this->assertSame(false, $json['success']);
        $this->assertSame('Produk “Kafei Susu - Regular” belum memiliki recipe. Transaksi tidak dapat dilanjutkan.', $json['message']);

        // additive only
        $this->assertSame(['success', 'message', 'cashier_message', 'reason', 'variant_status'], array_keys($json));
        $this->assertIsString($json['cashier_message']);
        $this->assertIsString($json['reason']);
        $this->assertSame(['eligible', 'reason', 'message'], array_keys($json['variant_status']));
    }

    public function test_success_json_and_other_rejections_are_unchanged(): void
    {
        $ok = $this->addJson($this->variant)->assertOk()->json();
        $this->assertSame(['success', 'message', 'cart'], array_keys($ok));
        $this->assertTrue($ok['success']);
        $this->assertSame('Item berhasil masuk ke keranjang.', $ok['message']);

        // a refusal that is not about eligibility (no shift) keeps its two-field shape
        CashierShift::query()->delete();
        $blocked = $this->addJson($this->variant)->assertStatus(422)->json();
        $this->assertSame(['success', 'message'], array_keys($blocked));
        $this->assertFalse($blocked['success']);
    }

    public function test_non_json_add_keeps_the_redirect_and_the_original_flash_wording(): void
    {
        $this->milk->update(['is_active' => false]);

        $this->actingAs($this->cashier)
            ->withSession($this->cashierSession())
            ->post(route('cashier.cart.add', $this->variant), ['order_type' => 'dine_in'])
            ->assertRedirect(route('cashier.index'))
            ->assertSessionHas('error', 'Ingredient “Milk” tidak aktif. Transaksi tidak dapat dilanjutkan.');
    }

    // ---- checkout still revalidates ----------------------------------------------------------------

    public function test_eligible_checkout_still_succeeds_and_deducts_stock(): void
    {
        StockBalance::create(['ingredient_id' => $this->milk->id, 'location_type' => 'outlet', 'location_id' => $this->bxc->id, 'qty_on_hand' => 100]);

        $this->checkout()->assertRedirect(route('cashier.index'))->assertSessionHas('last_checkout');

        $this->assertSame(1, SalesTransaction::count());
        $this->assertEquals(90, StockBalance::where('ingredient_id', $this->milk->id)->value('qty_on_hand'));
    }

    public function test_checkout_with_zero_missing_or_negative_stock_still_succeeds(): void
    {
        // missing balance
        $this->checkout()->assertSessionHas('last_checkout');

        // zero and negative
        foreach ([0, -40] as $qty) {
            StockBalance::updateOrCreate(
                ['ingredient_id' => $this->milk->id, 'location_type' => 'outlet', 'location_id' => $this->bxc->id],
                ['qty_on_hand' => $qty]
            );

            $this->checkout()->assertSessionHas('last_checkout');
        }

        $this->assertSame(3, SalesTransaction::count());
    }

    public function test_checkout_rejects_an_ineligible_cart_with_the_same_wording_as_before(): void
    {
        Recipe::query()->update(['is_active' => false]);

        $this->checkout()
            ->assertRedirect(route('cashier.index'))
            ->assertSessionHas('error', 'Checkout gagal diproses: Produk “Kafei Susu - Regular” belum memiliki recipe aktif. Transaksi tidak dapat dilanjutkan.');

        $this->assertSame(0, SalesTransaction::count());
    }

    public function test_cart_that_was_eligible_when_filled_is_revalidated_at_checkout(): void
    {
        // the cart is filled while everything is fine ...
        $this->addJson($this->variant)->assertOk();

        // ... Back Office then changes things ...
        $this->milk->outlets()->sync([$this->other->id]);

        // ... and checkout asks the rules again instead of trusting the cart or any page snapshot.
        $this->actingAs($this->cashier)
            ->post(route('cashier.checkout'), ['payment_method' => 'cash', 'amount_paid' => 20000, 'order_type' => 'dine_in'])
            ->assertRedirect(route('cashier.index'))
            ->assertSessionHas('error', 'Checkout gagal diproses: Ingredient “Milk” belum tersedia untuk BXC.');

        $this->assertSame(0, SalesTransaction::count());
    }

    // ---- snapshot is a hint, never the authority -----------------------------------------------------

    public function test_page_snapshot_does_not_let_an_add_through(): void
    {
        $this->assertStringContainsString('data-eligible="1"', $this->page());   // the snapshot said "sellable"

        $this->recipeRows()->update(['is_active' => false]);

        $this->addJson($this->variant)->assertStatus(422)->assertJsonPath('reason', 'recipe_inactive');
        $this->assertSame([], session('cashier_cart', []), 'nothing reached the cart');
    }

    // ---- catalog visibility unchanged ----------------------------------------------------------------

    public function test_catalog_filtering_is_unchanged_and_hidden_products_stay_hidden(): void
    {
        $inactive = $this->productWithVariant('Hidden Inactive', 'R', 'HI', [$this->bxc])[0];
        $inactive->update(['is_active' => false]);

        $elsewhere = $this->productWithVariant('Hidden Elsewhere', 'R', 'HE', [$this->other])[0];

        $onlyInactiveVariant = $this->productWithVariant('Only Inactive Variant', 'R', 'OIV', [$this->bxc]);
        $onlyInactiveVariant[1]->update(['is_active' => false]);

        $noVariants = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'No Variants', 'code' => 'NV', 'is_active' => true]);
        $noVariants->outlets()->sync([$this->bxc->id]);

        [$visible, $goodVariant] = $this->productWithVariant('Visible Mixed', 'Good', 'VM', [$this->bxc]);
        $this->recipe($goodVariant, [$this->milk]);
        $hiddenVariant = $this->variantFor($visible, 'Inactive', 'VM-I', [$this->bxc]);
        $hiddenVariant->update(['is_active' => false]);
        $elsewhereVariant = $this->variantFor($visible, 'Elsewhere', 'VM-E', [$this->other]);

        $html = $this->page();

        foreach (['Hidden Inactive', 'Hidden Elsewhere', 'Only Inactive Variant', 'No Variants'] as $name) {
            $this->assertStringNotContainsString('<div class="product-name">'.$name.'</div>', $html, $name);
        }

        $this->assertStringContainsString('<div class="product-name">Visible Mixed</div>', $html);
        $this->assertNotNull($this->variantAttr($html, $goodVariant, 'eligible'));
        $this->assertNull($this->variantAttr($html, $hiddenVariant, 'eligible'), 'inactive variant still filtered out');
        $this->assertNull($this->variantAttr($html, $elsewhereVariant, 'eligible'), 'variant of another outlet still filtered out');
    }

    // ---- output is escaped ---------------------------------------------------------------------------

    public function test_names_in_reasons_are_rendered_as_text_not_markup(): void
    {
        $evilIngredient = '<img src=x onerror=alert(1)>';
        $evilOutlet = '<script>alert(1)</script>';
        $evilProduct = '"><svg onload=alert(2)>';

        $this->bxc->update(['name' => $evilOutlet]);
        $this->milk->update(['name' => $evilIngredient]);
        $this->milk->outlets()->sync([$this->other->id]);     // -> "Ingredient <name> belum tersedia di <outlet>."
        $this->product->update(['name' => $evilProduct]);

        $html = $this->page();

        $this->assertStringNotContainsString($evilIngredient, $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<svg onload=alert(2)>', $html);
        $this->assertStringContainsString(e($evilIngredient), $html);
        $this->assertStringContainsString('data-reason-message="Ingredient '.e($evilIngredient).' belum tersedia di '.e($evilOutlet).'."', $html);

        // JSON carries the raw text (the page puts it in with textContent / escapeHtml).
        $json = $this->addJson($this->variant)->assertStatus(422)->json();
        $this->assertStringContainsString($evilIngredient, $json['cashier_message']);
    }

    public function test_page_script_only_puts_server_text_into_the_dom_as_text(): void
    {
        $source = file_get_contents(resource_path('views/cashier/index.blade.php'));
        $toast = file_get_contents(resource_path('views/cashier/partials/toast.blade.php'));

        // toast: no HTML sink for the message at all
        $this->assertStringContainsString('text.textContent = message', $toast);
        $this->assertStringNotContainsString('insertAdjacentHTML', $toast);
        $this->assertDoesNotMatchRegularExpression('/innerHTML\s*=\s*+(?!\'&times;\')/', $toast);

        // modal rows: every dynamic value goes through escapeHtml()
        $this->assertStringContainsString('${escapeHtml(item.dataset.reasonMessage || \'Tidak dapat dijual.\')}', $source);
        $this->assertStringNotContainsString('${item.dataset.reasonMessage}', $source);
        $this->assertStringNotContainsString('${error.message}', $source);

        // card refresh uses textContent
        $this->assertStringContainsString('reason.textContent = messages.length === 1 ? messages[0]', $source);
    }

    // ---- cashier outlet, not the Backoffice Active Outlet -------------------------------------------

    public function test_eligibility_follows_the_cashier_outlet_and_leaves_the_backoffice_active_outlet_alone(): void
    {
        // sellable at BXC only
        $backofficeActive = $this->other->id;
        $session = $this->cashierSession() + ['active_backoffice_outlet_id' => $backofficeActive];

        $page = $this->actingAs($this->cashier)->withSession($session)->get(route('cashier.index'))->assertOk()->getContent();
        $this->assertSame('1', $this->variantAttr($page, $this->variant, 'eligible'), 'judged at the cashier outlet (BXC), not at Bazaar');

        $this->postJson(route('cashier.cart.add', $this->variant), ['order_type' => 'dine_in'])->assertOk();

        $this->assertSame($backofficeActive, session('active_backoffice_outlet_id'), 'Backoffice Active Outlet untouched');

        // and the reverse: cashier at Bazaar, Backoffice looking at BXC
        $this->product->outlets()->sync([$this->bxc->id, $this->other->id]);
        $this->variant->outlets()->sync([$this->bxc->id, $this->other->id]);

        $this->actingAs($this->cashier)
            ->withSession($this->cashierSession($this->other) + ['active_backoffice_outlet_id' => $this->bxc->id])
            ->postJson(route('cashier.cart.add', $this->variant), ['order_type' => 'dine_in'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'ingredient_not_at_outlet')
            ->assertJsonPath('variant_status.message', 'Ingredient Milk belum tersedia di Bazaar.');
    }

    // ---- performance --------------------------------------------------------------------------------

    public function test_variant_status_queries_do_not_grow_with_the_number_of_variants(): void
    {
        $counts = [];
        $ids = [$this->variant->id];
        $targets = [1, 31, 101];

        foreach ($targets as $target) {
            while (count($ids) < $target) {
                $i = count($ids);
                [, $variant] = $this->productWithVariant('Bulk '.$i, 'R', 'B'.$i, [$this->bxc]);
                $this->recipe($variant, [$this->milk, $this->ingredient('Bulk Ing '.$i, [$this->bxc])]);
                $ids[] = $variant->id;
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $statuses = app(SaleEligibilityService::class)->variantStatuses($ids, $this->bxc->id);
            $counts[$target] = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertCount($target, $statuses);
        }

        if (getenv('PARITY_TABLE')) {
            fwrite(STDERR, "\nvariantStatuses queries: ".json_encode($counts)."\n");
        }

        $this->assertSame(1, count(array_unique($counts)), 'same number of queries for 1, 31 and 101 variants: '.json_encode($counts));
    }

    // ---- helpers ---------------------------------------------------------------------------------

    private function cashierSession(?Outlet $outlet = null): array
    {
        return ['auth_portal' => 'cashier', 'cashier_outlet_id' => ($outlet ?? $this->bxc)->id, 'cashier_order_type' => 'dine_in'];
    }

    private function addJson(ProductVariant $variant)
    {
        return $this->actingAs($this->cashier)
            ->withSession($this->cashierSession())
            ->postJson(route('cashier.cart.add', $variant), ['order_type' => 'dine_in']);
    }

    private function page(): string
    {
        return $this->actingAs($this->cashier)->withSession($this->cashierSession())->get(route('cashier.index'))->assertOk()->getContent();
    }

    private function checkout()
    {
        return $this->actingAs($this->cashier)
            ->withSession($this->cashierSession() + ['cashier_cart' => ['variant_'.$this->variant->id.'_dine_in' => [
                'cart_key' => 'variant_'.$this->variant->id.'_dine_in',
                'variant_id' => $this->variant->id,
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'variant_name' => $this->variant->name,
                'order_type' => 'dine_in',
                'qty' => 1,
                'price' => 20000,
                'line_total' => 20000,
            ]]])
            ->post(route('cashier.checkout'), ['payment_method' => 'cash', 'amount_paid' => 20000, 'order_type' => 'dine_in']);
    }

    private function recipeRows()
    {
        return Recipe::where('product_variant_id', $this->variant->id);
    }

    private function variantAttr(string $html, ProductVariant $variant, string $attr): ?string
    {
        $pos = strpos($html, 'data-url="'.e(route('cashier.cart.add', $variant)).'"');

        if ($pos === false) {
            return null;
        }

        return preg_match('/data-'.preg_quote($attr, '/').'="([^"]*)"/', substr($html, $pos, 400), $m) ? html_entity_decode($m[1]) : null;
    }

    private function ingredient(string $name, array $outlets): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->ingredientCategory->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $name)).'_'.uniqid(),
            'unit' => 'ml',
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => true,
        ]);
        $ingredient->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $ingredient;
    }

    private function productWithVariant(string $productName, string $variantName, string $code, array $outlets): array
    {
        $product = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => $productName, 'code' => $code, 'is_active' => true]);
        $product->outlets()->sync(collect($outlets)->pluck('id')->all());

        return [$product, $this->variantFor($product, $variantName, $code.'-V', $outlets)];
    }

    private function variantFor(Product $product, string $name, string $code, array $outlets): ProductVariant
    {
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => $name, 'code' => $code, 'price' => 20000, 'price_dine_in' => 20000, 'price_delivery' => 22000, 'is_active' => true]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    private function recipe(ProductVariant $variant, array $ingredients): Recipe
    {
        $recipe = Recipe::create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'name' => 'Recipe '.$variant->name, 'is_active' => true]);

        foreach ($ingredients as $ingredient) {
            RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'qty' => 10, 'unit' => 'ml']);
        }

        return $recipe;
    }
}
