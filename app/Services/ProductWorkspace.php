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

    private const DAY_LABELS = [
        'sunday' => 'Min', 'monday' => 'Sen', 'tuesday' => 'Sel', 'wednesday' => 'Rab',
        'thursday' => 'Kam', 'friday' => 'Jum', 'saturday' => 'Sab',
    ];

    public function __construct(
        private readonly BackofficeOutletContext $context,
        private readonly SaleEligibilityService $eligibility,
        private readonly RecipeAccessPolicy $recipePolicy,
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
        $variantIds = $variants->pluck('id')->map(fn ($id) => (int) $id)->all();
        $productOutletIds = $product->outlets->pluck('id')->map(fn ($id) => (int) $id);
        $readinessOutlets = $this->readinessOutlets($product, $user);
        $statuses = $this->eligibilityByOutlet($variantIds, $readinessOutlets);
        $recipes = $this->recipesByVariant($variantIds);
        $returnUrl = fn (string $section) => self::url($product, $section, $listReturnTo);

        return [
            'header' => [
                'variant_count' => $variants->count(),
                'active_variant_count' => $variants->where('is_active', true)->count(),
                'outlet_count' => $productOutletIds->count(),
                'sellable' => $this->sellableSummary($variants, $readinessOutlets, $statuses),
            ],
            'outlets' => $this->outletsSection($product, $user),
            'variants' => $this->variantsSection($variants, $readinessOutlets, $statuses),
            'recipes' => $this->recipeSection($variants, $recipes, $user, $returnUrl('recipe')),
            'stock' => $this->stockSection($variants, $recipes, $readinessOutlets, $statuses),
            'promos' => $this->promoSection($variants, $user, $returnUrl('promo')),
            'links' => [
                'variants_edit' => $variants->isNotEmpty()
                    ? route('backoffice.variants.edit', [$variants->first()->id, 'return_to' => $returnUrl('variants')], false)
                    : null,
                'variants_create' => route('backoffice.variants.create', ['return_to' => $returnUrl('variants')], false),
                'stock_balances' => route('backoffice.stock-balances.index', [], false),
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

    private function variantsSection(Collection $variants, Collection $readinessOutlets, array $statuses): array
    {
        return $variants->map(function (ProductVariant $variant) use ($readinessOutlets, $statuses) {
            $eligibleAt = $readinessOutlets->filter(fn (Outlet $outlet) => $statuses[$outlet->id][$variant->id]['eligible'] ?? false);

            return [
                'id' => (int) $variant->id,
                'name' => $variant->name,
                'code' => $variant->code,
                'is_active' => (bool) $variant->is_active,
                'price_dine_in' => (float) ($variant->price_dine_in ?? $variant->price ?? 0),
                'price_delivery' => (float) ($variant->price_delivery ?? $variant->price ?? 0),
                'outlets' => $variant->outlets->pluck('name')->all(),
                'sellable_count' => $eligibleAt->count(),
                'readiness_count' => $readinessOutlets->count(),
            ];
        })->values()->all();
    }

    private function recipeSection(Collection $variants, Collection $recipes, User $user, string $returnTo): array
    {
        return $variants->map(function (ProductVariant $variant) use ($recipes, $user, $returnTo) {
            $variantRecipes = $recipes->get($variant->id, collect());
            $mutation = $this->recipePolicy->mutationStatus($user, $variant);

            return [
                'variant_id' => (int) $variant->id,
                'variant_name' => $variant->name,
                'variant_active' => (bool) $variant->is_active,
                'status' => self::recipeStatus($variantRecipes),
                'can_mutate' => $mutation['allowed'],
                'mutation_message' => $mutation['message'],
                'recipes' => $variantRecipes->map(fn (Recipe $recipe) => [
                    'id' => (int) $recipe->id,
                    'name' => $recipe->name,
                    'is_active' => (bool) $recipe->is_active,
                    'items_count' => (int) $recipe->items_count,
                    'edit_url' => route('backoffice.recipes.edit', [$recipe->id, 'return_to' => $returnTo], false),
                ])->values()->all(),
                'create_url' => $variantRecipes->isEmpty() && $mutation['allowed']
                    ? route('backoffice.recipes.create', ['return_to' => $returnTo], false)
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

    private function stockSection(Collection $variants, Collection $recipes, Collection $readinessOutlets, array $statuses): array
    {
        // Ingredients of every ACTIVE Recipe of this Product's Variants (an ambiguous Variant contributes
        // all of its active Recipes: this is context, not a deduction).
        $activeRecipeIds = $recipes->flatten(1)->where('is_active', true)->pluck('id')->all();
        $outletIds = $readinessOutlets->pluck('id')->map(fn ($id) => (int) $id)->all();

        $ingredients = $activeRecipeIds === []
            ? collect()
            : RecipeItem::with(['ingredient.outlets:id'])
                ->whereIn('recipe_id', $activeRecipeIds)
                ->get()
                ->pluck('ingredient')
                ->filter()
                ->unique('id')
                ->sortBy(fn ($ingredient) => mb_strtolower($ingredient->name))
                ->values();

        $balances = $ingredients->isEmpty() || $outletIds === []
            ? collect()
            : StockBalance::where('location_type', 'outlet')
                ->whereIn('location_id', $outletIds)
                ->whereIn('ingredient_id', $ingredients->pluck('id')->all())
                ->get()
                ->keyBy(fn (StockBalance $balance) => $balance->ingredient_id.':'.$balance->location_id);

        return [
            'outlets' => $readinessOutlets->map(fn (Outlet $outlet) => ['id' => (int) $outlet->id, 'name' => $outlet->name])->values()->all(),
            'eligibility' => $variants->map(fn (ProductVariant $variant) => [
                'variant_name' => $variant->name,
                'cells' => $readinessOutlets->map(fn (Outlet $outlet) => $statuses[$outlet->id][$variant->id] ?? ['eligible' => false, 'reason' => null, 'message' => null])->values()->all(),
            ])->values()->all(),
            'ingredients' => $ingredients->map(function ($ingredient) use ($readinessOutlets, $balances) {
                $minimum = (float) ($ingredient->minimum_stock ?? 0);

                return [
                    'name' => $ingredient->name,
                    'unit' => $ingredient->unit,
                    'minimum_stock' => $minimum,
                    'is_active' => (bool) $ingredient->is_active,
                    'cells' => $readinessOutlets->map(function (Outlet $outlet) use ($ingredient, $balances, $minimum) {
                        $balance = $balances->get($ingredient->id.':'.$outlet->id);
                        $qty = $balance ? (float) $balance->qty_on_hand : null;

                        // Same labels as Inventory Control (StockBalanceViewController::getStockStatusLabel).
                        $state = match (true) {
                            $qty === null => 'none',
                            $qty <= 0 => 'out',
                            $qty <= $minimum => 'low',
                            default => 'ok',
                        };

                        return [
                            'available' => $ingredient->outlets->contains('id', $outlet->id),
                            'qty' => $qty,
                            'state' => $state,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    private function promoSection(Collection $variants, User $user, string $returnTo): array
    {
        $variantIds = $variants->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($variantIds === []) {
            return ['can_edit' => false, 'promos' => []];
        }

        $canEdit = in_array($user->role?->code, self::PROMO_ROLES, true);
        $variantNames = $variants->pluck('name', 'id');

        $promos = Promo::with(['outlets' => fn ($query) => $query->orderBy('name'), 'requirements', 'rewards'])
            ->referencingVariants($variantIds)
            ->orderBy('name')
            ->get();

        return [
            'can_edit' => $canEdit,
            'promos' => $promos->map(function (Promo $promo) use ($variantIds, $variantNames, $canEdit, $returnTo) {
                $used = collect();

                foreach ($promo->requirements as $requirement) {
                    $used->push(['variant_id' => (int) $requirement->product_variant_id, 'role' => 'Syarat']);
                }
                foreach ($promo->rewards as $reward) {
                    $used->push(['variant_id' => (int) $reward->product_variant_id, 'role' => 'Reward']);
                }
                $used->push(['variant_id' => (int) $promo->requirement_product_variant_id, 'role' => 'Syarat']);
                $used->push(['variant_id' => (int) $promo->reward_product_variant_id, 'role' => 'Reward']);

                $usage = $used
                    ->filter(fn ($row) => in_array($row['variant_id'], $variantIds, true))
                    ->map(fn ($row) => $variantNames[$row['variant_id']].' ('.$row['role'].')')
                    ->unique()->values()->all();

                return [
                    'id' => (int) $promo->id,
                    'name' => $promo->name,
                    'status' => $promo->status,
                    'is_active' => (bool) $promo->is_active,
                    'live' => $promo->isActiveStatus(),
                    'outlets' => $promo->outlets->pluck('name')->all(),
                    'outlet_ids' => $promo->outlets->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    'variant_ids' => $used->pluck('variant_id')->filter(fn ($id) => in_array($id, $variantIds, true))->unique()->values()->all(),
                    'usage' => $usage,
                    'schedule' => self::promoSchedule($promo),
                    'edit_url' => $canEdit ? route('backoffice.promos.edit', [$promo->id, 'return_to' => $returnTo], false) : null,
                ];
            })->values()->all(),
        ];
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
            ->whereIn('product_variant_id', $variantIds)
            ->orderBy('id')
            ->get()
            ->groupBy('product_variant_id');
    }

    /** @return array<int, array<int, array>> [outletId][variantId] => status */
    private function eligibilityByOutlet(array $variantIds, Collection $outlets): array
    {
        $statuses = [];

        foreach ($outlets as $outlet) {
            $statuses[$outlet->id] = $this->eligibility->variantStatuses($variantIds, (int) $outlet->id);
        }

        return $statuses;
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
