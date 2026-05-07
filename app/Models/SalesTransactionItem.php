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
        'promo_name',
        'promo_discount_amount',
        'final_line_total',
    ];

    protected $casts = [
        'less_sugar' => 'boolean',
        'less_ice' => 'boolean',
        'promo_discount_amount' => 'decimal:2',
        'final_line_total' => 'decimal:2',
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