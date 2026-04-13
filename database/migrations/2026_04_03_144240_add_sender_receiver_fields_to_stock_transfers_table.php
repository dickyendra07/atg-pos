<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('sender_name')->nullable()->after('note');
            $table->string('receiver_name')->nullable()->after('sender_name');
            $table->timestamp('received_at')->nullable()->after('receiver_name');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn([
                'sender_name',
                'receiver_name',
                'received_at',
            ]);
        });
    }
};