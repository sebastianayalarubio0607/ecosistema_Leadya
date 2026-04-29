<?php

namespace App\Support;

class SensitiveValue
{
    public static function mask(?string $value, int $visibleStart = 4, int $visibleEnd = 4): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $length = mb_strlen($value);

        if ($length <= ($visibleStart + $visibleEnd)) {
            return str_repeat('*', max($length, 8));
        }

        $start = mb_substr($value, 0, $visibleStart);
        $end = mb_substr($value, -1 * $visibleEnd);

        return $start.str_repeat('*', max($length - ($visibleStart + $visibleEnd), 8)).$end;
    }

    public static function redact(?string $value): string
    {
        return $value ? self::mask($value, 2, 2) : '—';
    }
}
