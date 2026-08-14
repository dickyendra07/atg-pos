<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'outlet_id',
        'name',
        'code',
        'price',
        'price_dine_in',
        'price_delivery',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_dine_in' => 'decimal:2',
        'price_delivery' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'product_variant_outlet')->withTimestamps();
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class, 'product_variant_id');
    }

    public function salesTransactionItems(): HasMany
    {
        return $this->hasMany(SalesTransactionItem::class, 'product_variant_id');
    }

    public function getPriceByOrderType(string $orderType): float
    {
        $type = strtolower($orderType);

        if ($type === 'delivery') {
            return (float) ($this->price_delivery ?? $this->price ?? 0);
        }

        return (float) ($this->price_dine_in ?? $this->price ?? 0);
    }

    public function scopeAvailableAtOutlet(Builder $query, int $outletId): Builder
    {
        return $query
            ->whereHas('product.outlets', function (Builder $outletQuery) use ($outletId) {
                $outletQuery->where('outlets.id', $outletId);
            })
            ->where(function (Builder $variantQuery) use ($outletId) {
                $variantQuery->doesntHave('outlets')
                    ->orWhereHas('outlets', function (Builder $outletQuery) use ($outletId) {
                        $outletQuery->where('outlets.id', $outletId);
                    });
            });
    }
}
