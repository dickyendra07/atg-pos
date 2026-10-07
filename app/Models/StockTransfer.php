<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

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

    /** Insert attempts for an auto-generated transfer number (the first try plus retries on a number collision). */
    public const NUMBER_INSERT_ATTEMPTS = 5;

    /** Draws the pre-check tries before settling for a candidate (the insert-time retry covers the rest). */
    private const NUMBER_DRAWS = 20;

    /** True only while the number on this instance was generated here (never for a caller-supplied number). */
    private bool $numberWasGenerated = false;

    protected static function booted(): void
    {
        static::creating(function (self $transfer) {
            if (! $transfer->transfer_number) {
                $transfer->transfer_number = static::freshTransferNumber();
                $transfer->numberWasGenerated = true;
            }
        });
    }

    /**
     * Same format as always (TRF-<second>-<3 digits>). Returns a candidate that is not taken right now: a
     * cheap, bounded pre-check that keeps collisions rare. It does NOT guarantee uniqueness (two requests can
     * both see a candidate as free); the unique index decides, see performInsert().
     */
    public static function freshTransferNumber(): string
    {
        for ($draw = 1; $draw <= self::NUMBER_DRAWS; $draw++) {
            $number = 'TRF-' . now()->format('YmdHis') . '-' . random_int(100, 999);

            if (! static::where('transfer_number', $number)->exists()) {
                return $number;
            }
        }

        return $number;
    }

    /**
     * The unique index on transfer_number is the final authority. When an AUTO-GENERATED number loses the
     * race at INSERT time, draw a new one and insert again, at most NUMBER_INSERT_ATTEMPTS times in total.
     *
     * Each attempt runs in its own (nested) transaction, i.e. a savepoint when the caller already has a
     * transaction (both transfer screens do), so a failed insert never poisons the caller's transaction on
     * any database. Only a unique violation on transfer_number is retried: every other error, a number the
     * caller supplied, and the final failed attempt are thrown unchanged.
     */
    protected function performInsert(Builder $query)
    {
        $last = null;

        for ($attempt = 1; $attempt <= self::NUMBER_INSERT_ATTEMPTS; $attempt++) {
            try {
                return $this->getConnection()->transaction(fn () => parent::performInsert($query));
            } catch (UniqueConstraintViolationException $e) {
                if (! $this->numberWasGenerated || ! str_contains($e->getMessage(), 'transfer_number')) {
                    throw $e;
                }

                $last = $e;
                $this->transfer_number = null;   // the creating hook draws a fresh number on the next attempt
                $this->numberWasGenerated = false;
            }
        }

        throw $last;
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
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by_user_id');
    }
}