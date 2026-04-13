<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientProductionItem extends Model
{
    protected $fillable = [
        'ingredient_production_id',
        'ingredient_id',
        'item_type',
        'qty',
        'unit',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    public function production(): BelongsTo
    {
        return $this->belongsTo(IngredientProduction::class, 'ingredient_production_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}