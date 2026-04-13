<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('ingredient_category_id')->constrained('ingredient_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('unit');
            $table->decimal('cost_per_unit', 12, 2)->default(0);
            $table->decimal('minimum_stock', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};