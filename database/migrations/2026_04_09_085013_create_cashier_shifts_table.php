<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();

            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();

            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('closing_cash_actual', 12, 2)->nullable();

            $table->text('closing_note')->nullable();

            $table->string('status')->default('open');
            // open / closed

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_shifts');
    }
};