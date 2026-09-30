<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    /**
     * Recipes whose Variant is available in the given outlets (a Recipe has no outlet of its own).
     * null = unrestricted, [] = nothing.
     *
     * @param  int[]|null  $outletIds
     */
    public function scopeInOutletScope(Builder $query, ?array $outletIds): Builder
    {
        if ($outletIds === null) {
            return $query;
        }

        return $query->whereHas('variant', fn (Builder $variantQuery) => $variantQuery->inOutletScope($outletIds));
    }
}
