<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'ingredient_id',
        'location_type',
        'location_id',
        'movement_type',
        'qty_in',
        'qty_out',
        'reference_type',
        'reference_id',
        'note',
    ];

    protected $casts = [
        'qty_in' => 'decimal:2',
        'qty_out' => 'decimal:2',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}