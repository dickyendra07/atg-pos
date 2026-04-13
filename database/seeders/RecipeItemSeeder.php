<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;
use Illuminate\Database\Seeder;

class RecipeItemSeeder extends Seeder
{
    public function run(): void
    {
        $blackTea = Product::where('code', 'black_tea')->first();
        $blackTeaRegular = ProductVariant::where('product_id', $blackTea?->id)->where('code', 'r')->first();
        $blackTeaRecipe = Recipe::where('product_id', $blackTea?->id)
            ->where('product_variant_id', $blackTeaRegular?->id)
            ->first();

        $peachTea = Product::where('code', 'peach_tea')->first();
        $peachTeaRegular = ProductVariant::where('product_id', $peachTea?->id)->where('code', 'r')->first();
        $peachTeaRecipe = Recipe::where('product_id', $peachTea?->id)
            ->where('product_variant_id', $peachTeaRegular?->id)
            ->first();

        $originalWaffle = Product::where('code', 'original_waffle')->first();
        $originalWaffleFull = ProductVariant::where('product_id', $originalWaffle?->id)->where('code', 'full')->first();
        $originalWaffleRecipe = Recipe::where('product_id', $originalWaffle?->id)
            ->where('product_variant_id', $originalWaffleFull?->id)
            ->first();

        $blackTeaIngredient = Ingredient::where('code', 'black_tea_ingredient')->first();
        $freshMilk = Ingredient::where('code', 'fresh_milk')->first();
        $liquidSugar = Ingredient::where('code', 'liquid_sugar')->first();
        $waffleBatter = Ingredient::where('code', 'waffle_batter')->first();
        $chocolateFilling = Ingredient::where('code', 'chocolate_filling')->first();

        $items = [
            [
                'recipe_id' => $blackTeaRecipe?->id,
                'ingredient_id' => $blackTeaIngredient?->id,
                'qty' => 10,
                'unit' => 'gram',
            ],
            [
                'recipe_id' => $blackTeaRecipe?->id,
                'ingredient_id' => $liquidSugar?->id,
                'qty' => 20,
                'unit' => 'ml',
            ],
            [
                'recipe_id' => $peachTeaRecipe?->id,
                'ingredient_id' => $blackTeaIngredient?->id,
                'qty' => 8,
                'unit' => 'gram',
            ],
            [
                'recipe_id' => $peachTeaRecipe?->id,
                'ingredient_id' => $liquidSugar?->id,
                'qty' => 25,
                'unit' => 'ml',
            ],
            [
                'recipe_id' => $originalWaffleRecipe?->id,
                'ingredient_id' => $waffleBatter?->id,
                'qty' => 120,
                'unit' => 'gram',
            ],
            [
                'recipe_id' => $originalWaffleRecipe?->id,
                'ingredient_id' => $chocolateFilling?->id,
                'qty' => 30,
                'unit' => 'gram',
            ],
        ];

        foreach ($items as $item) {
            if (! $item['recipe_id'] || ! $item['ingredient_id']) {
                continue;
            }

            RecipeItem::updateOrCreate(
                [
                    'recipe_id' => $item['recipe_id'],
                    'ingredient_id' => $item['ingredient_id'],
                ],
                [
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                ]
            );
        }
    }
}