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
}
