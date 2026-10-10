<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock Adjustment VOID. Purely additive: every existing adjustment becomes 'completed' through the column
 * default (no backfill, no data is rewritten) and no stock_movements row is touched.
 *
 * status          'completed' | 'void'. The completed -> void change is the single atomic claim that makes a
 *                 VOID happen exactly once (see StockAdjustmentVoidService).
 * void_* (header) who/when/why, named like the same columns on sales_transactions.
 * void_stock_movement_id (item) the compensating movement that reversed this item's original movement; the
 *                 original stays in stock_movement_id. NULL for zero-difference items, which have nothing to reverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->string('status', 20)->default('completed')->after('note');
            $table->timestamp('void_at')->nullable()->after('status');
            $table->text('void_reason')->nullable()->after('void_at');
            $table->foreignId('void_by_user_id')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();

            $table->index('status');
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->foreignId('void_stock_movement_id')->nullable()->after('stock_movement_id')
                ->unique()->constrained('stock_movements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            // Foreign key, then its unique index, then the column: SQLite refuses to drop an indexed column.
            $table->dropForeign(['void_stock_movement_id']);
            $table->dropUnique(['void_stock_movement_id']);
            $table->dropColumn('void_stock_movement_id');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('void_by_user_id');
            $table->dropColumn(['status', 'void_at', 'void_reason']);
        });
    }
};
