<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OutletSeeder::class,
            BrandSeeder::class,
            ProductCategorySeeder::class,
            ProductSeeder::class,
            ProductVariantSeeder::class,
            ModifierGroupSeeder::class,
            ModifierOptionSeeder::class,
            IngredientCategorySeeder::class,
            IngredientSeeder::class,
            RecipeSeeder::class,
            RecipeItemSeeder::class,
            StockBalanceSeeder::class,
            StockMovementSeeder::class,
            MemberSeeder::class,
            UserSeeder::class,
        ]);
    }
}