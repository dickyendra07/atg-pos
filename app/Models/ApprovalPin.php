<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalPin extends Model
{
    protected $fillable = [
        'pin_code',
        'purpose',
        'expires_at',
        'used_at',
        'created_by_user_id',
        'used_by_user_id',
        'sales_transaction_id',
        'outlet_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'sales_transaction_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function isUsableFor(string $purpose, ?int $outletId = null, ?int $transactionId = null): bool
    {
        if ($this->used_at) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if (! in_array($this->purpose, [$purpose, 'all'], true)) {
            return false;
        }

        if ($outletId !== null && (int) ($this->outlet_id ?? 0) !== (int) $outletId) {
            return false;
        }

        if ($transactionId !== null && (int) ($this->sales_transaction_id ?? 0) !== (int) $transactionId) {
            return false;
        }

        return true;
    }
}
