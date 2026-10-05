<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promo;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\StockBalance;
use App\Models\User;
use App\Support\BackofficeReturnUrl;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Read model of the Product Workspace: everything one Product page shows, built from the existing
 * rules (BackofficeOutletContext, SaleEligibilityService, RecipeAccessPolicy). It never writes.
 *
 * Bind per request (it carries the request's RecipeAccessPolicy, which memoises the user context).
 */
class ProductWorkspace
{
    public const SECTIONS = [
        'general' => 'General',
        'outlets' => 'Outlets',
        'variants' => 'Variants & Pricing',
        'recipe' => 'Recipe',
        'stock' => 'Stock & Readiness',
        'promo' => 'Promo',
    ];

    public const DEFAULT_SECTION = 'general';

    /** Mirrors PromoViewController::authorizeAccess() (primary role). */
    private const PROMO_ROLES = ['owner', 'admin_pusat', 'staff_gudang'];

    /** Roles of Inventory Control / Stock Movements (primary role), as those controllers check. */
    private const STOCK_ROLES = ['owner', 'admin_pusat', 'admin_outlet', 'staff_gudang'];

    public const AMBIGUOUS_MESSAGE = 'Lebih dari satu Recipe aktif. Perlu review data terlebih dahulu.';

    /** Where in the workspace the blocking reason can be looked at (null: nothing to open). */
    private const REASON_SECTIONS = [
        'product_inactive' => 'general',
        'product_not_at_outlet' => 'outlets',
        'variant_inactive' => 'variants',
        'variant_not_at_outlet' => 'variants',
        'recipe_missing' => 'recipe',
        'recipe_inactive' => 'recipe',
        'recipe_ambiguous' => 'recipe',
        'recipe_product_mismatch' => 'recipe',
        'recipe_empty' => 'recipe',
        'ingredient_invalid' => 'recipe',
        'ingredient_inactive' => 'recipe',
        'ingredient_not_at_outlet' => 'recipe',
        'ingredient_qty_invalid' => 'recipe',
    ];

    private const DAY_LABELS = [
        'sunday' => 'Min', 'monday' => 'Sen', 'tuesday' => 'Sel', 'wednesday' => 'Rab',
        'thursday' => 'Kam', 'friday' => 'Jum', 'saturday' => 'Sab',
    ];

    public function __construct(
        private readonly BackofficeOutletContext $context,
        private readonly SaleEligibilityService $eligibility,
        private readonly RecipeAccessPolicy $recipePolicy,
        private readonly IngredientWriter $ingredientWriter,
    ) {}

    public static function normalizeSection(mixed $section): string
    {
        return is_string($section) && array_key_exists($section, self::SECTIONS) ? $section : self::DEFAULT_SECTION;
    }

    /**
     * The workspace URL for one section, carrying the list context (return_to) along. Used as the
     * return_to of every editor the workspace links out to, so saving there comes back here.
     */
    public static function url(Product $product, string $section, ?string $listReturnTo): string
    {
        $params = [$product->id, 'section' => self::normalizeSection($section)];

        if ($listReturnTo !== null) {
            $params[BackofficeReturnUrl::FIELD] = $listReturnTo;
        }

        return route('backoffice.products.edit', $params, false);
    }

    /**
     * Everything the workspace page (and each re-rendered section) needs. $request carries return_to,
     * from the query string on the page itself or from the hidden form field on a section save.
     */
    public function viewData(Request $request, Product $product, User $user, mixed $section): array
    {
        $listReturnTo = BackofficeReturnUrl::fromRequest($request);
        $product->refresh();

        return [
            'user' => $user,
            'product' => $product,
            'section' => self::normalizeSection($section),
            'sections' => self::SECTIONS,
            'workspaceReturnTo' => $listReturnTo,
            'closeUrl' => BackofficeReturnUrl::resolve($request, 'backoffice.products.index', [], 'product-'.$product->id),
            'brands' => Brand::orderBy('name')->get(),
            'categories' => ProductCategory::where('is_active', true)->orWhere('id', $product->product_category_id)->orderBy('name')->get(),
            'categoryBrands' => Brand::where('is_active', true)->orderBy('name')->get(),
            'canCreateCategory' => CategoryWriter::canManage($user),
            'workspace' => $this->build($product, $user, $listReturnTo),
        ];
    }

    public function build(Product $product, User $user, ?string $listReturnTo): array
    {
        $product->load([
            'brand',
            'category',
            'outlets' => fn ($query) => $query->orderBy('name'),
            'variants' => fn ($query) => $query->orderBy('name'),
            'variants.outlets' => fn ($query) => $query->orderBy('name'),
        ]);

        $variants = $product->variants;
        // Variants already belong to this loaded Product: hand it over so no policy or rule re-reads it per Variant.
        $variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));
        $variantIds = $variants->pluck('id')->map(fn ($id) => (int) $id)->all();
        $productOutletIds = $product->outlets->pluck('id')->map(fn ($id) => (int) $id);
        $readinessOutlets = $this->readinessOutlets($product, $user);
        $activeOutlet = $this->context->activeOutlet($user);
        // An Active Outlet the Product is not assigned to still needs an answer (Cashier would refuse it).
        $foreignOutlet = $activeOutlet && ! $readinessOutlets->contains('id', $activeOutlet->id) ? $activeOutlet : null;
        $statuses = $this->eligibilityByOutlet($variantIds, $readinessOutlets->pluck('id')->push($foreignOutlet?->id)->filter()->all());
        $recipes = $this->recipesByVariant($variantIds);
        $returnUrl = fn (string $section) => self::url($product, $section, $listReturnTo);
        $ingredientLinks = $this->ingredientLinker($product, $user, $returnUrl);

        return [
            'header' => [
                'variant_count' => $variants->count(),
                'active_variant_count' => $variants->where('is_active', true)->count(),
                'outlet_count' => $productOutletIds->count(),
                'sellable' => $this->sellableSummary($variants, $readinessOutlets, $statuses),
            ],
            'outlets' => $this->outletsSection($product, $user),
            'variants' => $this->variantsSection($product, $variants, $readinessOutlets, $statuses, $returnUrl('variants')),
            'recipes' => $this->recipeSection($product, $variants, $recipes, $user, $returnUrl('recipe'), $ingredientLinks),
            'stock' => $this->stockSection($product, $user, $variants, $recipes, $readinessOutlets, $statuses, $foreignOutlet, $ingredientLinks, $returnUrl('stock')),
            'promos' => $this->promoSection($product, $variants, $user, $returnUrl('promo')),
            'links' => [
                'variants_edit' => $variants->isNotEmpty()
                    ? route('backoffice.variants.edit', [$variants->first()->id, 'return_to' => $returnUrl('variants')], false)
                    : null,
                'variants_create' => route('backoffice.variants.create', ['return_to' => $returnUrl('variants')], false),
                'variant_create_form' => route('backoffice.products.workspace.variants.create-form', $product, false),
                'stock_balances' => route('backoffice.stock-balances.index', [], false),
                'can_manage_ingredients' => IngredientWriter::hasIngredientRole($user),
                'ingredient_create' => route('backoffice.ingredients.create', ['return_to' => $returnUrl('stock')], false),
                'ingredient_create_form' => route('backoffice.products.workspace.ingredients.create-form', $product, false),
            ],
        ];
    }

    /** Active Product outlets inside the user's current view scope (Active Outlet, or what they can access). */
    public function readinessOutlets(Product $product, User $user): Collection
    {
        $scope = $this->context->scopeOutletIds($user);

        return $product->outlets
            ->filter(fn (Outlet $outlet) => (bool) $outlet->is_active)
            ->filter(fn (Outlet $outlet) => $scope === null || in_array((int) $outlet->id, $scope, true))
            ->values();
    }

    private function outletsSection(Product $product, User $user): array
    {
        $accessible = $this->context->accessibleOutlets($user);
        $accessibleIds = $accessible->pluck('id')->map(fn ($id) => (int) $id);
        $assignedIds = $product->outlets->pluck('id')->map(fn ($id) => (int) $id);

        return [
            'accessible' => $accessible->map(fn (Outlet $outlet) => [
                'id' => (int) $outlet->id,
                'name' => $outlet->name,
                'assigned' => $assignedIds->contains((int) $outlet->id),
            ])->values()->all(),
            // Assigned outside the user's access: shown, never editable, always kept on save.
            'locked' => $product->outlets
                ->reject(fn (Outlet $outlet) => $accessibleIds->contains((int) $outlet->id))
                ->map(fn (Outlet $outlet) => ['id' => (int) $outlet->id, 'name' => $outlet->name, 'is_active' => (bool) $outlet->is_active])
                ->values()->all(),
            'assigned' => $product->outlets->map(fn (Outlet $outlet) => ['id' => (int) $outlet->id, 'name' => $outlet->name, 'is_active' => (bool) $outlet->is_active])->values()->all(),
            'variant_matrix' => $product->variants->map(fn (ProductVariant $variant) => [
                'id' => (int) $variant->id,
                'name' => $variant->name,
                'is_active' => (bool) $variant->is_active,
                'outlet_ids' => $variant->outlets->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ])->values()->all(),
        ];
    }

    private function variantsSection(Product $product, Collection $variants, Collection $readinessOutlets, array $statuses, string $returnTo): array
    {
        return $variants->map(function (ProductVariant $variant) use ($product, $readinessOutlets, $statuses, $returnTo) {
            $eligibleAt = $readinessOutlets->filter(fn (Outlet $outlet) => $statuses[$outlet->id][$variant->id]['eligible'] ?? false);
            $dineIn = (float) ($variant->price_dine_in ?? $variant->price ?? 0);

            return [
                'id' => (int) $variant->id,
                'name' => $variant->name,
                'code' => $variant->code,
                'is_active' => (bool) $variant->is_active,
                'price_dine_in' => $dineIn,
                'price_delivery' => (float) ($variant->price_delivery ?? $variant->price ?? 0),
                // Legacy `price` normally mirrors dine-in; only worth showing when an old row differs.
                'legacy_price' => $variant->price !== null && abs((float) $variant->price - $dineIn) > 0.004 ? (float) $variant->price : null,
                'outlets' => $variant->outlets->pluck('name')->all(),
                'sellable_count' => $eligibleAt->count(),
                'readiness_count' => $readinessOutlets->count(),
                'form_url' => route('backoffice.products.workspace.variants.edit-form', [$product, $variant], false),
                'legacy_edit_url' => route('backoffice.variants.edit', [$variant->id, 'return_to' => $returnTo], false),
                'deactivate_url' => route('backoffice.products.workspace.variants.deactivate', [$product, $variant], false),
            ];
        })->values()->all();
    }

    /**
     * Edit links for an Ingredient shown in this workspace (drawer + classic page), or null when the
     * user may not edit it here. Ingredients are global; this only decides who may open them.
     */
    private function ingredientLinker(Product $product, User $user, callable $returnUrl): callable
    {
        $canManage = IngredientWriter::hasIngredientRole($user);
        $memo = [];

        return function ($ingredient, string $section) use ($product, $user, $returnUrl, $canManage, &$memo) {
            if (! $canManage || ! $ingredient) {
                return null;
            }

            $memo[$ingredient->id] ??= $this->ingredientWriter->canEdit($user, $ingredient);

            return $memo[$ingredient->id] ? [
                'form_url' => route('backoffice.products.workspace.ingredients.edit-form', [$product, $ingredient, 'return_section' => $section], false),
                'legacy_edit_url' => route('backoffice.ingredients.edit', [$ingredient->id, 'return_to' => $returnUrl($section)], false),
            ] : null;
        };
    }

    private function recipeSection(Product $product, Collection $variants, Collection $recipes, User $user, string $returnTo, callable $ingredientLinks): array
    {
        return $variants->map(function (ProductVariant $variant) use ($product, $recipes, $user, $returnTo, $ingredientLinks) {
            $variantRecipes = $recipes->get($variant->id, collect());
            $mutation = $this->recipePolicy->mutationStatus($user, $variant);
            $status = self::recipeStatus($variantRecipes);
            $activeIds = $variantRecipes->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->values();
            // Inline changes need the Recipe policy AND a non-ambiguous Variant. Ambiguous stays read-only.
            $editable = $mutation['allowed'] && $status !== 'ambiguous';
            $variantOutlets = $variant->outlets;
            $required = $variantOutlets->pluck('id')->map(fn ($id) => (int) $id);

            return [
                'variant_id' => (int) $variant->id,
                'variant_name' => $variant->name,
                'variant_active' => (bool) $variant->is_active,
                'variant_outlets' => $variantOutlets->pluck('name')->all(),
                'status' => $status,
                'can_mutate' => $mutation['allowed'],
                'editable' => $editable,
                'mutation_message' => $mutation['message'],
                'active_recipe_ids' => $activeIds->all(),
                'inactive_count' => $variantRecipes->where('is_active', false)->count(),
                'recipes' => $variantRecipes->map(function (Recipe $recipe) use ($product, $variant, $editable, $activeIds, $required, $variantOutlets, $returnTo, $ingredientLinks) {
                    $owned = (int) $recipe->product_id === (int) $product->id;
                    $inline = $editable && $owned;
                    $ingredientCounts = $recipe->items->countBy('ingredient_id');

                    return [
                        'id' => (int) $recipe->id,
                        'name' => $recipe->name,
                        'is_active' => (bool) $recipe->is_active,
                        'items_count' => (int) $recipe->items_count,
                        'product_mismatch' => ! $owned,
                        // Read-only display of the stored rows: qty and unit exactly as stored, warnings only.
                        'items' => $recipe->items->map(function (RecipeItem $item) use ($required, $variantOutlets, $ingredientCounts, $ingredientLinks) {
                            $ingredient = $item->ingredient;
                            $missing = $ingredient ? $required->diff($ingredient->outlets->pluck('id')->map(fn ($id) => (int) $id)) : collect();

                            return [
                                'name' => $ingredient?->name ?? 'Ingredient tidak valid',
                                'qty' => (string) $item->getRawOriginal('qty'),
                                'unit' => $item->unit,
                                'is_active' => (bool) ($ingredient?->is_active),
                                'missing_outlets' => $variantOutlets->whereIn('id', $missing->all())->pluck('name')->values()->all(),
                                'duplicate' => ($ingredientCounts[$item->ingredient_id] ?? 0) > 1,
                                'unit_differs' => $ingredient && $item->unit !== null && $ingredient->unit !== null && $item->unit !== $ingredient->unit,
                                'ingredient_unit' => $ingredient?->unit,
                                'links' => $ingredientLinks($ingredient, 'recipe'),
                            ];
                        })->values()->all(),
                        'edit_url' => route('backoffice.recipes.edit', [$recipe->id, 'return_to' => $returnTo], false),
                        'form_url' => $inline ? route('backoffice.products.workspace.recipes.edit-form', [$product, $variant, $recipe], false) : null,
                        'activate_url' => $inline && ! $recipe->is_active && $activeIds->isEmpty()
                            ? route('backoffice.products.workspace.recipes.activate', [$product, $variant, $recipe], false) : null,
                        'deactivate_url' => $inline && $recipe->is_active
                            ? route('backoffice.products.workspace.recipes.deactivate', [$product, $variant, $recipe], false) : null,
                    ];
                })->values()->all(),
                'create_url' => $variantRecipes->isEmpty() && $mutation['allowed']
                    ? route('backoffice.recipes.create', ['return_to' => $returnTo], false)
                    : null,
                'create_form_url' => $variantRecipes->isEmpty() && $editable
                    ? route('backoffice.products.workspace.recipes.create-form', [$product, $variant], false)
                    : null,
            ];
        })->values()->all();
    }

    /**
     * none | inactive | ambiguous | empty | valid. Several active Recipes are reported as ambiguous and
     * never resolved to one (same rule as SaleEligibilityService).
     */
    public static function recipeStatus(Collection $variantRecipes): string
    {
        if ($variantRecipes->isEmpty()) {
            return 'none';
        }

        $active = $variantRecipes->where('is_active', true);

        if ($active->isEmpty()) {
            return 'inactive';
        }

        if ($active->count() > 1) {
            return 'ambiguous';
        }

        return (int) $active->first()->items_count === 0 ? 'empty' : 'valid';
    }

    /**
     * Stock & Readiness, one entry per Variant. Two separate answers, never merged:
     *
     *  - CONFIGURATION READINESS: the sale rules of SaleEligibilityService (Product/Variant/outlet/Recipe/
     *    Ingredient validity), per outlet. Stock on hand plays no part in it.
     *  - STOCK OBSERVATION: balance vs. Recipe need and minimum, per Ingredient and outlet. Informational
     *    only: zero, negative or missing stock never changes the configuration answer.
     *
     * Ambiguous Variants (several active Recipes) get no stock rows: no Recipe is picked for them.
     */
    private function stockSection(Product $product, User $user, Collection $variants, Collection $recipes, Collection $readinessOutlets, array $statuses, ?Outlet $foreignOutlet, callable $ingredientLinks, string $returnTo): array
    {
        $outletIds = $readinessOutlets->pluck('id')->map(fn ($id) => (int) $id)->all();

        // The one active Recipe a Variant's stock context is read from (null: none, inactive or ambiguous).
        $recipeOf = $variants->mapWithKeys(function (ProductVariant $variant) use ($recipes) {
            $active = $recipes->get($variant->id, collect())->where('is_active', true);

            return [$variant->id => $active->count() === 1 ? $active->first() : null];
        });

        $ingredientIds = $recipeOf->filter()
            ->flatMap(fn (Recipe $recipe) => $recipe->items->pluck('ingredient_id'))
            ->unique()->values()->all();

        $balances = $ingredientIds === [] || $outletIds === []
            ? collect()
            : StockBalance::where('location_type', 'outlet')
                ->whereIn('location_id', $outletIds)
                ->whereIn('ingredient_id', $ingredientIds)
                ->get()
                ->keyBy(fn (StockBalance $balance) => $balance->ingredient_id.':'.$balance->location_id);

        $canSeeStock = in_array($user->role?->code, self::STOCK_ROLES, true);
        $activeOutlet = $this->context->activeOutlet($user);
        $allowedOutletCount = $this->context->accessibleOutlets($user)
            ->filter(fn (Outlet $outlet) => $product->outlets->contains('id', $outlet->id))->count();

        $rows = $variants->map(function (ProductVariant $variant) use ($recipes, $recipeOf, $readinessOutlets, $statuses, $balances, $ingredientLinks, $returnTo, $canSeeStock) {
            $variantRecipes = $recipes->get($variant->id, collect());
            $recipeStatus = self::recipeStatus($variantRecipes);
            $recipe = $recipeOf[$variant->id];
            $assignedIds = $variant->outlets->pluck('id')->map(fn ($id) => (int) $id);

            $outlets = $readinessOutlets->map(function (Outlet $outlet) use ($variant, $statuses, $recipe, $assignedIds, $balances, $ingredientLinks, $returnTo, $canSeeStock) {
                $status = $statuses[$outlet->id][$variant->id] ?? ['eligible' => false, 'reason' => null, 'message' => null];
                $items = [];

                // A Variant that is not sold at the outlet has no stock context there.
                if ($recipe && $assignedIds->contains((int) $outlet->id)) {
                    $counts = $recipe->items->countBy('ingredient_id');

                    foreach ($recipe->items as $item) {
                        $ingredient = $item->ingredient;

                        if (! $ingredient) {
                            $items[] = ['valid' => false, 'name' => 'Ingredient tidak valid'];

                            continue;
                        }

                        $balance = $balances->get($ingredient->id.':'.$outlet->id);
                        $qty = $balance ? (float) $balance->qty_on_hand : null;
                        $minimum = (float) ($ingredient->minimum_stock ?? 0);
                        $available = $ingredient->outlets->contains('id', $outlet->id);

                        $items[] = [
                            'valid' => true,
                            'ingredient_id' => (int) $ingredient->id,
                            'name' => $ingredient->name,
                            'is_active' => (bool) $ingredient->is_active,
                            'available' => $available,
                            // Recipe need exactly as stored, unit exactly as stored; nothing is converted.
                            'need' => (string) $item->getRawOriginal('qty'),
                            'need_unit' => $item->unit,
                            'unit' => $ingredient->unit,
                            'unit_differs' => $item->unit !== null && $ingredient->unit !== null && $item->unit !== $ingredient->unit,
                            'duplicate' => ($counts[$item->ingredient_id] ?? 0) > 1,
                            'qty' => $qty,
                            'minimum' => $minimum,
                            'state' => self::stockState($qty, $minimum),
                            'links' => $ingredientLinks($ingredient, 'stock'),
                            'stock_url' => $canSeeStock ? route('backoffice.stock-balances.index', ['ingredient_id' => $ingredient->id, 'return_to' => $returnTo], false) : null,
                            'movement_url' => $canSeeStock ? route('backoffice.stock-movements.index', ['ingredient_id' => $ingredient->id, 'return_to' => $returnTo], false) : null,
                        ];
                    }
                }

                $attention = collect($items)->filter(fn (array $row) => $row['valid'] && $row['available'] && $row['state'] !== 'normal')->count();

                return [
                    'id' => (int) $outlet->id,
                    'name' => $outlet->name,
                    'eligible' => (bool) $status['eligible'],
                    'reason' => $status['reason'],
                    'message' => $status['message'],
                    'items' => $items,
                    'attention' => $attention,
                ];
            })->values();

            $ready = $outlets->where('eligible', true)->count();
            $issues = $outlets->reject(fn (array $outlet) => $outlet['eligible'])
                ->groupBy('reason')
                ->map(fn (Collection $group, $reason) => [
                    'reason' => $reason,
                    'message' => $reason === 'recipe_ambiguous' ? self::AMBIGUOUS_MESSAGE : $group->first()['message'],
                    'outlets' => $group->pluck('name')->all(),
                    'section' => self::REASON_SECTIONS[$reason] ?? null,
                ])->values()->all();

            $ingredientCount = $recipe ? $recipe->items->pluck('ingredient_id')->unique()->count() : 0;
            $attention = $outlets->sum('attention');

            return [
                'id' => (int) $variant->id,
                'name' => $variant->name,
                'is_active' => (bool) $variant->is_active,
                'recipe_status' => $recipeStatus,
                'configuration' => match (true) {
                    $outlets->isEmpty() => 'none',
                    $ready === $outlets->count() => 'ready',
                    $ready === 0 => 'blocked',
                    default => 'partial',
                },
                'ready_outlets' => $ready,
                'outlet_count' => $outlets->count(),
                'issues' => $issues,
                'ingredient_count' => $ingredientCount,
                'stock_context' => $recipe !== null && $ingredientCount > 0,
                'attention' => (int) $attention,
                'outlets' => $outlets->all(),
            ];
        })->values();

        return [
            'scope' => [
                'active_outlet' => $activeOutlet?->name,
                'other_outlets' => $activeOutlet ? max(0, $allowedOutletCount - 1) : 0,
            ],
            'outlets' => $readinessOutlets->map(fn (Outlet $outlet) => ['id' => (int) $outlet->id, 'name' => $outlet->name])->values()->all(),
            'foreign_outlet' => $foreignOutlet ? [
                'name' => $foreignOutlet->name,
                'messages' => $variants->map(fn (ProductVariant $variant) => ($statuses[$foreignOutlet->id][$variant->id]['message'] ?? null))->filter()->unique()->values()->all(),
            ] : null,
            'summary' => [
                'variants' => $variants->count(),
                'ready_pairs' => $rows->sum('ready_outlets'),
                'total_pairs' => $rows->sum('outlet_count'),
                'attention' => (int) $rows->sum('attention'),
            ],
            'variants' => $rows->all(),
            'links' => ['stock_balances' => $canSeeStock ? route('backoffice.stock-balances.index', ['return_to' => $returnTo], false) : null],
        ];
    }

    /** none (no balance row) | negative | zero | low (at or under the minimum) | normal. Same thresholds as Inventory Control. */
    public static function stockState(?float $qty, float $minimum): string
    {
        return match (true) {
            $qty === null => 'none',
            $qty < -0.004 => 'negative',
            abs($qty) < 0.005 => 'zero',
            $qty <= $minimum => 'low',
            default => 'normal',
        };
    }

    /** ACTIVE | UPCOMING | EXPIRED | INACTIVE from the Promo's own fields (the dates the Cashier checks). */
    public static function promoState(Promo $promo, ?CarbonInterface $now = null): array
    {
        $today = ($now ?? now())->toDateString();

        if (! $promo->is_active || $promo->status !== 'active') {
            return ['key' => 'inactive', 'label' => match ($promo->status) {
                'draft' => 'Draft',
                'discontinued' => 'Dihentikan',
                default => 'Nonaktif',
            }];
        }

        if ($promo->end_date && $promo->end_date->toDateString() < $today) {
            return ['key' => 'expired', 'label' => 'Berakhir'];
        }

        if ($promo->start_date && $promo->start_date->toDateString() > $today) {
            return ['key' => 'upcoming', 'label' => 'Akan datang'];
        }

        return ['key' => 'active', 'label' => 'Aktif'];
    }

    /** Whether the Promo's day/time window includes the current moment (as the Cashier checks it). */
    private static function promoWithinWindow(Promo $promo, CarbonInterface $now): bool
    {
        $time = $now->format('H:i:s');
        $days = $promo->active_days ?? [];

        return ! (($promo->start_time && $promo->start_time > $time)
            || ($promo->end_time && $promo->end_time < $time)
            || (! empty($days) && ! in_array(strtolower($now->format('l')), $days, true)));
    }

    private static function plainNumber(mixed $value): string
    {
        $text = number_format((float) $value, 2, ',', '.');

        return str_contains($text, ',') ? rtrim(rtrim($text, '0'), ',') : $text;
    }

    /**
     * Promos that use a Variant of this Product, as far as the user may see them (same rule as the Promo
     * list: a limited role sees Promos at its outlets, an Active Outlet narrows to that outlet). Read-only:
     * nothing here changes a Promo or its outlets, and a Promo is only ever edited in the Promo editor.
     */
    private function promoSection(Product $product, Collection $variants, User $user, string $returnTo): array
    {
        $variantIds = $variants->pluck('id')->map(fn ($id) => (int) $id)->all();
        $canEdit = in_array($user->role?->code, self::PROMO_ROLES, true);

        $empty = [
            'can_edit' => $canEdit,
            'create_url' => null,
            'variant_create' => [],
            'scope_note' => null,
            'summary' => ['total' => 0, 'active' => 0, 'upcoming' => 0, 'expired' => 0, 'inactive' => 0],
            'promos' => [],
        ];

        if ($variantIds === []) {
            return $empty;
        }

        $fullAccess = $user->isFullAccessUser();
        $accessibleIds = $this->context->accessibleOutlets($user)->pluck('id')->map(fn ($id) => (int) $id);
        $activeOutletId = $this->context->activeOutletId($user);
        $now = now();
        $productOutletIds = $product->outlets->pluck('id')->map(fn ($id) => (int) $id);
        $variantNames = $variants->pluck('name', 'id');
        $variantOutletIds = $variants->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => $variant->outlets->pluck('id')->map(fn ($id) => (int) $id)]);

        $promos = Promo::with([
            'outlets' => fn ($query) => $query->orderBy('name'),
            'requirements.variant.product', 'rewards.variant.product', 'requirementVariant.product', 'rewardVariant.product',
        ])->referencingVariants($variantIds)->orderBy('name')->get();

        // What this user may see at all, then what the Active Outlet narrows it to.
        $visible = $fullAccess ? $promos : $promos->filter(function (Promo $promo) use ($accessibleIds) {
            return $promo->outlets->isEmpty() || $promo->outlets->pluck('id')->map(fn ($id) => (int) $id)->intersect($accessibleIds)->isNotEmpty();
        });
        $shown = $activeOutletId
            ? $visible->filter(fn (Promo $promo) => $promo->outlets->contains('id', $activeOutletId))
            : $visible;
        $narrowed = $visible->count() - $shown->count();

        // Live Promos first, then upcoming, expired and inactive; by name within each.
        $rank = ['active' => 0, 'upcoming' => 1, 'expired' => 2, 'inactive' => 3];
        $shown = $shown->sortBy(fn (Promo $promo) => [$rank[self::promoState($promo, $now)['key']], mb_strtolower($promo->name)])->values();

        $rows = $shown->values()->map(function (Promo $promo) use ($variantIds, $variantNames, $variantOutletIds, $productOutletIds, $fullAccess, $accessibleIds, $canEdit, $returnTo, $now) {
            $state = self::promoState($promo, $now);
            $used = collect();
            $requirementTexts = [];
            $rewardTexts = [];
            $label = fn (?ProductVariant $variant, ?int $id) => $variant
                ? (in_array((int) $variant->id, $variantIds, true) ? $variant->name : trim(($variant->product?->name ?? '').' - '.$variant->name, ' -'))
                : 'Variant #'.$id;

            foreach ($promo->requirements as $requirement) {
                $used->push(['variant_id' => (int) $requirement->product_variant_id, 'role' => 'Syarat']);
                $requirementTexts[] = $label($requirement->variant, (int) $requirement->product_variant_id).' ×'.self::plainNumber($requirement->qty);
            }
            foreach ($promo->rewards as $reward) {
                if ($reward->product_variant_id) {
                    $used->push(['variant_id' => (int) $reward->product_variant_id, 'role' => 'Reward']);
                }
                $rewardTexts[] = self::rewardText($reward->reward_type, $reward->reward_value, $reward->variant ? $label($reward->variant, (int) $reward->product_variant_id) : null, $reward->qty);
            }

            // Old Promos only have the legacy columns (the rule rows mirror them when both exist); the
            // columns still count as usage so a Promo found through them is explained.
            if ($promo->requirement_product_variant_id) {
                $used->push(['variant_id' => (int) $promo->requirement_product_variant_id, 'role' => 'Syarat']);

                if ($promo->requirements->isEmpty()) {
                    $requirementTexts[] = $label($promo->requirementVariant, (int) $promo->requirement_product_variant_id).' ×'.self::plainNumber($promo->requirement_qty ?? 1);
                }
            }
            if ($promo->reward_product_variant_id) {
                $used->push(['variant_id' => (int) $promo->reward_product_variant_id, 'role' => 'Reward']);
            }
            if ($promo->rewards->isEmpty()) {
                if ($promo->reward_product_variant_id) {
                    $rewardTexts[] = self::rewardText($promo->reward_type, $promo->reward_value, $label($promo->rewardVariant, (int) $promo->reward_product_variant_id), $promo->reward_qty);
                } elseif ($promo->reward_type && (float) $promo->reward_value > 0) {
                    $rewardTexts[] = self::rewardText($promo->reward_type, $promo->reward_value, null, null);
                }
            }

            $mine = $used->filter(fn ($row) => in_array($row['variant_id'], $variantIds, true));
            $usedVariantIds = $mine->pluck('variant_id')->unique()->values();

            $outletIds = $promo->outlets->pluck('id')->map(fn ($id) => (int) $id);
            $visibleOutlets = $fullAccess ? $promo->outlets : $promo->outlets->filter(fn ($outlet) => $accessibleIds->contains((int) $outlet->id));
            $hiddenCount = $promo->outlets->count() - $visibleOutlets->count();

            $conflicts = [];

            if (in_array($state['key'], ['active', 'upcoming'], true)) {
                foreach ($usedVariantIds as $variantId) {
                    foreach ($visibleOutlets as $outlet) {
                        if (! $productOutletIds->contains((int) $outlet->id) || ! ($variantOutletIds[$variantId] ?? collect())->contains((int) $outlet->id)) {
                            $conflicts[] = $variantNames[$variantId].' tidak tersedia di '.$outlet->name;
                        }
                    }
                }
            }

            return [
                'id' => (int) $promo->id,
                'name' => $promo->name,
                'state' => $state['key'],
                'state_label' => $state['label'],
                'status' => $promo->status,
                'is_active' => (bool) $promo->is_active,
                'live' => $state['key'] === 'active',
                'window_note' => $state['key'] === 'active' && ! self::promoWithinWindow($promo, $now) ? 'Saat ini di luar hari/jam Promo.' : null,
                'logic' => strtoupper((string) ($promo->requirement_logic ?? 'and')),
                'requirements' => $requirementTexts,
                'rewards' => $rewardTexts,
                'usage' => $mine->map(fn ($row) => $variantNames[$row['variant_id']].' ('.$row['role'].')')->unique()->values()->all(),
                'variant_ids' => $usedVariantIds->all(),
                'variant_count' => $usedVariantIds->count(),
                'outlets' => $visibleOutlets->pluck('name')->values()->all(),
                'outlet_ids' => $visibleOutlets->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'outlet_count' => $visibleOutlets->count(),
                'hidden_outlet_count' => $hiddenCount,
                'conflicts' => array_values(array_unique($conflicts)),
                'schedule' => self::promoSchedule($promo),
                // The editor saves exactly the outlets it can list; a role that cannot see all of this
                // Promo's outlets must not open it (saving would drop the ones it cannot see).
                'edit_url' => $canEdit && $hiddenCount === 0 ? route('backoffice.promos.edit', [$promo->id, 'return_to' => $returnTo], false) : null,
                'read_only_reason' => $canEdit && $hiddenCount > 0 ? 'Promo ini juga berlaku di outlet lain di luar akses akun ini. Hanya akun dengan akses semua outlet yang dapat mengubahnya.' : null,
            ];
        });

        return [
            'can_edit' => $canEdit,
            'create_url' => $canEdit ? route('backoffice.promos.create', ['return_to' => $returnTo], false) : null,
            'variant_create' => $canEdit
                ? $variants->where('is_active', true)->map(fn (ProductVariant $variant) => [
                    'name' => $variant->name,
                    'url' => route('backoffice.promos.create', ['variant_id' => $variant->id, 'return_to' => $returnTo], false),
                ])->values()->all()
                : [],
            'scope_note' => $activeOutletId && $narrowed > 0
                ? $narrowed.' Promo lain tidak ditampilkan karena tidak berlaku di outlet aktif. Pilih "Semua Outlet" untuk melihat semuanya.'
                : null,
            'summary' => [
                'total' => $rows->count(),
                'active' => $rows->where('state', 'active')->count(),
                'upcoming' => $rows->where('state', 'upcoming')->count(),
                'expired' => $rows->where('state', 'expired')->count(),
                'inactive' => $rows->where('state', 'inactive')->count(),
            ],
            'promos' => $rows->all(),
        ];
    }

    private static function rewardText(?string $type, mixed $value, ?string $variantLabel, mixed $qty): string
    {
        return match ($type) {
            'discount_percent' => 'Diskon '.self::plainNumber($value).'%',
            'free_item' => 'Gratis '.($variantLabel ?? 'item').' ×'.self::plainNumber($qty ?? 1),
            default => 'Diskon Rp '.self::plainNumber($value),
        };
    }

    private static function promoSchedule(Promo $promo): string
    {
        $dates = match (true) {
            $promo->start_date && $promo->end_date => $promo->start_date->format('d/m/Y').' – '.$promo->end_date->format('d/m/Y'),
            (bool) $promo->start_date => 'Mulai '.$promo->start_date->format('d/m/Y'),
            (bool) $promo->end_date => 'Sampai '.$promo->end_date->format('d/m/Y'),
            default => 'Tanpa batas tanggal',
        };

        $times = $promo->start_time && $promo->end_time
            ? substr((string) $promo->start_time, 0, 5).'–'.substr((string) $promo->end_time, 0, 5)
            : 'Sepanjang hari';

        $days = collect($promo->active_days ?? [])->map(fn ($day) => self::DAY_LABELS[$day] ?? $day)->implode(', ');

        return trim($dates.' · '.$times.($days !== '' ? ' · '.$days : ''));
    }

    /** @return Collection<int, Collection<int, Recipe>> keyed by Variant ID */
    private function recipesByVariant(array $variantIds): Collection
    {
        if ($variantIds === []) {
            return collect();
        }

        return Recipe::withCount('items')
            ->with(['items' => fn ($query) => $query->orderBy('id'), 'items.ingredient:id,name,is_active,unit,minimum_stock', 'items.ingredient.outlets:id'])
            ->whereIn('product_variant_id', $variantIds)
            ->orderBy('id')
            ->get()
            ->groupBy('product_variant_id');
    }

    /** @return array<int, array<int, array>> [outletId][variantId] => status (one load for all outlets) */
    private function eligibilityByOutlet(array $variantIds, array $outletIds): array
    {
        return $this->eligibility->variantStatusesAtOutlets($variantIds, $outletIds);
    }

    private function sellableSummary(Collection $variants, Collection $outlets, array $statuses): array
    {
        $total = $variants->count() * $outlets->count();
        $eligible = 0;

        foreach ($outlets as $outlet) {
            foreach ($variants as $variant) {
                if ($statuses[$outlet->id][$variant->id]['eligible'] ?? false) {
                    $eligible++;
                }
            }
        }

        return ['eligible' => $eligible, 'total' => $total];
    }
}
