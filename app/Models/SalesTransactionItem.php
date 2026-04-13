<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTransactionItem extends Model
{
    protected $fillable = [
        'sales_transaction_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'less_sugar',
        'less_ice',
        'qty',
        'price',
        'line_total',
    ];

    protected $casts = [
        'less_sugar' => 'boolean',
        'less_ice' => 'boolean',
        'qty' => 'decimal:2',
        'price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'sales_transaction_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}