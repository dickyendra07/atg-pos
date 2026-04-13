<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('ingredient_type')
                ->default('raw')
                ->after('unit');
        });

        DB::table('ingredients')
            ->whereNull('ingredient_type')
            ->update([
                'ingredient_type' => 'raw',
            ]);
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('ingredient_type');
        });
    }
};