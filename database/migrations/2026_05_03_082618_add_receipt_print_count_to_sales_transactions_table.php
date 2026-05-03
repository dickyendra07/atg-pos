<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_transactions', 'receipt_print_count')) {
                $table->unsignedInteger('receipt_print_count')->default(0)->after('promo_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('sales_transactions', 'receipt_print_count')) {
                $table->dropColumn('receipt_print_count');
            }
        });
    }
};
