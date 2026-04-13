<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    protected $fillable = [
        'user_id',
        'outlet_id',
        'started_at',
        'ended_at',
        'opening_cash',
        'closing_cash_actual',
        'closing_note',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'opening_cash' => 'decimal:2',
        'closing_cash_actual' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class, 'cashier_shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->ended_at === null;
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed' && $this->ended_at !== null;
    }
}