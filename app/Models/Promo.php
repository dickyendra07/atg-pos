<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promo extends Model
{
    protected $fillable = [
        'outlet_id',
        'name',
        'requirement_product_variant_id',
        'requirement_qty',
        'reward_type',
        'reward_value',
        'reward_product_variant_id',
        'reward_qty',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'active_days',
        'status',
        'is_active',
    ];

    protected $casts = [
        'requirement_qty' => 'decimal:2',
        'reward_value' => 'decimal:2',
        'reward_qty' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'active_days' => 'array',
        'is_active' => 'boolean',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function requirementVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'requirement_product_variant_id');
    }

    public function rewardVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'reward_product_variant_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(PromoRequirement::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(PromoReward::class);
    }

    public function isActiveStatus(): bool
    {
        return $this->is_active && $this->status === 'active';
    }
}
