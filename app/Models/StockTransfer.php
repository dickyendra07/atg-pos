<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransfer extends Model
{
    protected $fillable = [
        'transfer_number',
        'warehouse_id',
        'outlet_id',
        'ingredient_id',
        'qty',
        'transferred_by_user_id',
        'status',
        'note',
        'from_location_type',
        'from_location_id',
        'to_location_type',
        'to_location_id',
        'sender_name',
        'receiver_name',
        'sent_at',
        'received_at',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $transfer) {
            if (! $transfer->transfer_number) {
                $transfer->transfer_number = 'TRF-' . now()->format('YmdHis') . '-' . random_int(100, 999);
            }
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by_user_id');
    }
}