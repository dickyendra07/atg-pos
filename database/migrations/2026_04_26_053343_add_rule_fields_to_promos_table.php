<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->foreignId('requirement_product_variant_id')
                ->nullable()
                ->after('name')
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->decimal('requirement_qty', 12, 2)
                ->default(1)
                ->after('requirement_product_variant_id');

            $table->string('reward_type')
                ->default('discount_amount')
                ->after('requirement_qty');

            $table->decimal('reward_value', 12, 2)
                ->default(0)
                ->after('reward_type');

            $table->foreignId('reward_product_variant_id')
                ->nullable()
                ->after('reward_value')
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->decimal('reward_qty', 12, 2)
                ->default(1)
                ->after('reward_product_variant_id');

            $table->index(['requirement_product_variant_id']);
            $table->index(['reward_product_variant_id']);
            $table->index(['reward_type']);
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requirement_product_variant_id');
            $table->dropConstrainedForeignId('reward_product_variant_id');

            $table->dropColumn([
                'requirement_qty',
                'reward_type',
                'reward_value',
                'reward_qty',
            ]);
        });
    }
};
