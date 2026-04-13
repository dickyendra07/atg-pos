<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('from_location_type')->nullable()->after('transfer_number');
            $table->unsignedBigInteger('from_location_id')->nullable()->after('from_location_type');
            $table->string('to_location_type')->nullable()->after('from_location_id');
            $table->unsignedBigInteger('to_location_id')->nullable()->after('to_location_type');
        });

        DB::table('stock_transfers')
            ->whereNull('from_location_type')
            ->update([
                'from_location_type' => 'warehouse',
                'to_location_type' => 'outlet',
            ]);

        DB::statement("
            UPDATE stock_transfers
            SET from_location_id = warehouse_id
            WHERE from_location_id IS NULL
        ");

        DB::statement("
            UPDATE stock_transfers
            SET to_location_id = outlet_id
            WHERE to_location_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn([
                'from_location_type',
                'from_location_id',
                'to_location_type',
                'to_location_id',
            ]);
        });
    }
};