<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Support\BackofficeNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * UX-A1: the sidebar is grouped and collapsible, but structurally the same menu: same links, same
 * routes, same active patterns, visible to every role exactly as before.
 */
class BackofficeNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** Every link the sidebar showed before UX-A1 (all roles saw all of them). */
    private const PREVIOUS_SIDEBAR_ROUTES = [
        'backoffice.index',
        'backoffice.outlets.index',
        'backoffice.warehouses.index',
        'backoffice.users.index',
        'backoffice.products.index',
        'backoffice.variants.index',
        'backoffice.menu-categories.index',
        'backoffice.ingredients.index',
        'backoffice.ingredient-categories.index',
        'backoffice.recipes.index',
        'backoffice.production-recipes.index',
        'backoffice.productions.index',
        'backoffice.stock-balances.index',
        'backoffice.transfers.index',
        'backoffice.purchase-history.index',
        'backoffice.stock-adjustments.index',
        'backoffice.transactions.index',
        'backoffice.promos.index',
        'backoffice.discounts.index',
        'backoffice.shifts.index',
    ];

    private const EXPECTED_GROUPS = [
        'sales' => ['Sales', ['backoffice.transactions.index', 'backoffice.shifts.index']],
        'menu-products' => ['Menu & Products', ['backoffice.products.index', 'backoffice.variants.index', 'backoffice.recipes.index', 'backoffice.menu-categories.index']],
        'promo-discount' => ['Promo & Discount', ['backoffice.promos.index', 'backoffice.discounts.index']],
        'ingredients-production' => ['Ingredients & Production', ['backoffice.ingredients.index', 'backoffice.ingredient-categories.index', 'backoffice.production-recipes.index', 'backoffice.productions.index']],
        'inventory' => ['Inventory', ['backoffice.stock-balances.index', 'backoffice.transfers.index', 'backoffice.purchase-history.index', 'backoffice.stock-adjustments.index']],
        'warehouse' => ['Warehouse', ['backoffice.warehouses.index']],
        'administration' => ['Administration', ['backoffice.outlets.index', 'backoffice.users.index']],
    ];

    // ---- definition --------------------------------------------------------------------------------

    public function test_every_previously_visible_link_is_still_in_the_navigation_and_nothing_was_added(): void
    {
        $routes = BackofficeNavigation::routeNames();

        $this->assertEqualsCanonicalizing(self::PREVIOUS_SIDEBAR_ROUTES, $routes);
        $this->assertSame(count($routes), count(array_unique($routes)), 'A route is linked twice.');
    }

    public function test_orphaned_pages_are_not_added_to_the_navigation_in_this_phase(): void
    {
        $routes = BackofficeNavigation::routeNames();

        $this->assertNotContains('backoffice.stock-movements.index', $routes);
        $this->assertNotContains('backoffice.warehouse-transfers.index', $routes);
    }

    public function test_every_navigation_route_name_exists(): void
    {
        foreach (BackofficeNavigation::routeNames() as $routeName) {
            $this->assertTrue(Route::has($routeName), "Route [{$routeName}] does not exist.");
        }
    }

    public function test_groups_and_their_order_match_the_information_architecture(): void
    {
        $navigation = BackofficeNavigation::definition();

        $this->assertSame('backoffice.index', $navigation['dashboard']['route']);

        $actual = collect($navigation['groups'])
            ->mapWithKeys(fn (array $group) => [$group['key'] => [$group['label'], collect($group['items'])->pluck('route')->all()]])
            ->all();

        $this->assertSame(self::EXPECTED_GROUPS, $actual);
    }

    public function test_active_patterns_are_the_same_ones_the_sidebar_used_before(): void
    {
        $navigation = BackofficeNavigation::definition();

        $this->assertSame(['backoffice.index'], $navigation['dashboard']['active_patterns']);

        foreach ($navigation['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $this->assertSame([str_replace('.index', '.*', $item['route'])], $item['active_patterns'], $item['label']);
            }
        }
    }

    // ---- active state ------------------------------------------------------------------------------

    public static function activeRouteProvider(): array
    {
        return [
            'dashboard' => ['backoffice.index', 'backoffice.index', null],
            'product list' => ['backoffice.products.index', 'backoffice.products.index', 'menu-products'],
            'product edit' => ['backoffice.products.edit', 'backoffice.products.index', 'menu-products'],
            'variant create' => ['backoffice.variants.create', 'backoffice.variants.index', 'menu-products'],
            'recipe edit' => ['backoffice.recipes.edit', 'backoffice.recipes.index', 'menu-products'],
            'menu category edit' => ['backoffice.menu-categories.edit', 'backoffice.menu-categories.index', 'menu-products'],
            'transactions show' => ['backoffice.transactions.show', 'backoffice.transactions.index', 'sales'],
            'shift show' => ['backoffice.shifts.show', 'backoffice.shifts.index', 'sales'],
            'promo edit' => ['backoffice.promos.edit', 'backoffice.promos.index', 'promo-discount'],
            'discount create' => ['backoffice.discounts.create', 'backoffice.discounts.index', 'promo-discount'],
            'ingredient import' => ['backoffice.ingredients.import', 'backoffice.ingredients.index', 'ingredients-production'],
            'ingredient category' => ['backoffice.ingredient-categories.index', 'backoffice.ingredient-categories.index', 'ingredients-production'],
            'production recipe edit' => ['backoffice.production-recipes.edit', 'backoffice.production-recipes.index', 'ingredients-production'],
            'production show' => ['backoffice.productions.show', 'backoffice.productions.index', 'ingredients-production'],
            'stock opname' => ['backoffice.stock-balances.opname.create', 'backoffice.stock-balances.index', 'inventory'],
            'transfer create' => ['backoffice.transfers.create', 'backoffice.transfers.index', 'inventory'],
            'purchase detail' => ['backoffice.purchase-history.show', 'backoffice.purchase-history.index', 'inventory'],
            'adjustment detail' => ['backoffice.stock-adjustments.show', 'backoffice.stock-adjustments.index', 'inventory'],
            'warehouse stock' => ['backoffice.warehouses.stock.index', 'backoffice.warehouses.index', 'warehouse'],
            'outlet edit' => ['backoffice.outlets.edit', 'backoffice.outlets.index', 'administration'],
            'user create' => ['backoffice.users.create', 'backoffice.users.index', 'administration'],
        ];
    }

    #[DataProvider('activeRouteProvider')]
    public function test_current_route_marks_exactly_one_item_active_and_opens_only_its_group(string $currentRoute, string $expectedItemRoute, ?string $expectedGroup): void
    {
        $navigation = BackofficeNavigation::resolve($this->requestFor($currentRoute));

        $activeItems = collect([$navigation['dashboard']])
            ->merge(collect($navigation['groups'])->flatMap(fn (array $group) => $group['items']))
            ->where('active', true)
            ->pluck('route')
            ->all();

        $this->assertSame([$expectedItemRoute], $activeItems);

        $openGroups = collect($navigation['groups'])->where('open', true)->pluck('key')->all();
        $this->assertSame($expectedGroup === null ? [] : [$expectedGroup], $openGroups);
    }

    public function test_pages_outside_the_menu_mark_nothing_active_and_open_no_group(): void
    {
        $navigation = BackofficeNavigation::resolve($this->requestFor('backoffice.stock-movements.index'));

        $this->assertFalse($navigation['dashboard']['active']);
        $this->assertSame([], collect($navigation['groups'])->where('open', true)->all());
    }

    public function test_resolve_without_a_route_does_not_fail(): void
    {
        $navigation = BackofficeNavigation::resolve(Request::create('/'));

        $this->assertFalse($navigation['dashboard']['active']);
        $this->assertSame([], collect($navigation['groups'])->where('open', true)->all());
    }

    // ---- rendered sidebar --------------------------------------------------------------------------

    public function test_rendered_sidebar_opens_the_active_group_server_side_and_marks_the_active_link(): void
    {
        [$owner] = $this->makeFixtures();

        $html = $this->actingAs($owner)->get(route('backoffice.products.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="sidebar-group-toggle-menu-products"\s+aria-expanded="true"\s+aria-controls="sidebar-group-menu-products"/', $html);
        $this->assertMatchesRegularExpression('/class="sidebar-section sidebar-group is-open has-active"\s+data-sidebar-group\s+data-sidebar-group-key="menu-products"/', $html);

        foreach (array_diff(array_keys(self::EXPECTED_GROUPS), ['menu-products']) as $closedGroup) {
            $this->assertMatchesRegularExpression('/id="sidebar-group-toggle-'.$closedGroup.'"\s+aria-expanded="false"/', $html, $closedGroup);
        }

        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('backoffice.products.index'), '#').'" class="sidebar-link active"\s+aria-current="page"#', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertSame(1, substr_count($html, 'class="sidebar-link active"'));
    }

    public function test_rendered_sidebar_keeps_mobile_drawer_markup_and_accessibility_essentials(): void
    {
        [$owner] = $this->makeFixtures();

        $html = $this->actingAs($owner)->get(route('backoffice.products.index'))->assertOk()->getContent();

        // Off-canvas drawer hooks used by the layout script (open, close, overlay, Esc).
        $this->assertStringContainsString('id="backoffice-mobile-menu-button"', $html);
        $this->assertStringContainsString('aria-label="Open back office menu"', $html);
        $this->assertStringContainsString('id="backoffice-sidebar-close"', $html);
        $this->assertStringContainsString('id="backoffice-sidebar-overlay"', $html);
        $this->assertStringContainsString('<aside class="sidebar" id="backoffice-sidebar">', $html);
        $this->assertStringContainsString("event.key === 'Escape'", $html);

        // Navigation landmark, and every toggle is a real button controlling an existing region.
        $this->assertStringContainsString('<nav class="sidebar-nav" aria-label="Back office navigation">', $html);
        $this->assertStringContainsString("document.documentElement.classList.add('bo-js')", $html);

        foreach (array_keys(self::EXPECTED_GROUPS) as $groupKey) {
            $this->assertMatchesRegularExpression('/<button type="button"\s+class="sidebar-group-toggle"\s+id="sidebar-group-toggle-'.$groupKey.'"/', $html);
            $this->assertStringContainsString('aria-controls="sidebar-group-'.$groupKey.'"', $html);
            $this->assertStringContainsString('id="sidebar-group-'.$groupKey.'" role="group" aria-labelledby="sidebar-group-toggle-'.$groupKey.'"', $html);
        }

        // Links still close the mobile drawer.
        $this->assertStringContainsString("document.querySelectorAll('.sidebar a')", $html);
    }

    public function test_no_role_based_hiding_every_role_sees_every_link(): void
    {
        [$owner, $outlet] = $this->makeFixtures();

        $adminOutlet = $this->makeUser('admin_outlet', $outlet);
        $staffGudang = $this->makeUser('staff_gudang', $outlet);

        // Each user on a page they may open; the sidebar must be identical for all of them.
        $pages = [
            [$owner, 'backoffice.products.index'],
            [$adminOutlet, 'backoffice.products.index'],
            [$staffGudang, 'backoffice.ingredients.index'],
        ];

        foreach ($pages as [$user, $page]) {
            $html = $this->actingAs($user)->get(route($page))->assertOk()->getContent();

            foreach (self::PREVIOUS_SIDEBAR_ROUTES as $routeName) {
                $this->assertStringContainsString('<a href="'.route($routeName).'" class="sidebar-link', $html, $user->username.' misses '.$routeName);
            }

            foreach (self::EXPECTED_GROUPS as [$label]) {
                $this->assertStringContainsString('<span class="sidebar-title">'.e($label).'</span>', $html);
            }
        }
    }

    // ---- helpers -----------------------------------------------------------------------------------

    private function requestFor(string $routeName): Request
    {
        $route = Route::getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "Route [{$routeName}] does not exist.");

        $request = Request::create('/'.ltrim($route->uri(), '/'));
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    /** @return array{0: User, 1: Outlet} */
    private function makeFixtures(): array
    {
        $outlet = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $category = ProductCategory::create(['brand_id' => $brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $product = Product::create(['brand_id' => $brand->id, 'product_category_id' => $category->id, 'name' => 'Kafei Susu', 'code' => 'KAFEI-SUSU', 'is_active' => true]);
        $product->outlets()->sync([$outlet->id]);

        return [$this->makeUser('owner', $outlet), $outlet];
    }

    private function makeUser(string $roleCode, Outlet $outlet): User
    {
        $user = User::create([
            'name' => $roleCode,
            'username' => $roleCode,
            'email' => $roleCode.'@example.test',
            'password' => 'password',
            'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id,
            'outlet_id' => $outlet->id,
            'is_active' => true,
        ]);
        $user->outlets()->sync([$outlet->id]);

        return $user;
    }
}
