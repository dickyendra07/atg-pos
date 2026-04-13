<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientProductionRecipeItem extends Model
{
    protected $fillable = [
        'ingredient_production_recipe_id',
        'input_ingredient_id',
        'qty',
        'unit',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(IngredientProductionRecipe::class, 'ingredient_production_recipe_id');
    }

    public function inputIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'input_ingredient_id');
    }
}