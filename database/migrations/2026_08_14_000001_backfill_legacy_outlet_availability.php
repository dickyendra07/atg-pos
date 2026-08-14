<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outlets')) {
            return;
        }

        $outletIds = DB::table('outlets')->pluck('id');

        if ($outletIds->isEmpty()) {
            return;
        }

        $now = now();

        if (Schema::hasTable('product_outlet')) {
            $legacyUnassignedProductIds = DB::table('products')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('product_outlet')
                        ->whereColumn('product_outlet.product_id', 'products.id');
                })
                ->pluck('id');

            if (Schema::hasTable('product_variant_outlet')) {
                $variantAssignments = DB::table('product_variant_outlet')
                    ->join('product_variants', 'product_variants.id', '=', 'product_variant_outlet.product_variant_id')
                    ->whereIn('product_variants.product_id', $legacyUnassignedProductIds)
                    ->select('product_variants.product_id', 'product_variant_outlet.outlet_id')
                    ->distinct()
                    ->get()
                    ->map(fn ($row) => [
                        'product_id' => $row->product_id,
                        'outlet_id' => $row->outlet_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                if (! empty($variantAssignments)) {
                    DB::table('product_outlet')->insertOrIgnore($variantAssignments);
                }
            }

            if (Schema::hasColumn('product_variants', 'outlet_id')) {
                $legacyAssignments = DB::table('product_variants')
                    ->whereNotNull('outlet_id')
                    ->whereIn('product_id', $legacyUnassignedProductIds)
                    ->select('product_id', 'outlet_id')
                    ->distinct()
                    ->get()
                    ->map(fn ($row) => [
                        'product_id' => $row->product_id,
                        'outlet_id' => $row->outlet_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                if (! empty($legacyAssignments)) {
                    DB::table('product_outlet')->insertOrIgnore($legacyAssignments);
                }
            }

            $unassignedProductIds = DB::table('products')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('product_outlet')
                        ->whereColumn('product_outlet.product_id', 'products.id');
                })
                ->pluck('id');

            foreach ($unassignedProductIds->chunk(100) as $productIds) {
                $rows = $productIds->flatMap(fn ($productId) => $outletIds->map(fn ($outletId) => [
                    'product_id' => $productId,
                    'outlet_id' => $outletId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]))->all();

                DB::table('product_outlet')->insertOrIgnore($rows);
            }
        }

        if (Schema::hasTable('ingredient_outlet')) {
            $unassignedIngredientIds = DB::table('ingredients')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('ingredient_outlet')
                        ->whereColumn('ingredient_outlet.ingredient_id', 'ingredients.id');
                })
                ->pluck('id');

            foreach ($unassignedIngredientIds->chunk(100) as $ingredientIds) {
                $rows = $ingredientIds->flatMap(fn ($ingredientId) => $outletIds->map(fn ($outletId) => [
                    'ingredient_id' => $ingredientId,
                    'outlet_id' => $outletId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]))->all();

                DB::table('ingredient_outlet')->insertOrIgnore($rows);
            }
        }
    }

    public function down(): void
    {
        // Intentionally keep compatibility assignments; they may have been edited after migration.
    }
};
