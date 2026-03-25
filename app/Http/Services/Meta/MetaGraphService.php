<?php

namespace App\Http\Services\Meta;

use Illuminate\Support\Facades\Http;

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

        do {
            $response = Http::retry(3, 1000)
                ->acceptJson()
                ->timeout(60)
                ->get($nextUrl, $nextQuery);

            $payload = $this->decode($response->throw());
            $items = array_merge($items, $payload['data'] ?? []);
            $nextUrl = data_get($payload, 'paging.next');
            $nextQuery = [];
        } while ($nextUrl);

        return $items;
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
