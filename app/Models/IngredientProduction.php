<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngredientProduction extends Model
{
    protected $fillable = [
        'ingredient_production_recipe_id',
        'output_ingredient_id',
        'location_type',
        'location_id',
        'batch_qty',
        'output_qty',
        'output_unit',
        'status',
        'note',
        'produced_by_user_id',
        'produced_at',
    ];

    protected $casts = [
        'batch_qty' => 'decimal:2',
        'output_qty' => 'decimal:2',
        'produced_at' => 'datetime',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(IngredientProductionRecipe::class, 'ingredient_production_recipe_id');
    }

    public function outputIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'output_ingredient_id');
    }

    public function producedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'produced_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IngredientProductionItem::class);
    }

    public function inputItems(): HasMany
    {
        return $this->hasMany(IngredientProductionItem::class)
            ->where('item_type', 'input');
    }

    public function outputItems(): HasMany
    {
        return $this->hasMany(IngredientProductionItem::class)
            ->where('item_type', 'output');
    }
}