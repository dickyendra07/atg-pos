<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            if (! Schema::hasColumn('promos', 'requirement_logic')) {
                $table->string('requirement_logic', 10)
                    ->default('and')
                    ->after('name')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            if (Schema::hasColumn('promos', 'requirement_logic')) {
                $table->dropIndex(['requirement_logic']);
                $table->dropColumn('requirement_logic');
            }
        });
    }
};
