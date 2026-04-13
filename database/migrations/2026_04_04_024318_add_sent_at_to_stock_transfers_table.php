<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('receiver_name');
        });

        DB::statement('
            UPDATE stock_transfers
            SET sent_at = received_at
            WHERE sent_at IS NULL AND received_at IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });
    }
};