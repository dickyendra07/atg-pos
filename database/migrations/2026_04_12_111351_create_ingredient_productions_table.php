<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_production_recipe_id')
                ->constrained('ingredient_production_recipes')
                ->cascadeOnDelete();

            $table->foreignId('output_ingredient_id')
                ->constrained('ingredients')
                ->cascadeOnDelete();

            $table->string('location_type'); // warehouse / outlet
            $table->unsignedBigInteger('location_id');

            $table->decimal('batch_qty', 12, 2)->default(1);
            $table->decimal('output_qty', 12, 2)->default(0);
            $table->string('output_unit');

            $table->string('status')->default('completed');
            $table->text('note')->nullable();

            $table->foreignId('produced_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('produced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_productions');
    }
};