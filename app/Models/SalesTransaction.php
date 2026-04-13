<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesTransaction extends Model
{
    protected $fillable = [
        'transaction_number',
        'user_id',
        'outlet_id',
        'member_id',
        'cashier_shift_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'payment_method',
        'payment_status',
        'amount_paid',
        'change_amount',
        'status',
        'void_at',
        'void_reason',
        'void_by_user_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'void_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function voidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'void_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesTransactionItem::class);
    }
}