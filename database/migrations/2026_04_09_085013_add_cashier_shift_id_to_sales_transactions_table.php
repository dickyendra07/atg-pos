<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->foreignId('cashier_shift_id')
                ->nullable()
                ->after('outlet_id')
                ->constrained('cashier_shifts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_shift_id');
        });
    }
};