<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->timestamp('void_at')->nullable()->after('status');
            $table->text('void_reason')->nullable()->after('void_at');
            $table->foreignId('void_by_user_id')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('void_by_user_id');
            $table->dropColumn(['void_at', 'void_reason']);
        });
    }
};