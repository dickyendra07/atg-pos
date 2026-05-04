<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_outlet', function (Blueprint $table) {
            if (! Schema::hasColumn('user_outlet', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('user_outlet', 'outlet_id')) {
                $table->foreignId('outlet_id')->nullable()->after('user_id')->constrained('outlets')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_outlet', function (Blueprint $table) {
            if (Schema::hasColumn('user_outlet', 'outlet_id')) {
                $table->dropConstrainedForeignId('outlet_id');
            }

            if (Schema::hasColumn('user_outlet', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
