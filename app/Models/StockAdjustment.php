<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'reference',
        'location_type',
        'location_id',
        'user_id',
        'note',
    ];

    // status / void_* are deliberately NOT mass assignable: they only change through StockAdjustmentVoidService.
    protected $casts = [
        'void_at' => 'datetime',
    ];

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'void_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'location_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'location_id');
    }

    public function locationName(): string
    {
        $location = $this->location_type === 'warehouse' ? $this->warehouse : $this->outlet;

        return ($location?->name ?? ('#'.$this->location_id)).' ('.ucfirst($this->location_type).')';
    }

    public static function nextReference(): string
    {
        $prefix = 'ADJ-'.now()->format('Ymd').'-';
        $last = static::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
