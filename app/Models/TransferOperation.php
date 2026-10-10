<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The idempotency record of one transfer-creating business operation (see the create_transfer_operations_table
 * migration). One row per operation, however many stock_transfers rows it created.
 */
class TransferOperation extends Model
{
    public const KIND_GENERAL_TRANSFER = 'general_transfer';

    protected $fillable = [
        'operation_key',
        'user_id',
        'kind',
        'fingerprint',
        'transfer_ids',
    ];

    protected $casts = [
        'transfer_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
