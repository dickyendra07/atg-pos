<?php

namespace App\Support;

final class QuantityFormatter
{
    public static function twoDecimals(int|float|string|null $value): string
    {
        $number = (float) ($value ?? 0);
        $normalized = abs($number) < 0.005 ? 0.0 : $number;

        return number_format($normalized, 2, ',', '.');
    }
}
