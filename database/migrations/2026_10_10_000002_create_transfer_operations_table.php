<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency record for ONE business operation that creates transfers (a General Transfer, which may be a bulk of
 * many items and therefore many stock_transfers rows). Purely additive: no existing table is touched and nothing is
 * backfilled; code that does not know the table simply ignores it.
 *
 * operation_key  Random UUID issued by the server with the create form. The UNIQUE index is the atomic claim: the
 *                row is inserted as the first statement of the posting transaction, so a second request with the
 *                same key waits for the first and then sees its committed result (or its rollback).
 * user_id        Who claimed the key. nullOnDelete: deleting a user keeps the record (and the replay protection).
 * kind           Which kind of operation ('general_transfer'), so another module can reuse the table.
 * fingerprint    SHA-256 of the normalized business payload; a replay with another payload is refused.
 * transfer_ids   Every stock_transfers id the operation created (JSON list), written in the same transaction.
 *
 * created_at is indexed for the retention clean-up that is planned separately (no pruning exists yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_operations', function (Blueprint $table) {
            $table->id();
            $table->char('operation_key', 36)->unique('transfer_operations_operation_key_unique');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 40);
            $table->char('fingerprint', 64);
            $table->json('transfer_ids');
            $table->timestamps();

            $table->index('created_at', 'transfer_operations_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_operations');
    }
};
