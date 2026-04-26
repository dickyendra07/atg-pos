<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backoffice_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title');
            $table->text('message')->nullable();
            $table->foreignId('sales_transaction_id')->nullable()->constrained('sales_transactions')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'read_at']);
            $table->index(['outlet_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backoffice_notifications');
    }
};