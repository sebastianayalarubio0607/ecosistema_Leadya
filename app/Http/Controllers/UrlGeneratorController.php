<?php

namespace App\Http\Controllers;

use App\Models\CampaignObjective;
use App\Models\Geo;
use App\Models\Language;
use App\Models\Origin;
use App\Models\Platform;
use App\Models\SiteLink;
use App\Models\Source;

class UrlGeneratorController extends Controller
{
    public function showForm()
    {
        $googleFallbackCodes = [
            'google',
            'gads',
            'google_ads',
            'google ads',
            'adwords',
        ];

        $sources = Source::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $origins = Origin::query()
            ->with('source')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $options = [
            'source' => $sources
                ->map(fn (Source $source) => [
                    'id' => (string) $source->id,
                    'code' => (string) $source->code,
                    'label' => (string) $source->name,
                ])
                ->values()
                ->all(),
            'origin' => $origins
                ->map(fn (Origin $origin) => [
                    'id' => (string) $origin->id,
                    'source_id' => (string) $origin->source_id,
                    'code' => (string) $origin->code,
                    'label' => (string) $origin->name,
                ])
                ->values()
                ->all(),
            'platform' => Platform::query()
                ->with('sources')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Platform $platform) => [
                    'id' => (string) $platform->id,
                    'source_ids' => $platform->sources->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                    'code' => (string) $platform->code,
                    'label' => (string) $platform->name,
                ])
                ->values()
                ->all(),
            'site_link' => SiteLink::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (SiteLink $siteLink) => [
                    'id' => (string) $siteLink->id,
                    'source_id' => (string) $siteLink->source_id,
                    'code' => (string) $siteLink->code,
                    'label' => (string) $siteLink->name,
                ])
                ->values()
                ->all(),
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

        $googleSourceIds = $sources
            ->filter(function (Source $source) use ($googleFallbackCodes) {
                $sourceName = mb_strtolower(trim((string) $source->name));
                $sourceCode = mb_strtolower(trim((string) $source->code));

                return str_contains($sourceName, 'google')
                    || in_array($sourceCode, $googleFallbackCodes, true);
            })
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        return view('generate-url', compact('options', 'googleSourceIds'));
    }
}
