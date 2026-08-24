<?php

namespace App\Http\Services\AiConnectors;

use App\Models\AiConnector;
use Closure;
use Illuminate\Support\Facades\Cache;

class AiConnectorQueryCacheService
{
    public function remember(AiConnector $connector, string $tool, array $arguments, Closure $resolver): array
    {
        $ttl = max(0, (int) $connector->cache_ttl_seconds);
        $key = $this->cacheKey($connector, $tool, $arguments);

        if ($ttl === 0) {
            return ['payload' => $resolver(), 'cache_hit' => false, 'query_hash' => $this->queryHash($tool, $arguments)];
        }

        if (Cache::has($key)) {
            return ['payload' => Cache::get($key), 'cache_hit' => true, 'query_hash' => $this->queryHash($tool, $arguments)];
        }

        $payload = $resolver();
        Cache::put($key, $payload, $ttl);

        return ['payload' => $payload, 'cache_hit' => false, 'query_hash' => $this->queryHash($tool, $arguments)];
    }

    public function queryHash(string $tool, array $arguments): string
    {
        return hash('sha256', json_encode([
            'tool' => $tool,
            'arguments' => $this->canonicalize($arguments),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function cacheKey(AiConnector $connector, string $tool, array $arguments): string
    {
        return 'ai-connector:'.$connector->id.':tool-result:'.$this->queryHash($tool, $arguments);
    }

    private function canonicalize(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }
}
