<?php

namespace App\Http\Services\Meta;

use Illuminate\Support\Facades\Http;

/**
 * Servicio para interactuar con la API de Graph de Meta
 */
class MetaGraphService
{
    public function get(string $path, array $query = []): array
    {
        $response = Http::retry(3, 1000)
            ->acceptJson()
            ->timeout(60)
            ->get($this->url($path), $query);

        return $this->decode($response->throw());
    }

    public function paginatedGet(string $path, array $query = []): array
    {
        $items = [];
        $nextUrl = $this->url($path);
        $nextQuery = $query;
        $accessToken = $query['access_token'] ?? null;

        do {
            $response = Http::retry(3, 1000)
                ->acceptJson()
                ->timeout(60)
                ->get($nextUrl, $nextQuery);

            $payload = $this->decode($response->throw());
            $items = array_merge($items, $payload['data'] ?? []);
            $nextUrl = data_get($payload, 'paging.next');
            $nextQuery = [];

            if ($nextUrl && $accessToken && ! $this->urlHasQueryParam($nextUrl, 'access_token')) {
                $nextUrl = $this->appendQueryParams($nextUrl, ['access_token' => $accessToken]);
            }
        } while ($nextUrl);

        return $items;
    }

    private function appendQueryParams(string $url, array $params): string
    {
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        if ($query === '') {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').$query;
    }

    private function urlHasQueryParam(string $url, string $param): bool
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return false;
        }

        parse_str($query, $params);

        return filled($params[$param] ?? null);
    }

    private function url(string $path): string
    {
        $version = trim((string) config('services.meta.graph_version', 'v24.0'), '/');
        $cleanPath = ltrim($path, '/');

        return "https://graph.facebook.com/{$version}/{$cleanPath}";
    }

    private function decode($response): array
    {
        $payload = $response->json();

        if (isset($payload['error'])) {
            throw new \RuntimeException(data_get($payload, 'error.message', 'Meta API error'));
        }

        return is_array($payload) ? $payload : [];
    }
}
