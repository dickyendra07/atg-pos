<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'price_dine_in')) {
                $table->decimal('price_dine_in', 12, 2)->default(0)->after('price');
            }

            if (! Schema::hasColumn('product_variants', 'price_delivery')) {
                $table->decimal('price_delivery', 12, 2)->default(0)->after('price_dine_in');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'price_delivery')) {
                $table->dropColumn('price_delivery');
            }

            if (Schema::hasColumn('product_variants', 'price_dine_in')) {
                $table->dropColumn('price_dine_in');
            }
        });
    }
};