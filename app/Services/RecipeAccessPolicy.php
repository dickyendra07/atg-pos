<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Single source of truth for who may see and who may change a Recipe.
 *
 * A Recipe is GLOBAL per ProductVariant (no outlet of its own), so two questions are kept apart:
 *
 * VIEW     - the Recipe's Variant is available in at least one outlet of the current view scope
 *            (BackofficeOutletContext::scopeOutletIds()).
 * MUTATION - a change reaches every active outlet that uses the Variant (Product + Variant both
 *            available there), so it is only allowed when ALL of those outlets are inside the
 *            mutation ceiling:
 *              - specific Active Outlet  => just that outlet, for every role including owner
 *              - "Semua Outlet(...)"     => every outlet the user can access
 *
 * Bind per request (not a singleton): it memoises the user's context.
 */
class RecipeAccessPolicy
{
    public const DENIED_CONTEXT = 'context';

    public const DENIED_ACCESS = 'access';

    public const DENIED_SCOPE = 'scope';

    private array $memo = [];

    private array $statusMemo = [];

    private ?Collection $activeOutlets = null;

    public function __construct(private readonly BackofficeOutletContext $context) {}

    /** Outlet ids of the current view scope (null = unrestricted). */
    public function viewScope(User $user): ?array
    {
        return $this->userContext($user)['viewScope'];
    }

    public function canViewRecipe(User $user, Recipe $recipe): bool
    {
        $scope = $this->viewScope($user);

        return $scope === null || Recipe::whereKey($recipe->id)->inOutletScope($scope)->exists();
    }

    /** The Variant is available in at least one outlet of the current view scope. */
    public function canViewVariant(User $user, ProductVariant $variant): bool
    {
        $scope = $this->viewScope($user);

        return $scope === null || $this->usageOutletIds($variant)->intersect($scope)->isNotEmpty();
    }

    public function canMutateRecipe(User $user, Recipe $recipe): bool
    {
        return $this->canViewRecipe($user, $recipe)
            && $this->canMutateVariantRecipe($user, $recipe->variant);
    }

    public function canMutateVariantRecipe(User $user, ?ProductVariant $variant): bool
    {
        return $this->mutationStatus($user, $variant)['allowed'];
    }

    /**
     * @return array{allowed: bool, reason: ?string, message: ?string, outlets: string[]}
     */
    public function mutationStatus(User $user, ?ProductVariant $variant): array
    {
        return $this->statusMemo[$user->id.':'.($variant?->id ?? 'none')] ??= $this->computeMutationStatus($user, $variant);
    }

    private function computeMutationStatus(User $user, ?ProductVariant $variant): array
    {
        $ctx = $this->userContext($user);
        $usage = $variant ? $this->usageOutletIds($variant) : collect();

        // A Variant outside the current view scope is never a valid target (create, reassign, import).
        if ($variant && ! $this->canViewVariant($user, $variant)) {
            return [
                'allowed' => false,
                'reason' => self::DENIED_SCOPE,
                'message' => 'Variant tidak tersedia pada Active Outlet atau outlet yang dapat kamu akses.',
                'outlets' => [],
            ];
        }

        if ($usage->diff($ctx['ceiling'])->isEmpty()) {
            return ['allowed' => true, 'reason' => null, 'message' => null, 'outlets' => []];
        }

        // Names come from the already loaded active outlets (no query per Recipe on the Index).
        $names = $this->activeOutlets()->whereIn('id', $usage->all())->pluck('name')->sort()->values()->all();

        if ($usage->diff($ctx['accessible'])->isNotEmpty()) {
            return [
                'allowed' => false,
                'reason' => self::DENIED_ACCESS,
                'message' => 'Recipe ini digunakan di outlet lain di luar akses Anda. Anda dapat melihat Recipe ini, tetapi tidak dapat mengubahnya.',
                'outlets' => $names,
            ];
        }

        return [
            'allowed' => false,
            'reason' => self::DENIED_CONTEXT,
            'message' => 'Recipe ini digunakan di beberapa outlet: '.implode(', ', $names).'. Perubahan Recipe berlaku ke seluruh outlet tersebut. Pilih \''.$ctx['allLabel'].'\' untuk melakukan perubahan.',
            'outlets' => $names,
        ];
    }

    /** Message explaining why the user cannot change this Recipe (null when allowed). */
    public function mutationDeniedMessage(User $user, ?ProductVariant $variant): ?string
    {
        return $this->mutationStatus($user, $variant)['message'];
    }

    /**
     * Active outlets that use the Variant: Product AND Variant both available there.
     *
     * @return Collection<int, int>
     */
    public function usageOutletIds(ProductVariant $variant): Collection
    {
        $variant->loadMissing(['outlets', 'product.outlets']);

        $productOutletIds = ($variant->product?->outlets ?? collect())->pluck('id');

        return $variant->outlets->pluck('id')
            ->intersect($productOutletIds)
            ->intersect($this->activeOutlets()->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function activeOutlets(): Collection
    {
        return $this->activeOutlets ??= Outlet::where('is_active', true)->get(['id', 'name']);
    }

    private function userContext(User $user): array
    {
        return $this->memo[$user->id] ??= (function () use ($user) {
            $accessible = $this->context->accessibleOutlets($user)->pluck('id')->map(fn ($id) => (int) $id);
            $activeOutletId = $this->context->activeOutletId($user);

            return [
                'viewScope' => $this->context->scopeOutletIds($user),
                'accessible' => $accessible,
                'ceiling' => $activeOutletId ? collect([(int) $activeOutletId]) : $accessible,
                'allLabel' => $this->context->labelFor($user, null),
            ];
        })();
    }
}
