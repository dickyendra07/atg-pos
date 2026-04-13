<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    public const TYPE_RAW = 'raw';
    public const TYPE_SEMI_FINISHED = 'semi_finished';

    protected $fillable = [
        'ingredient_category_id',
        'name',
        'code',
        'unit',
        'ingredient_type',
        'minimum_stock',
        'cost_per_unit',
        'is_active',
    ];

    protected $casts = [
        'minimum_stock' => 'decimal:2',
        'cost_per_unit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function ingredientTypeOptions(): array
    {
        return [
            self::TYPE_RAW => 'Mentah',
            self::TYPE_SEMI_FINISHED => 'Setengah Jadi',
        ];
    }

    public function isRaw(): bool
    {
        return $this->ingredient_type === self::TYPE_RAW;
    }

    public function isSemiFinished(): bool
    {
        return $this->ingredient_type === self::TYPE_SEMI_FINISHED;
    }

    public function ingredientTypeLabel(): string
    {
        return self::ingredientTypeOptions()[$this->ingredient_type] ?? 'Mentah';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'ingredient_category_id');
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function productionRecipes(): HasMany
    {
        return $this->hasMany(IngredientProductionRecipe::class, 'output_ingredient_id');
    }

    public function productionRecipeItems(): HasMany
    {
        return $this->hasMany(IngredientProductionRecipeItem::class, 'input_ingredient_id');
    }
}