<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReceipt extends Model
{
    protected $fillable = [
        'reference_number', 'supplier_name', 'destination_type', 'destination_id',
        'received_date', 'status', 'total_amount', 'notes', 'created_by_user_id',
    ];

    protected $casts = [
        'received_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'destination_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_id');
    }

    public function destinationName(): string
    {
        return $this->destination_type === 'outlet'
            ? ($this->outlet?->name ?? 'Outlet #'.$this->destination_id)
            : ($this->warehouse?->name ?? 'Warehouse #'.$this->destination_id);
    }
}
