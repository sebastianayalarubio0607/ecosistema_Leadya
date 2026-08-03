<?php

namespace App\Http\Services\GeneralLeads;

use Illuminate\Support\Str;

class GeneralLeadsPresentation
{
    public const NULL_SOURCE = '__NULL_SOURCE__';
    public const MISSING_SOURCE = '__MISSING_SOURCE__';
    public const NULL_ORIGIN = '__NULL_ORIGIN__';
    public const MISSING_ORIGIN = '__MISSING_ORIGIN__';
    public const NULL_TYPE = '__NULL_TYPE__';
    public const MISSING_TYPE = '__MISSING_TYPE__';

    public static function title(mixed $value, string $fallback = 'Sin Dato'): string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? $fallback : Str::of($text)->lower()->title()->toString();
    }

    public static function money(mixed $value): string
    {
        return '$ '.number_format((float) $value, 2, ',', '.');
    }
}
