<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('grand_total');
            $table->string('payment_status')->default('paid')->after('payment_method');
            $table->decimal('amount_paid', 12, 2)->default(0)->after('payment_status');
            $table->decimal('change_amount', 12, 2)->default(0)->after('amount_paid');
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_status',
                'amount_paid',
                'change_amount',
            ]);
        });
    }
};