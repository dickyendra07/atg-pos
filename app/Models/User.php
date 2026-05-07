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

    public function isFullAccessUser(): bool
    {
        return in_array($this->normalizedRoleName(), [
            'owner',
            'admin pusat',
        ], true);
    }

    public function isLimitedAccessUser(): bool
    {
        return in_array($this->normalizedRoleName(), [
            'kasir',
        ], true);
    }

    public function canAccessCashier(): bool
    {
        return in_array($this->normalizedRoleName(), [
            'owner',
            'kasir',
        ], true);
    }

    public function canAccessBackofficeDashboard(): bool
    {
        return $this->isFullAccessUser();
    }

    public function canManageUsers(): bool
    {
        return $this->isFullAccessUser();
    }
}