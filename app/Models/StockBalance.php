<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    protected $fillable = [
        'ingredient_id',
        'location_type',
        'location_id',
        'qty_on_hand',
    ];

    protected $casts = [
        'qty_on_hand' => 'float',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * Operational stock views: balances of an Ingredient removed from the system (tombstoned) are hidden.
     * The rows themselves are kept (they are stock history); only lists, pickers and stock actions skip them.
     */
    public function scopeWithLiveIngredient(Builder $query): Builder
    {
        return $query->whereHas('ingredient');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'location_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'location_id');
    }
}