<?php

namespace App\Http\Controllers;

use App\Models\CampaignObjective;
use App\Models\Geo;
use App\Models\Language;
use App\Models\Origin;
use App\Models\Platform;

class UrlGeneratorController extends Controller
{
    public function showForm()
    {
        $googleOriginFallbackCodes = [
            'google',
            'gads',
            'google_ads',
            'google ads',
            'adwords',
        ];

        $origins = Origin::query()
            ->with('source')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $options = [
            'origin' => $origins
                ->pluck('name', 'code')
                ->toArray(),
            'platform' => Platform::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray(),
            'geo' => Geo::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray(),
            'language' => Language::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray(),
            'campaign_objective' => CampaignObjective::query()
                ->where('estado', true)
                ->orderBy('nombre')
                ->pluck('nombre', 'id')
                ->toArray(),
        ];

        $googleOriginCodes = $origins
            ->filter(function (Origin $origin) use ($googleOriginFallbackCodes) {
                $sourceName = mb_strtolower(trim((string) $origin->source?->name));
                $originCode = mb_strtolower(trim((string) $origin->code));

                return str_contains($sourceName, 'google')
                    || in_array($originCode, $googleOriginFallbackCodes, true);
            })
            ->pluck('code')
            ->values()
            ->all();

        return view('generate-url', compact('options', 'googleOriginCodes'));
    }
}
