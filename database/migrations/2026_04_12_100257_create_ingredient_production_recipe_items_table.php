<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_production_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_production_recipe_id')
                ->constrained('ingredient_production_recipes')
                ->cascadeOnDelete();

            $table->foreignId('input_ingredient_id')
                ->constrained('ingredients')
                ->cascadeOnDelete();

            $table->decimal('qty', 12, 2);
            $table->string('unit');
            $table->timestamps();

            $table->unique(
                ['ingredient_production_recipe_id', 'input_ingredient_id'],
                'ingredient_production_recipe_items_unique_input'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_production_recipe_items');
    }
};