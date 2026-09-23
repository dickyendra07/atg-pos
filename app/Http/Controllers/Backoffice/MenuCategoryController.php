<?php

namespace App\Http\Controllers\Backoffice;

use App\Models\ProductCategory;

class MenuCategoryController extends CategoryManagementController
{
    protected function modelClass(): string
    {
        return ProductCategory::class;
    }

    protected function usageRelation(): string
    {
        return 'products';
    }

    protected function config(): array
    {
        return [
            'title' => 'Menu Categories',
            'kicker' => 'Category Menu',
            'route' => 'backoffice.menu-categories',
            'usage_label' => 'Jumlah Product',
            'needs_brand' => true,
            'deletable' => true,
            'usage_noun' => 'produk',
        ];
    }
}
