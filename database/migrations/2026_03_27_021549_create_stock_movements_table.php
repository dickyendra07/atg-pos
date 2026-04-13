<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->string('location_type'); // warehouse / outlet
            $table->unsignedBigInteger('location_id');

            $table->string('movement_type'); 
            // opening_balance / transfer_in / transfer_out / sales_usage / opname_adjustment / manual_adjustment

            $table->decimal('qty_in', 12, 2)->default(0);
            $table->decimal('qty_out', 12, 2)->default(0);

            $table->string('reference_type')->nullable(); 
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};