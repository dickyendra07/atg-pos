<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_production_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_production_id')
                ->constrained('ingredient_productions')
                ->cascadeOnDelete();

            $table->foreignId('ingredient_id')
                ->constrained('ingredients')
                ->cascadeOnDelete();

            $table->string('item_type'); // input / output
            $table->decimal('qty', 12, 2);
            $table->string('unit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_production_items');
    }
};