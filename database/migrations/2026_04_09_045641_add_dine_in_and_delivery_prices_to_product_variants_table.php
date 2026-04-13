<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('price_dine_in', 12, 2)->nullable()->after('price');
            $table->decimal('price_delivery', 12, 2)->nullable()->after('price_dine_in');
        });

        DB::statement('UPDATE product_variants SET price_dine_in = price WHERE price_dine_in IS NULL');
        DB::statement('UPDATE product_variants SET price_delivery = price WHERE price_delivery IS NULL');
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['price_dine_in', 'price_delivery']);
        });
    }
};