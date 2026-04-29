<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_outlet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained('promos')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promo_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_outlet');
    }
};
