<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngredientProductionRecipe extends Model
{
    protected $fillable = [
        'output_ingredient_id',
        'name',
        'output_qty',
        'output_unit',
        'is_active',
    ];

    protected $casts = [
        'output_qty' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function outputIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'output_ingredient_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IngredientProductionRecipeItem::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(IngredientProduction::class, 'ingredient_production_recipe_id');
    }
}