<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tombstone marker for the temporary Back Office cleanup delete (Product, Variant, Ingredient).
 *
 * Additive and nullable: nothing existing changes, rolling back only drops the column. A row with
 * deleted_at set is "removed from the active system" (hidden everywhere operational) while every
 * historical record that points at it (sales, stock movements, transfers, ...) stays intact.
 */
return new class extends Migration
{
    private const TABLES = ['products', 'product_variants', 'ingredients'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('deleted_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['deleted_at']);
                $table->dropColumn('deleted_at');
            });
        }
    }
};
