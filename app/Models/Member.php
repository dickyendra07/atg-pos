<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'points',
        'is_active',
    ];

    protected $casts = [
        'points' => 'integer',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }

    public function addPointsFromAmount(float $amount): int
    {
        $earnedPoints = (int) floor($amount / 10000);

        if ($earnedPoints > 0) {
            $this->increment('points', $earnedPoints);
            $this->refresh();
        }

        return $earnedPoints;
    }
}