<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_variant_outlet') || ! Schema::hasTable('product_outlet')) {
            return;
        }

        $rows = DB::table('product_variants as variants')
            ->join('product_outlet', 'product_outlet.product_id', '=', 'variants.product_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('product_variant_outlet')
                    ->whereColumn('product_variant_outlet.product_variant_id', 'variants.id');
            })
            ->selectRaw('variants.id as product_variant_id, product_outlet.outlet_id, CURRENT_TIMESTAMP as created_at, CURRENT_TIMESTAMP as updated_at')
            ->get()->map(fn ($row) => (array) $row)->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('product_variant_outlet')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        // Explicit assignments may be edited after migration and are intentionally preserved.
    }
};
