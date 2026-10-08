<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Single definition of the Backoffice sidebar: one standalone Dashboard link plus collapsible groups.
 *
 * Variants are managed from the Product Workspace, so they have no sidebar entry (the Variant pages and
 * routes still exist and resolve; a Variant page simply marks no sidebar item active, like any other
 * page outside the menu).
 *
 * Structure only. Every item is shown to every Backoffice user exactly as before (each controller
 * stays the only access guard), and each item keeps the route and active pattern it already had.
 *
 * resolve() marks the item matching the current route as active and the group holding it as open,
 * so the sidebar is rendered server-side in its final state (no collapsed flash on load).
 */
class BackofficeNavigation
{
    /**
     * @return array{dashboard: array, groups: array<int, array{key: string, label: string, items: array<int, array>}>}
     */
    public static function definition(): array
    {
        return [
            'dashboard' => self::item('Dashboard', 'backoffice.index', 'backoffice.index', 'orange', '<path d="M4 20h16"></path><path d="M6 20V8l6-4 6 4v12"></path><path d="M9 20v-5h6v5"></path>'),
            'groups' => [
                self::group('sales', 'Sales', [
                    self::item('Transactions', 'backoffice.transactions.index', 'backoffice.transactions.*', 'green', '<path d="M4 7h16"></path><rect x="3" y="5" width="18" height="14" rx="3"></rect><path d="M7 15h3"></path><path d="M14 15h3"></path>'),
                    self::item('Shifts', 'backoffice.shifts.index', 'backoffice.shifts.*', 'blue', '<path d="M12 6v6l4 2"></path><circle cx="12" cy="12" r="8"></circle>'),
                ]),
                self::group('menu-products', 'Menu & Products', [
                    self::item('Products', 'backoffice.products.index', 'backoffice.products.*', 'blue', '<rect x="4" y="4" width="16" height="16" rx="3"></rect><path d="M8 9h8"></path><path d="M8 13h8"></path><path d="M8 17h4"></path>'),
                    self::item('Recipes', 'backoffice.recipes.index', 'backoffice.recipes.*', 'orange', '<path d="M6 4h12"></path><path d="M8 4v16"></path><path d="M16 4v16"></path><path d="M8 9h8"></path><path d="M8 14h8"></path>'),
                    self::item('Menu Categories', 'backoffice.menu-categories.index', 'backoffice.menu-categories.*', 'blue', '<path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h10"></path>'),
                ]),
                self::group('promo-discount', 'Promo & Discount', [
                    self::item('Promos', 'backoffice.promos.index', 'backoffice.promos.*', 'violet', '<path d="M4 7h16"></path><path d="M7 7l2-3h6l2 3"></path><rect x="4" y="7" width="16" height="13" rx="3"></rect><path d="M8 13h8"></path><path d="M8 16h5"></path>'),
                    self::item('Discounts', 'backoffice.discounts.index', 'backoffice.discounts.*', 'orange', '<path d="M4 7h16"></path><path d="M7 7l2-3h6l2 3"></path><rect x="4" y="7" width="16" height="13" rx="3"></rect><path d="M9 13h6"></path><path d="M12 10v6"></path>'),
                ]),
                self::group('ingredients-production', 'Ingredients & Production', [
                    self::item('Ingredients', 'backoffice.ingredients.index', 'backoffice.ingredients.*', 'green', '<path d="M7 4h10"></path><path d="M9 4v5l-4 7a3 3 0 0 0 2.6 4.5h8.8A3 3 0 0 0 19 16l-4-7V4"></path><path d="M8 14h8"></path>'),
                    self::item('Ingredient Categories', 'backoffice.ingredient-categories.index', 'backoffice.ingredient-categories.*', 'green', '<path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h10"></path>'),
                    self::item('Production Recipes', 'backoffice.production-recipes.index', 'backoffice.production-recipes.*', 'violet', '<path d="M7 4h10"></path><path d="M9 4v4"></path><path d="M15 4v4"></path><path d="M5 10h14"></path><path d="M6 20h12"></path><path d="M8 14h8"></path>'),
                    self::item('Productions', 'backoffice.productions.index', 'backoffice.productions.*', 'green', '<path d="M4 7h16"></path><path d="M6 7v10h12V7"></path><path d="M9 12h6"></path><path d="M12 9v6"></path>'),
                ]),
                self::group('inventory', 'Inventory', [
                    self::item('Inventory Control', 'backoffice.stock-balances.index', 'backoffice.stock-balances.*', 'blue', '<rect x="4" y="4" width="16" height="16" rx="3"></rect><path d="M8 9h8"></path><path d="M8 13h8"></path><path d="M8 17h4"></path>'),
                    self::item('Transfers', 'backoffice.transfers.index', 'backoffice.transfers.*', 'orange', '<path d="M7 7h11"></path><path d="m14 4 4 3-4 3"></path><path d="M17 17H6"></path><path d="m10 14-4 3 4 3"></path>'),
                    self::item('Purchase History', 'backoffice.purchase-history.index', 'backoffice.purchase-history.*', 'green', '<path d="M5 4h14v16H5z"></path><path d="M8 8h8M8 12h8M8 16h5"></path>'),
                    self::item('Adjustment History', 'backoffice.stock-adjustments.index', 'backoffice.stock-adjustments.*', 'orange', '<path d="M4 7h10"></path><path d="M18 7h2"></path><circle cx="16" cy="7" r="2"></circle><path d="M4 17h2"></path><path d="M10 17h10"></path><circle cx="8" cy="17" r="2"></circle>'),
                ]),
                self::group('warehouse', 'Warehouse', [
                    self::item('Warehouses', 'backoffice.warehouses.index', 'backoffice.warehouses.*', 'green', '<path d="M3 10.5 12 5l9 5.5"></path><path d="M5 9.5V19h14V9.5"></path><path d="M9 19v-5h6v5"></path>'),
                ]),
                self::group('administration', 'Administration', [
                    self::item('Outlets', 'backoffice.outlets.index', 'backoffice.outlets.*', 'orange', '<path d="M4 20h16"></path><path d="M6 20V8l6-4 6 4v12"></path><path d="M9 20v-5h6v5"></path>'),
                    self::item('Users', 'backoffice.users.index', 'backoffice.users.*', 'violet', '<path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path><circle cx="9.5" cy="7" r="4"></circle><path d="M20 8v6"></path><path d="M17 11h6"></path>'),
                ]),
            ],
        ];
    }

    /**
     * The definition with `active` set on the item for the current route and `open`/`active` on its group.
     */
    public static function resolve(?Request $request = null): array
    {
        $request ??= request();
        $navigation = self::definition();

        $navigation['dashboard']['active'] = self::matches($request, $navigation['dashboard']);

        foreach ($navigation['groups'] as &$group) {
            foreach ($group['items'] as &$item) {
                $item['active'] = self::matches($request, $item);
            }
            unset($item);

            $group['active'] = collect($group['items'])->contains('active', true);
            $group['open'] = $group['active'];
        }
        unset($group);

        return $navigation;
    }

    /**
     * Every route the sidebar links to, in display order.
     *
     * @return string[]
     */
    public static function routeNames(): array
    {
        $navigation = self::definition();

        return collect([$navigation['dashboard']])
            ->merge(collect($navigation['groups'])->flatMap(fn (array $group) => $group['items']))
            ->pluck('route')
            ->all();
    }

    private static function matches(Request $request, array $item): bool
    {
        return $request->route() !== null && $request->routeIs(...$item['active_patterns']);
    }

    private static function group(string $key, string $label, array $items): array
    {
        return ['key' => $key, 'label' => $label, 'items' => $items];
    }

    /** $icon is trusted static SVG markup from this file only. */
    private static function item(string $label, string $route, string|array $activePatterns, string $tone, string $icon): array
    {
        return [
            'label' => $label,
            'route' => $route,
            'active_patterns' => (array) $activePatterns,
            'tone' => $tone,
            'icon' => $icon,
        ];
    }
}
