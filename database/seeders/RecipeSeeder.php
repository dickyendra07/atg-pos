<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $blackTea = Product::where('code', 'black_tea')->first();
        $blackTeaRegular = ProductVariant::where('product_id', $blackTea?->id)->where('code', 'r')->first();

        $peachTea = Product::where('code', 'peach_tea')->first();
        $peachTeaRegular = ProductVariant::where('product_id', $peachTea?->id)->where('code', 'r')->first();

        $originalWaffle = Product::where('code', 'original_waffle')->first();
        $originalWaffleFull = ProductVariant::where('product_id', $originalWaffle?->id)->where('code', 'full')->first();

        $recipes = [
            [
                'product_id' => $blackTea?->id,
                'product_variant_id' => $blackTeaRegular?->id,
                'name' => 'Black Tea Regular Recipe',
            ],
            [
                'product_id' => $peachTea?->id,
                'product_variant_id' => $peachTeaRegular?->id,
                'name' => 'Peach Tea Regular Recipe',
            ],
            [
                'product_id' => $originalWaffle?->id,
                'product_variant_id' => $originalWaffleFull?->id,
                'name' => 'Original Waffle Full Recipe',
            ],
        ];

        foreach ($recipes as $recipe) {
            if (! $recipe['product_id']) {
                continue;
            }

            Recipe::updateOrCreate(
                [
                    'product_id' => $recipe['product_id'],
                    'product_variant_id' => $recipe['product_variant_id'],
                ],
                [
                    'name' => $recipe['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}