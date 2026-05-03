<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_pins', function (Blueprint $table) {
            $table->id();
            $table->string('pin_code', 20);
            $table->string('purpose')->default('all');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('used_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sales_transaction_id')->nullable()->constrained('sales_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['pin_code', 'purpose', 'used_at']);
            $table->index(['expires_at', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_pins');
    }
};
