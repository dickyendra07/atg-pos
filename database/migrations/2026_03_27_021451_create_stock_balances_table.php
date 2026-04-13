<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->string('location_type'); // warehouse / outlet
            $table->unsignedBigInteger('location_id');
            $table->decimal('qty_on_hand', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['ingredient_id', 'location_type', 'location_id'], 'stock_balance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};