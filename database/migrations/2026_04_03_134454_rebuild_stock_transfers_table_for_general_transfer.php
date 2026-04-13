<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers_new', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->nullable()->unique();

            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable();

            $table->unsignedBigInteger('ingredient_id');
            $table->decimal('qty', 15, 2)->default(0);
            $table->unsignedBigInteger('transferred_by_user_id')->nullable();
            $table->string('status')->default('completed');
            $table->string('note')->nullable();

            $table->string('from_location_type')->nullable();
            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->string('to_location_type')->nullable();
            $table->unsignedBigInteger('to_location_id')->nullable();

            $table->timestamps();
        });

        DB::statement('
            INSERT INTO stock_transfers_new (
                id,
                transfer_number,
                warehouse_id,
                outlet_id,
                ingredient_id,
                qty,
                transferred_by_user_id,
                status,
                note,
                from_location_type,
                from_location_id,
                to_location_type,
                to_location_id,
                created_at,
                updated_at
            )
            SELECT
                id,
                transfer_number,
                warehouse_id,
                outlet_id,
                ingredient_id,
                qty,
                transferred_by_user_id,
                status,
                note,
                from_location_type,
                from_location_id,
                to_location_type,
                to_location_id,
                created_at,
                updated_at
            FROM stock_transfers
        ');

        Schema::drop('stock_transfers');
        Schema::rename('stock_transfers_new', 'stock_transfers');
    }

    public function down(): void
    {
        // biarkan kosong dulu
    }
};