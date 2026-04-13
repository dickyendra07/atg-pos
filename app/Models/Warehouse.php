<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'location_id')
            ->where('location_type', 'warehouse');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'location_id')
            ->where('location_type', 'warehouse');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class);
    }
}