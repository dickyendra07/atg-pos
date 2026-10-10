<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requirement #03: the Products page category accordion.
 *
 * These are server-rendered contracts: what the HTML promises (collapsed state, accessible buttons, counts,
 * ARIA state with and without JavaScript). The in-page JavaScript (toggling, Expand All) is exercised in a
 * real browser during QA, not here.
 */
class ProductsCategoryAccordionTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private Brand $brand;

    private User $owner;

    /** @var array<string, ProductCategory> */
    private array $categories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);

        $role = Role::create(['name' => 'Owner', 'code' => 'owner']);
        $this->owner = User::create([
            'name' => 'owner',
            'username' => 'owner',
            'email' => 'owner@example.test',
            'password' => 'password',
            'role_id' => $role->id,
            'outlet_id' => $this->outlet->id,
            'is_active' => true,
        ]);
        $this->owner->outlets()->sync([$this->outlet->id]);

        // Astral (2 products), Corn Milk (3 products), Extra (1 product).
        $this->makeProduct('Astral', 'Astral Latte', 'Regular', 30000);
        $this->makeProduct('Astral', 'Astral Mocha', 'Large', 35000, false);
        $this->makeProduct('Corn Milk', 'Corn Milk Original', 'Regular', 20000);
        $this->makeProduct('Corn Milk', 'Corn Milk Cheese', 'Regular', 22000);
        $this->makeProduct('Corn Milk', 'Corn Milk Choco', 'Regular', 24000);
        $this->makeProduct('Extra', 'Extra Boba', 'Cup', 5000);
    }

    // ---- #03 Products category accordion ----------------------------------------------------

    public function test_every_category_starts_closed_including_the_first_one(): void
    {
        $xpath = $this->productsPage();

        $sections = $xpath->query('//*[@data-product-category-section]');
        $this->assertSame(3, $sections->length);

        foreach ($sections as $section) {
            $this->assertStringNotContainsString('is-open', $section->getAttribute('class'));
        }

        $this->assertSame(3, $xpath->query('//button[@data-product-category-toggle]')->length);

        // The old "first category is open" rule and the old class-based collapse are gone.
        $html = $this->get(route('backoffice.products.index'))->getContent();
        $this->assertStringNotContainsString('loop->first', $html);
        $this->assertStringNotContainsString('product-category-section collapsed', $html);
        $this->assertStringNotContainsString('.product-category-section.collapsed', $html);
    }

    public function test_category_headers_are_real_buttons_wired_to_their_panels(): void
    {
        $xpath = $this->productsPage();

        // No non-interactive element carries the toggle any more.
        $this->assertSame(0, $xpath->query('//*[@data-product-category-toggle and not(self::button)]')->length);

        foreach ($xpath->query('//button[@data-product-category-toggle]') as $button) {
            $this->assertSame('button', $button->getAttribute('type'), 'must never submit a form');

            $panelId = $button->getAttribute('aria-controls');
            $this->assertNotSame('', $panelId);

            $panel = $xpath->query('//*[@id="'.$panelId.'"]')->item(0);
            $this->assertNotNull($panel, 'aria-controls points at an existing panel');
            $this->assertSame($button->getAttribute('id'), $panel->getAttribute('aria-labelledby'));
            $this->assertSame('region', $panel->getAttribute('role'));
        }
    }

    public function test_category_counts_are_accurate_and_singular_when_one(): void
    {
        $xpath = $this->productsPage();

        $counts = [];
        foreach ($xpath->query('//button[@data-product-category-toggle]') as $button) {
            $title = trim($xpath->query('.//*[contains(@class,"product-category-title")]', $button)->item(0)->textContent);
            $counts[$title] = trim($xpath->query('.//*[contains(@class,"product-category-count")]', $button)->item(0)->textContent);
        }

        $this->assertSame(['Astral' => '2 Products', 'Corn Milk' => '3 Products', 'Extra' => '1 Product'], $counts);
    }

    public function test_products_variants_prices_status_and_actions_are_still_rendered_once_each(): void
    {
        $response = $this->actingAs($this->owner)->get(route('backoffice.products.index'))->assertOk();
        $xpath = $this->xpath($response->getContent());

        // Every product row exists exactly once (no duplicate loading) and sits inside its category panel.
        foreach (Product::all() as $product) {
            $rows = $xpath->query('//tr[@id="product-'.$product->id.'"]');
            $this->assertSame(1, $rows->length, $product->name);
            $this->assertSame(
                1,
                $xpath->query('//tr[@id="product-'.$product->id.'"]/ancestor::*[@data-product-category-section]')->length,
                'the row can be revealed by bo:reveal'
            );
            $this->assertSame(1, $xpath->query('//tr[@id="product-'.$product->id.'"]//a[contains(@href,"/products/'.$product->id.'/edit")]')->length);
        }

        $response->assertSee('Regular - Rp 30.000', false)
            ->assertSee('Large - Rp 35.000', false)
            ->assertSee('Active', false)
            ->assertSee('Inactive', false)
            ->assertSee('Nonaktifkan', false);

        // Nonaktifkan is offered for active products only (3 of the 4 active rows here are unchanged: 5 active).
        $this->assertSame(5, substr_count($response->getContent(), '>Nonaktifkan</button>'));
    }

    public function test_search_opens_the_categories_that_hold_matches(): void
    {
        $response = $this->actingAs($this->owner)->get(route('backoffice.products.index', ['search' => 'Choco']))->assertOk();
        $xpath = $this->xpath($response->getContent());

        $sections = $xpath->query('//*[@data-product-category-section]');
        $this->assertSame(1, $sections->length, 'only the category with a match is listed');
        $this->assertStringContainsString('is-open', $sections->item(0)->getAttribute('class'));
        $this->assertSame('true', $xpath->query('//button[@data-product-category-toggle]')->item(0)->getAttribute('aria-expanded'));

        $response->assertSee('Corn Milk Choco')->assertDontSee('Astral Latte')->assertDontSee('Extra Boba');
        $this->assertSame('value="Choco"', $this->searchFieldValue($response->getContent()));
    }

    public function test_variant_name_search_still_matches_and_stays_visible(): void
    {
        $xpath = $this->xpath($this->actingAs($this->owner)->get(route('backoffice.products.index', ['search' => 'Large']))->assertOk()->getContent());

        $this->assertSame(1, $xpath->query('//*[@data-product-category-section]')->length);
        $this->assertSame('true', $xpath->query('//button[@data-product-category-toggle]')->item(0)->getAttribute('aria-expanded'));
        $this->assertSame(1, $xpath->query('//tr[contains(@id,"product-")]')->length);
    }

    public function test_category_filter_opens_the_selected_category(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('backoffice.products.index', ['category_id' => $this->categories['Astral']->id]))
            ->assertOk();
        $xpath = $this->xpath($response->getContent());

        $this->assertSame(1, $xpath->query('//*[@data-product-category-section]')->length);
        $this->assertSame('true', $xpath->query('//button[@data-product-category-toggle]')->item(0)->getAttribute('aria-expanded'));
        $response->assertSee('Astral Latte')->assertSee('Astral Mocha')->assertDontSee('Corn Milk Original');
    }

    public function test_search_and_category_filter_combine(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('backoffice.products.index', ['search' => 'Corn', 'category_id' => $this->categories['Astral']->id]));

        $response->assertOk()->assertSee('Belum ada product yang cocok dengan filter aktif.');
        $this->assertSame(0, $this->xpath($response->getContent())->query('//*[@data-product-category-section]')->length);
    }

    public function test_blank_search_does_not_count_as_an_active_filter(): void
    {
        $xpath = $this->xpath($this->actingAs($this->owner)->get(route('backoffice.products.index', ['search' => '   ']))->assertOk()->getContent());

        $this->assertSame(3, $xpath->query('//*[@data-product-category-section]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-product-category-section][contains(@class,"is-open")]')->length);
    }

    public function test_accordion_script_keeps_reveal_navigation_expand_all_and_reduced_motion(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.products.index'))->assertOk()->getContent();

        // Existing navigation: a record revealed after save opens its category (and does so without animation).
        $this->assertStringContainsString("addEventListener('bo:reveal'", $html);
        $this->assertMatchesRegularExpression('/bo:reveal.{0,400}setOpen\(section, true, true\)/s', $html);

        // Click toggles, aria-expanded is kept in sync.
        $this->assertStringContainsString("button.setAttribute('aria-expanded'", $html);
        $this->assertStringContainsString('section.classList.contains(\'is-open\')', $html);

        // Expand All / Collapse All exist when there is more than one category.
        $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//button[@type="button" and @data-product-category-expand-all]')->length);
        $this->assertSame(1, $xpath->query('//button[@type="button" and @data-product-category-collapse-all]')->length);

        // Motion is optional, and the panel only collapses when JS is running (.bo-js).
        $this->assertMatchesRegularExpression('/prefers-reduced-motion: reduce.{0,600}transition: none/s', $html);
        $this->assertStringContainsString('.bo-js .product-category-panel {', $html);
    }

    public function test_expand_all_controls_are_not_shown_for_a_single_category(): void
    {
        $html = $this->actingAs($this->owner)
            ->get(route('backoffice.products.index', ['category_id' => $this->categories['Extra']->id]))
            ->assertOk()->getContent();

        $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//*[@data-product-category-section]')->length);
        $this->assertSame(0, $xpath->query('//button[@data-product-category-expand-all or @data-product-category-collapse-all]')->length);
    }

    public function test_viewing_the_products_page_changes_no_data(): void
    {
        $before = [Product::count(), ProductVariant::count(), Product::where('is_active', true)->count()];

        $this->actingAs($this->owner)->get(route('backoffice.products.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('backoffice.products.index', ['search' => 'Astral']))->assertOk();

        $this->assertSame($before, [Product::count(), ProductVariant::count(), Product::where('is_active', true)->count()]);
    }

    // ---- No-JS accessibility ------------------------------------------------------------------

    public function test_without_javascript_every_panel_is_visible_and_aria_expanded_says_so(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.products.index'))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        // The page as delivered is the no-JS page: nothing is hidden, so every header reports "expanded".
        $buttons = $xpath->query('//button[@data-product-category-toggle]');
        $this->assertSame(3, $buttons->length);

        foreach ($buttons as $button) {
            $this->assertSame('true', $button->getAttribute('aria-expanded'));
        }

        // Closing exists only under .bo-js (set by the layout's inline script); no un-gated rule hides a panel.
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $this->styleBlock($html), $rules, PREG_SET_ORDER);

        foreach ($rules as [, $selector, $body]) {
            if (! str_contains($selector, 'product-category-panel')) {
                continue;
            }

            if (preg_match('/visibility:\s*hidden|grid-template-rows:\s*0fr|display:\s*none/', $body)) {
                $this->assertStringContainsString('.bo-js', $selector, 'hides content without JS: '.trim($selector));
            }
        }

        // The chevron only points "closed" when JS can reopen the panel, and the header only looks clickable then.
        $this->assertMatchesRegularExpression('/\.bo-js \.product-category-section:not\(\.is-open\) \.product-category-toggle\s*\{[^}]*rotate\(-90deg\)/', $html);
        $this->assertMatchesRegularExpression('/\.bo-js \.product-category-head\s*\{[^}]*cursor:\s*pointer/', $html);
    }

    public function test_javascript_syncs_aria_expanded_with_the_real_state_on_load(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.products.index'))->assertOk()->getContent();

        // On load the script sets aria-expanded from the is-open class: closed unless the server opened it.
        $this->assertMatchesRegularExpression(
            "/sections\.forEach\(function \(section\) \{\s*var button = section\.querySelector\('\[data-product-category-toggle\]'\);\s*if \(button\) \{\s*button\.setAttribute\('aria-expanded', section\.classList\.contains\('is-open'\) \? 'true' : 'false'\);/",
            $html
        );

        // Toggling, Expand All and bo:reveal all go through setOpen(), which keeps aria-expanded in step.
        $this->assertMatchesRegularExpression("/function setOpen\(section, open, instant\).{0,800}button\.setAttribute\('aria-expanded', open \? 'true' : 'false'\)/s", $html);
    }

    public function test_an_open_category_is_consistent_between_class_and_aria_in_the_markup(): void
    {
        $xpath = $this->xpath($this->actingAs($this->owner)->get(route('backoffice.products.index', ['search' => 'Astral']))->assertOk()->getContent());

        $section = $xpath->query('//*[@data-product-category-section]')->item(0);
        $this->assertStringContainsString('is-open', $section->getAttribute('class'));
        $this->assertSame('true', $xpath->query('.//button[@data-product-category-toggle]', $section)->item(0)->getAttribute('aria-expanded'));
    }

    // ---- helpers ------------------------------------------------------------------------------

    private function productsPage(): DOMXPath
    {
        return $this->xpath($this->actingAs($this->owner)->get(route('backoffice.products.index'))->assertOk()->getContent());
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    private function styleBlock(string $html): string
    {
        preg_match_all('#<style>(.*?)</style>#s', $html, $blocks);

        return preg_replace('#/\*.*?\*/#s', '', implode("\n", $blocks[1]));
    }

    private function searchFieldValue(string $html): string
    {
        preg_match('/<input[^>]*name="search"[^>]*?(value="[^"]*")/s', $html, $match);

        return $match[1] ?? '';
    }

    private function makeProduct(string $categoryName, string $name, string $variant, int $price, bool $active = true): Product
    {
        $category = $this->categories[$categoryName] ??= ProductCategory::create([
            'brand_id' => $this->brand->id,
            'name' => $categoryName,
            'code' => strtoupper(str_replace(' ', '-', $categoryName)),
            'is_active' => true,
        ]);

        $product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $category->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '-', $name)),
            'is_active' => $active,
        ]);
        $product->outlets()->sync([$this->outlet->id]);

        $productVariant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => $variant,
            'code' => strtoupper(str_replace(' ', '-', $name)).'-'.strtoupper($variant),
            'price' => $price,
            'price_dine_in' => $price,
            'price_delivery' => $price,
            'is_active' => true,
        ]);
        $productVariant->outlets()->sync([$this->outlet->id]);

        return $product;
    }
}
