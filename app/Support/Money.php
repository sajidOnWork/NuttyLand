<?php

namespace App\Support;

class Money
{
    /** Format integer cents as Australian dollars, e.g. 1250 → "$12.50". */
    public static function format(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }

    public static function toCents(float|int|string $dollars): int
    {
        return (int) round(((float) $dollars) * 100);
    }

    /** 500 → "500g", 1000 → "1kg". */
    public static function weight(int $grams): string
    {
        return $grams >= 1000 && $grams % 1000 === 0 ? ($grams / 1000).'kg' : $grams.'g';
    }
}
