<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_pins', function (Blueprint $table) {
            if (! Schema::hasColumn('approval_pins', 'outlet_id')) {
                $table->foreignId('outlet_id')->nullable()->after('sales_transaction_id')->constrained('outlets')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('approval_pins', function (Blueprint $table) {
            if (Schema::hasColumn('approval_pins', 'outlet_id')) {
                $table->dropConstrainedForeignId('outlet_id');
            }
        });
    }
};
