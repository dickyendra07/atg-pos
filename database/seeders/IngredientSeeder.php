<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $leeOngsTea = Brand::where('code', 'lee_ongs_tea')->first();
        $waspffle = Brand::where('code', 'waspffle')->first();

        $teaBase = IngredientCategory::where('code', 'tea_base')->first();
        $milk = IngredientCategory::where('code', 'milk')->first();
        $sweetener = IngredientCategory::where('code', 'sweetener')->first();
        $teaTopping = IngredientCategory::where('code', 'tea_topping_category')->first();

        $waffleBase = IngredientCategory::where('code', 'waffle_base')->first();
        $waffleFilling = IngredientCategory::where('code', 'waffle_filling_category')->first();
        $waffleDipping = IngredientCategory::where('code', 'waffle_dipping_category')->first();

        $ingredients = [
            [
                'brand_id' => $leeOngsTea?->id,
                'ingredient_category_id' => $teaBase?->id,
                'name' => 'Black Tea',
                'code' => 'black_tea_ingredient',
                'unit' => 'gram',
                'cost_per_unit' => 150,
                'minimum_stock' => 500,
            ],
            [
                'brand_id' => $leeOngsTea?->id,
                'ingredient_category_id' => $milk?->id,
                'name' => 'Fresh Milk',
                'code' => 'fresh_milk',
                'unit' => 'ml',
                'cost_per_unit' => 35,
                'minimum_stock' => 2000,
            ],
            [
                'brand_id' => $leeOngsTea?->id,
                'ingredient_category_id' => $sweetener?->id,
                'name' => 'Liquid Sugar',
                'code' => 'liquid_sugar',
                'unit' => 'ml',
                'cost_per_unit' => 10,
                'minimum_stock' => 1000,
            ],
            [
                'brand_id' => $leeOngsTea?->id,
                'ingredient_category_id' => $teaTopping?->id,
                'name' => 'Boba',
                'code' => 'boba_ingredient',
                'unit' => 'gram',
                'cost_per_unit' => 25,
                'minimum_stock' => 1000,
            ],
            [
                'brand_id' => $waspffle?->id,
                'ingredient_category_id' => $waffleBase?->id,
                'name' => 'Waffle Batter',
                'code' => 'waffle_batter',
                'unit' => 'gram',
                'cost_per_unit' => 20,
                'minimum_stock' => 3000,
            ],
            [
                'brand_id' => $waspffle?->id,
                'ingredient_category_id' => $waffleFilling?->id,
                'name' => 'Chocolate Filling',
                'code' => 'chocolate_filling',
                'unit' => 'gram',
                'cost_per_unit' => 30,
                'minimum_stock' => 1000,
            ],
            [
                'brand_id' => $waspffle?->id,
                'ingredient_category_id' => $waffleDipping?->id,
                'name' => 'Cheese Sauce',
                'code' => 'cheese_sauce_ingredient',
                'unit' => 'gram',
                'cost_per_unit' => 28,
                'minimum_stock' => 1000,
            ],
        ];

        foreach ($ingredients as $ingredient) {
            if (! $ingredient['ingredient_category_id']) {
                continue;
            }

            Ingredient::updateOrCreate(
                ['code' => $ingredient['code']],
                [
                    'brand_id' => $ingredient['brand_id'],
                    'ingredient_category_id' => $ingredient['ingredient_category_id'],
                    'name' => $ingredient['name'],
                    'unit' => $ingredient['unit'],
                    'cost_per_unit' => $ingredient['cost_per_unit'],
                    'minimum_stock' => $ingredient['minimum_stock'],
                    'is_active' => true,
                ]
            );
        }
    }
}