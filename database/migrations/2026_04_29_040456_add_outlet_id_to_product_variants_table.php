<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'outlet_id')) {
                $table->foreignId('outlet_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('outlets')
                    ->nullOnDelete();

                $table->index(['outlet_id', 'product_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'outlet_id')) {
                $table->dropForeign(['outlet_id']);
                $table->dropIndex(['outlet_id', 'product_id']);
                $table->dropColumn('outlet_id');
            }
        });
    }
};
