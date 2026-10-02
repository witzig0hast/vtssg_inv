<?php

namespace App\Support;

class Money
{
    /** "1.234,56" | "1234.56" | "12" → Cent */
    public static function toCents(null|string|int|float $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        return (int) round(((float) $s) * 100);
    }

    public static function format(?int $cents): string
    {
        return $cents === null ? '—' : number_format($cents / 100, 2, ',', '.').' €';
    }

    public static function input(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, ',', '');
    }
}
