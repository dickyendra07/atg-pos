<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StockBalance;
use Illuminate\Database\Seeder;

class StockBalanceSeeder extends Seeder
{
    public function run(): void
    {
        $outletTb = Outlet::where('code', 'TB')->first();
        $outletBx = Outlet::where('code', 'BX')->first();

        $ingredients = Ingredient::all();

        foreach ($ingredients as $ingredient) {
            if ($outletTb) {
                StockBalance::updateOrCreate(
                    [
                        'ingredient_id' => $ingredient->id,
                        'location_type' => 'outlet',
                        'location_id' => $outletTb->id,
                    ],
                    [
                        'qty_on_hand' => 1000,
                    ]
                );
            }

            if ($outletBx) {
                StockBalance::updateOrCreate(
                    [
                        'ingredient_id' => $ingredient->id,
                        'location_type' => 'outlet',
                        'location_id' => $outletBx->id,
                    ],
                    [
                        'qty_on_hand' => 800,
                    ]
                );
            }
        }
    }
}