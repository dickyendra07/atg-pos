<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\User;
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
}
