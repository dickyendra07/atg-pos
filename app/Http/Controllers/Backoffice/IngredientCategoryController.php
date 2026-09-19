<?php

namespace App\Http\Controllers\Backoffice;

use App\Models\IngredientCategory;

class IngredientCategoryController extends CategoryManagementController
{
    protected function modelClass(): string
    {
        return IngredientCategory::class;
    }

    protected function usageRelation(): string
    {
        return 'ingredients';
    }

    protected function config(): array
    {
        return [
            'title' => 'Ingredient Categories',
            'kicker' => 'Category Bahan',
            'route' => 'backoffice.ingredient-categories',
            'usage_label' => 'Jumlah Ingredient',
            'needs_brand' => false,
        ];
    }
}
