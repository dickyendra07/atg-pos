<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BackofficeOutletContext
{
    public const SESSION_KEY = 'active_backoffice_outlet_id';

    public function accessibleOutlets(User $user): Collection
    {
        $user->loadMissing(['role', 'roles', 'outlet', 'outlets']);

        if ($user->isFullAccessUser()) {
            return Outlet::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $outlets = $user->outlets
            ->filter(fn ($outlet) => (bool) $outlet->is_active);

        if ($outlets->isEmpty() && $user->outlet?->is_active) {
            $outlets = collect([$user->outlet]);
        }

        return $outlets->unique('id')->sortBy('name')->values();
    }

    public function activeOutlet(User $user): ?Outlet
    {
        $outletId = (int) session(self::SESSION_KEY);

        if ($outletId <= 0) {
            return null;
        }

        $outlet = $this->accessibleOutlets($user)
            ->first(fn ($candidate) => (int) $candidate->id === $outletId);

        if (! $outlet) {
            session()->forget(self::SESSION_KEY);

            return null;
        }

        return $outlet;
    }

    public function activeOutletId(User $user): ?int
    {
        return $this->activeOutlet($user)?->id;
    }

    public function canAccess(User $user, int $outletId): bool
    {
        return $this->accessibleOutlets($user)
            ->contains(fn ($outlet) => (int) $outlet->id === $outletId);
    }

    /**
     * Single source of truth for the "Outlet" label shown on Backoffice screens.
     *
     * It reflects the Active Backoffice Outlet, never users.outlet_id (the home outlet), which for a
     * global role such as admin pusat is just the first outlet picked when the account was created.
     */
    public function labelFor(User $user, ?Outlet $activeOutlet): string
    {
        if ($activeOutlet) {
            return $activeOutlet->name;
        }

        return $user->isFullAccessUser() ? 'Semua Outlet' : 'Semua Outlet yang Diizinkan';
    }

    /**
     * Outlet ids that bound what the user may see/modify right now.
     *
     * - specific Active Outlet            => [that outlet]
     * - full-access role, "Semua Outlet"  => null (unrestricted)
     * - limited role, "Semua Outlet"      => every outlet the user can access ([] = none)
     *
     * @return int[]|null
     */
    public function scopeOutletIds(User $user): ?array
    {
        $activeOutletId = $this->activeOutletId($user);

        if ($activeOutletId) {
            return [(int) $activeOutletId];
        }

        if ($user->isFullAccessUser()) {
            return null;
        }

        return $this->accessibleOutlets($user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Restricts a stock_balances / stock_movements query (location_type + location_id) to what $user may read.
     *
     * Outlet rows only inside scopeOutletIds(): "Semua Outlet" means every outlet within the user's access,
     * never every outlet in the system. Full-access roles are unrestricted. Warehouses are global for every
     * role that reaches these pages (the same convention as the Transfer pages), so they are not narrowed.
     * Read-only helper: it only adds conditions to the query.
     */
    public function restrictStockLocations(Builder $query, User $user): Builder
    {
        $scope = $this->scopeOutletIds($user);

        if ($scope === null) {
            return $query;
        }

        return $query->where(function (Builder $location) use ($scope) {
            $location->where('location_type', '!=', 'outlet')
                ->orWhere(fn (Builder $outlet) => $outlet->where('location_type', 'outlet')->whereIn('location_id', $scope));
        });
    }

    /** Whether a stock location (outlet or warehouse) named in a request is readable by $user. */
    public function canReadStockLocation(User $user, ?string $type, mixed $id): bool
    {
        if ($type !== 'outlet') {
            return true;
        }

        $scope = $this->scopeOutletIds($user);

        return $scope === null || in_array((int) $id, $scope, true);
    }
}
