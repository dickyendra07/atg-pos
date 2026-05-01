<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_transactions', 'promo_name')) {
                $table->string('promo_name')->nullable()->after('discount_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('sales_transactions', 'promo_name')) {
                $table->dropColumn('promo_name');
            }
        });
    }
};
