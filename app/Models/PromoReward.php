<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoReward extends Model
{
    protected $fillable = [
        'promo_id',
        'reward_type',
        'reward_value',
        'product_variant_id',
        'qty',
    ];

    protected $casts = [
        'reward_value' => 'decimal:2',
        'qty' => 'decimal:2',
    ];

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
