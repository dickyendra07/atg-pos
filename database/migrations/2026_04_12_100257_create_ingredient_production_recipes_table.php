<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_production_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('output_ingredient_id')
                ->constrained('ingredients')
                ->cascadeOnDelete();

            $table->string('name');
            $table->decimal('output_qty', 12, 2)->default(1);
            $table->string('output_unit');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('output_ingredient_id', 'ingredient_production_recipes_output_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_production_recipes');
    }
};