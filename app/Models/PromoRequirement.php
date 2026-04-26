<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoRequirement extends Model
{
    protected $fillable = [
        'promo_id',
        'product_variant_id',
        'qty',
    ];

    protected $casts = [
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
