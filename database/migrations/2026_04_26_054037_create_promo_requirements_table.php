<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained('promos')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('qty', 12, 2)->default(1);
            $table->timestamps();

            $table->index(['promo_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_requirements');
    }
};
