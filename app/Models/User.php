<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
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

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function roleCode(): ?string
    {
        return $this->role?->code;
    }

    public function isFullAccessUser(): bool
    {
        return in_array($this->roleCode(), [
            'owner',
            'admin_pusat',
        ], true);
    }

    public function isLimitedAccessUser(): bool
    {
        return in_array($this->roleCode(), [
            'admin_outlet',
            'kasir',
        ], true);
    }

    public function canAccessCashier(): bool
    {
        return $this->isFullAccessUser() || $this->isLimitedAccessUser();
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