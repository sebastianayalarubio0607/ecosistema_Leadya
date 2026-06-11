<?php

namespace App\Http\Services\Lead;

use App\Models\Lead;

class LeadAdSourceClassifier
{
    private const META_SOURCE_NAME = 'Meta Ads';

    private const GOOGLE_SOURCE_NAME = 'Google Ads';

    private const META_ORIGINS = [
        'fb',
        'meta',
        'ig',
        'wa',
        'mg',
        'fbm',
        'th',
    ];

    private const GOOGLE_ORIGINS = [
        'google',
        'gads',
        'google_ads',
        'google ads',
        'adwords',
    ];

    public static function classify(Lead $lead): array
    {
        $lead->loadMissing('campaignOrigin.source');

        $sourceName = self::normalizeSourceName($lead->campaignOrigin?->source?->name);
        $origin = self::normalizeOrigin($lead->campaign_origin);

        $isMetaSource = strcasecmp($sourceName, self::META_SOURCE_NAME) === 0;
        $isGoogleSource = strcasecmp($sourceName, self::GOOGLE_SOURCE_NAME) === 0;

        return [
            'source_name' => $sourceName !== '' ? $sourceName : null,
            'is_meta_ads' => $isMetaSource
                || (! $isGoogleSource && in_array($origin, self::META_ORIGINS, true)),
            'is_google_ads' => $isGoogleSource
                || (! $isMetaSource && in_array($origin, self::GOOGLE_ORIGINS, true)),
        ];
    }

    private static function normalizeSourceName(?string $sourceName): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $sourceName));
    }

    private static function normalizeOrigin(?string $origin): string
    {
        return mb_strtolower(trim((string) $origin));
    }
}
