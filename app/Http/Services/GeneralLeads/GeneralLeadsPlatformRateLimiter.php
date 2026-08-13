<?php

namespace App\Http\Services\GeneralLeads;

use Illuminate\Support\Facades\Cache;

class GeneralLeadsPlatformRateLimiter
{
    private const MAX_ATTEMPTS = 10;

    private const WINDOW_SECONDS = 60;

    private const LOCK_SECONDS = 120;

    public function hit(string $platform): array
    {
        $platform = $this->normalizePlatform($platform);
        $blockedUntil = (int) Cache::get($this->blockedKey($platform), 0);

        if ($blockedUntil > now()->timestamp) {
            return [
                'allowed' => false,
                'retry_after' => $blockedUntil - now()->timestamp,
            ];
        }

        $countKey = $this->countKey($platform);
        Cache::add($countKey, 0, self::WINDOW_SECONDS);
        $attempts = (int) Cache::increment($countKey);

        if ($attempts > self::MAX_ATTEMPTS) {
            $blockedUntil = now()->addSeconds(self::LOCK_SECONDS)->timestamp;
            Cache::put($this->blockedKey($platform), $blockedUntil, self::LOCK_SECONDS);

            return [
                'allowed' => false,
                'retry_after' => self::LOCK_SECONDS,
            ];
        }

        return [
            'allowed' => true,
            'retry_after' => 0,
        ];
    }

    private function normalizePlatform(string $platform): string
    {
        return $platform === 'google' ? 'google' : 'meta';
    }

    private function countKey(string $platform): string
    {
        return 'general-leads-platform-rate:'.$platform.':'.now()->format('YmdHi');
    }

    private function blockedKey(string $platform): string
    {
        return 'general-leads-platform-rate:'.$platform.':blocked-until';
    }
}
