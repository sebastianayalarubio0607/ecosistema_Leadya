<?php

namespace App\Http\Services\AiConnectors;

use App\Models\AiConnector;
use Illuminate\Support\Facades\Cache;

class AiConnectorRateLimitService
{
    public function assertAllowed(AiConnector $connector): void
    {
        $this->assertMinimumInterval($connector);
        $this->assertWindow(
            key: "ai-connector:{$connector->id}:minute:".now()->format('YmdHi'),
            maxAttempts: max(1, (int) $connector->max_requests_per_minute),
            ttlSeconds: 70,
            retryAfter: 60,
            message: 'El conector supero el limite de consultas por minuto.'
        );
        $this->assertWindow(
            key: "ai-connector:{$connector->id}:day:".now()->format('Ymd'),
            maxAttempts: max(1, (int) $connector->max_requests_per_day),
            ttlSeconds: now()->endOfDay()->diffInSeconds(now()) + 5,
            retryAfter: now()->endOfDay()->diffInSeconds(now()),
            message: 'El conector supero el limite diario de consultas.'
        );
    }

    private function assertMinimumInterval(AiConnector $connector): void
    {
        $seconds = max(0, (int) $connector->min_request_interval_seconds);
        if ($seconds === 0) {
            return;
        }

        $key = "ai-connector:{$connector->id}:last-request";
        $lastRequestAt = (int) Cache::get($key, 0);
        $elapsed = now()->timestamp - $lastRequestAt;

        if ($lastRequestAt > 0 && $elapsed < $seconds) {
            throw new AiConnectorRateLimitException(
                retryAfter: max(1, $seconds - $elapsed),
                message: 'El conector esta enviando consultas demasiado seguidas.'
            );
        }

        Cache::put($key, now()->timestamp, $seconds + 5);
    }

    private function assertWindow(string $key, int $maxAttempts, int $ttlSeconds, int $retryAfter, string $message): void
    {
        Cache::add($key, 0, max(1, $ttlSeconds));
        $attempts = (int) Cache::increment($key);

        if ($attempts > $maxAttempts) {
            throw new AiConnectorRateLimitException(
                retryAfter: max(1, $retryAfter),
                message: $message
            );
        }
    }
}
