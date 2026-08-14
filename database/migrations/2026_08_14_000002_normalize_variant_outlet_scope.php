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

        $scopeRows = DB::table('product_variant_outlet as variant_outlet')
            ->join('product_variants as variant', 'variant.id', '=', 'variant_outlet.product_variant_id')
            ->leftJoin('product_outlet as product_outlet', function ($join) {
                $join->on('product_outlet.product_id', '=', 'variant.product_id')
                    ->on('product_outlet.outlet_id', '=', 'variant_outlet.outlet_id');
            })
            ->select([
                'variant_outlet.id as pivot_id',
                'variant_outlet.product_variant_id as variant_id',
                'product_outlet.id as valid_parent_pivot_id',
            ])
            ->get();

        $invalidPivotIds = $scopeRows
            ->whereNull('valid_parent_pivot_id')
            ->pluck('pivot_id');

        $variantIdsToDeactivate = $scopeRows
            ->groupBy('variant_id')
            ->filter(fn ($rows) => $rows->every(fn ($row) => $row->valid_parent_pivot_id === null))
            ->keys();

        foreach ($invalidPivotIds->chunk(500) as $pivotIds) {
            DB::table('product_variant_outlet')->whereIn('id', $pivotIds->all())->delete();
        }

        foreach ($variantIdsToDeactivate->chunk(500) as $variantIds) {
            DB::table('product_variants')
                ->whereIn('id', $variantIds->all())
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Invalid child assignments cannot be restored safely without overriding later edits.
    }
};
