<?php

namespace App\Support;

class MetaAdAccountId
{
    public static function normalize(string $value): string
    {
        $value = trim($value);

        return str_starts_with($value, 'act_') ? substr($value, 4) : $value;
    }

    public static function act(string $value): string
    {
        $value = trim($value);

        return str_starts_with($value, 'act_') ? $value : 'act_'.$value;
    }

    public static function candidates(string $value): array
    {
        $value = trim($value);
        $normalized = self::normalize($value);

        return array_values(array_unique(array_filter([
            $value,
            $normalized,
            $normalized !== '' ? 'act_'.$normalized : null,
        ])));
    }
}
