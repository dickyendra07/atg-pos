<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'role_id',
        'outlet_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'user_outlet', 'user_id', 'outlet_id')->withTimestamps();
    }


    public function cashierAccessibleOutlets()
    {
        $outlets = $this->relationLoaded('outlets')
            ? $this->outlets
            : $this->outlets()->orderBy('name')->get();

        if ($outlets->isEmpty() && $this->outlet) {
            $outlets = collect([$this->outlet]);
        }

        return $outlets
            ->filter()
            ->unique('id')
            ->values();
    }

    public function hasCashierOutletAccess(int $outletId): bool
    {
        return $this->cashierAccessibleOutlets()
            ->contains(fn ($outlet) => (int) $outlet->id === (int) $outletId);
    }

    public function applyCashierOutletFromSession(): self
    {
        $outletId = (int) session('cashier_outlet_id');

        if (! $outletId || ! $this->hasCashierOutletAccess($outletId)) {
            return $this;
        }

        $outlet = $this->cashierAccessibleOutlets()
            ->first(fn ($candidate) => (int) $candidate->id === $outletId);

        if ($outlet) {
            $this->forceFill([
                'outlet_id' => $outlet->id,
            ]);

            $this->setRelation('outlet', $outlet);
        }

        return $this;
    }

    public function roleCode(): ?string
    {
        return $this->role?->code;
    }

    public function roleCodes(): array
    {
        $codes = $this->relationLoaded('roles')
            ? $this->roles->pluck('code')->all()
            : $this->roles()->pluck('code')->all();

        if ($this->role?->code) {
            $codes[] = $this->role->code;
        }

        return array_values(array_unique(array_filter($codes)));
    }

    public function hasRoleCode(string $code): bool
    {
        return in_array($code, $this->roleCodes(), true);
    }

    protected function normalizedRoleName(): string
    {
        return strtolower(trim((string) ($this->role?->name ?? '')));
    }

    public function hasAnyRoleCode(array $codes): bool
    {
        return ! empty(array_intersect($this->roleCodes(), $codes));
    }

    public function hasAnyRoleName(array $names): bool
    {
        $roleNames = [];

        if ($this->role?->name) {
            $roleNames[] = strtolower(trim((string) $this->role->name));
        }

        if ($this->relationLoaded('roles')) {
            $roleNames = array_merge($roleNames, $this->roles->pluck('name')->map(fn ($name) => strtolower(trim((string) $name)))->all());
        } else {
            $roleNames = array_merge($roleNames, $this->roles()->pluck('name')->map(fn ($name) => strtolower(trim((string) $name)))->all());
        }

        return ! empty(array_intersect(array_unique(array_filter($roleNames)), $names));
    }

    public function isCashierUser(): bool
    {
        return $this->hasAnyRoleCode(['kasir'])
            || $this->hasAnyRoleName(['kasir']);
    }

    public function isBackofficeUser(): bool
    {
        return $this->hasAnyRoleCode([
            'owner',
            'admin_pusat',
            'admin_outlet',
            'staff_gudang',
        ]) || $this->hasAnyRoleName([
            'owner',
            'admin pusat',
            'admin outlet',
            'staff gudang',
        ]);
    }

    public function isFullAccessUser(): bool
    {
        return $this->hasAnyRoleCode([
            'owner',
            'admin_pusat',
        ]) || $this->hasAnyRoleName([
            'owner',
            'admin pusat',
        ]);
    }

    public function isLimitedAccessUser(): bool
    {
        return $this->hasAnyRoleCode([
            'admin_outlet',
            'staff_gudang',
        ]) || $this->hasAnyRoleName([
            'admin outlet',
            'staff gudang',
        ]);
    }

    public function canAccessCashier(): bool
    {
        return $this->isCashierUser();
    }

    public function canAccessBackofficeDashboard(): bool
    {
        return $this->isBackofficeUser();
    }

    public function canManageUsers(): bool
    {
        return $this->isFullAccessUser();
    }
}