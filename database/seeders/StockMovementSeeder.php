<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;

class StockMovementSeeder extends Seeder
{
    public function run(): void
    {
        $outletTb = Outlet::where('code', 'TB')->first();
        $outletBx = Outlet::where('code', 'BX')->first();

        $ingredients = Ingredient::all();

        foreach ($ingredients as $ingredient) {
            if ($outletTb) {
                StockMovement::updateOrCreate(
                    [
                        'ingredient_id' => $ingredient->id,
                        'location_type' => 'outlet',
                        'location_id' => $outletTb->id,
                        'movement_type' => 'opening_balance',
                        'reference_type' => 'seed',
                        'reference_id' => $ingredient->id,
                    ],
                    [
                        'qty_in' => 1000,
                        'qty_out' => 0,
                        'note' => 'Opening balance seed for outlet TB',
                    ]
                );
            }

            if ($outletBx) {
                StockMovement::updateOrCreate(
                    [
                        'ingredient_id' => $ingredient->id,
                        'location_type' => 'outlet',
                        'location_id' => $outletBx->id,
                        'movement_type' => 'opening_balance',
                        'reference_type' => 'seed',
                        'reference_id' => $ingredient->id + 100000,
                    ],
                    [
                        'qty_in' => 800,
                        'qty_out' => 0,
                        'note' => 'Opening balance seed for outlet BX',
                    ]
                );
            }
        }
    }
}