<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'ingredient_id',
        'stock_movement_id',
        'unit',
        'system_qty',
        'actual_qty',
        'difference',
    ];

    protected $casts = [
        'system_qty' => 'float',
        'actual_qty' => 'float',
        'difference' => 'float',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /** The compensating movement written by VOID (void_stock_movement_id is set only by StockAdjustmentVoidService). */
    public function voidMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'void_stock_movement_id');
    }
}
