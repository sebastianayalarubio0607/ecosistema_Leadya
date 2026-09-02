<?php

namespace App\Http\Services\GeneralLeads;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GeneralLeadsAdsTableCacheService
{
    private const TTL_SECONDS = 600;

    public function __construct(private readonly GeneralLeadsDashboardService $dashboard) {}

    public function has(GeneralLeadsFilters $filters, string $section): bool
    {
        return Cache::has($this->key($filters, $section));
    }

    public function table(GeneralLeadsFilters $filters, Request $request, string $section): array
    {
        $payload = Cache::remember(
            $this->key($filters, $section),
            self::TTL_SECONDS,
            fn () => $this->dashboard->adTablePayload($filters, $section)
        );

        return $this->dashboard->formatAdTableRows($request, $section, $payload['title'], $payload['rows']);
    }

    private function key(GeneralLeadsFilters $filters, string $section): string
    {
        $query = $filters->query();
        ksort($query);

        return 'general-leads:ads-table:v2:'.md5(json_encode([
            'section' => $section,
            'query' => $query,
        ]));
    }
}
