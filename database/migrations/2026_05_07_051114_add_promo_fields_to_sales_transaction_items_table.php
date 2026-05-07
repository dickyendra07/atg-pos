<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transaction_items', function (Blueprint $table) {
            $table->string('promo_name')->nullable()->after('line_total');
            $table->decimal('promo_discount_amount', 15, 2)->default(0)->after('promo_name');
            $table->decimal('final_line_total', 15, 2)->nullable()->after('promo_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales_transaction_items', function (Blueprint $table) {
            $table->dropColumn([
                'promo_name',
                'promo_discount_amount',
                'final_line_total',
            ]);
        });
    }
};
