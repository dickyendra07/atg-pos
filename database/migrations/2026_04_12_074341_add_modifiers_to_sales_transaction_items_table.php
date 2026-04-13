<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transaction_items', function (Blueprint $table) {
            $table->boolean('less_sugar')->default(false)->after('variant_name');
            $table->boolean('less_ice')->default(false)->after('less_sugar');
        });
    }

    public function down(): void
    {
        Schema::table('sales_transaction_items', function (Blueprint $table) {
            $table->dropColumn(['less_sugar', 'less_ice']);
        });
    }
};