<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\IngredientCategory;
use Illuminate\Database\Seeder;

class IngredientCategorySeeder extends Seeder
{
    public function run(): void
    {
        $leeOngsTea = Brand::where('code', 'lee_ongs_tea')->first();
        $waspffle = Brand::where('code', 'waspffle')->first();

        $categories = [
            ['brand_id' => $leeOngsTea?->id, 'name' => 'Tea Base', 'code' => 'tea_base'],
            ['brand_id' => $leeOngsTea?->id, 'name' => 'Milk', 'code' => 'milk'],
            ['brand_id' => $leeOngsTea?->id, 'name' => 'Sweetener', 'code' => 'sweetener'],
            ['brand_id' => $leeOngsTea?->id, 'name' => 'Topping', 'code' => 'tea_topping_category'],

            ['brand_id' => $waspffle?->id, 'name' => 'Waffle Base', 'code' => 'waffle_base'],
            ['brand_id' => $waspffle?->id, 'name' => 'Filling', 'code' => 'waffle_filling_category'],
            ['brand_id' => $waspffle?->id, 'name' => 'Dipping', 'code' => 'waffle_dipping_category'],
        ];

        foreach ($categories as $category) {
            IngredientCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    'brand_id' => $category['brand_id'],
                    'name' => $category['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}