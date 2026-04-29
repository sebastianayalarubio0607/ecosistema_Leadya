<?php

namespace App\Http\Services\GoogleAds;

use App\Models\GoogleAdsCredential;
use App\Support\SensitiveValue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAdsAuthService
{
    public function getActiveCredential(): ?GoogleAdsCredential
    {
        $credential = GoogleAdsCredential::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        Log::info('Google Ads active credential lookup completed.', [
            'found' => (bool) $credential,
            'credential_id' => $credential?->id,
            'customer_id_masked' => $credential ? SensitiveValue::redact($credential->customer_id) : null,
            'mcc_id_masked' => $credential ? SensitiveValue::redact($credential->mcc_id) : null,
            'access_token_expires_at' => optional($credential?->access_token_expires_at)?->toDateTimeString(),
        ]);

        return $credential;
    }

    public function ensureValidAccessToken(?GoogleAdsCredential $credential = null): ?GoogleAdsCredential
    {
        $credential ??= $this->getActiveCredential();

        if (! $credential) {
            Log::warning('Google Ads access token validation skipped because there is no active global credential.');
            return null;
        }

        if ($credential->access_token && ! $this->isTokenExpiringSoon($credential)) {
            Log::info('Google Ads access token is still valid.', [
                'credential_id' => $credential->id,
                'expires_at' => optional($credential->access_token_expires_at)?->toDateTimeString(),
            ]);
            return $credential;
        }

        Log::info('Google Ads access token needs refresh.', [
            'credential_id' => $credential->id,
            'has_access_token' => (bool) $credential->access_token,
            'expires_at' => optional($credential->access_token_expires_at)?->toDateTimeString(),
        ]);

        return $this->refreshAccessToken($credential);
    }

    public function isTokenExpiringSoon(GoogleAdsCredential $credential, int $minutes = 10): bool
    {
        if (! $credential->access_token_expires_at) {
            return true;
        }

        return now()->addMinutes($minutes)->greaterThanOrEqualTo($credential->access_token_expires_at);
    }

    public function refreshAccessToken(GoogleAdsCredential $credential): ?GoogleAdsCredential
    {
        if (
            ! $credential->client_id
            || ! $credential->client_secret
            || ! $credential->refresh_token
        ) {
            Log::warning('Google Ads token refresh skipped because required credentials are incomplete.', [
                'credential_id' => $credential->id,
                'customer_id_masked' => SensitiveValue::redact($credential->customer_id),
                'mcc_id_masked' => SensitiveValue::redact($credential->mcc_id),
            ]);

            return null;
        }

        try {
            Log::info('Google Ads token refresh started.', [
                'credential_id' => $credential->id,
                'customer_id_masked' => SensitiveValue::redact($credential->customer_id),
                'mcc_id_masked' => SensitiveValue::redact($credential->mcc_id),
            ]);

            $response = Http::asForm()
                ->timeout(30)
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $credential->client_id,
                    'client_secret' => $credential->client_secret,
                    'refresh_token' => $credential->refresh_token,
                    'grant_type' => 'refresh_token',
                ]);

            if (! $response->successful()) {
                Log::error('Google Ads token refresh failed.', [
                    'credential_id' => $credential->id,
                    'status' => $response->status(),
                    'customer_id_masked' => SensitiveValue::redact($credential->customer_id),
                    'mcc_id_masked' => SensitiveValue::redact($credential->mcc_id),
                    'response_excerpt' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $accessToken = (string) $response->json('access_token', '');
            $expiresIn = (int) $response->json('expires_in', 0);

            if ($accessToken === '' || $expiresIn <= 0) {
                Log::error('Google Ads token refresh returned an incomplete payload.', [
                    'credential_id' => $credential->id,
                    'customer_id_masked' => SensitiveValue::redact($credential->customer_id),
                    'mcc_id_masked' => SensitiveValue::redact($credential->mcc_id),
                ]);

                return null;
            }

            $credential->forceFill([
                'access_token' => $accessToken,
                'access_token_expires_at' => now()->addSeconds($expiresIn),
            ])->save();

            Log::info('Google Ads token refresh completed successfully.', [
                'credential_id' => $credential->id,
                'expires_at' => optional($credential->fresh()->access_token_expires_at)?->toDateTimeString(),
            ]);

            return $credential->fresh();
        } catch (\Throwable $exception) {
            Log::error('Google Ads token refresh threw an exception.', [
                'credential_id' => $credential->id,
                'customer_id_masked' => SensitiveValue::redact($credential->customer_id),
                'mcc_id_masked' => SensitiveValue::redact($credential->mcc_id),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function refreshDueCredentials(?GoogleAdsCredential $credential = null): array
    {
        $stats = [
            'checked' => 0,
            'refreshed' => 0,
            'failed' => 0,
        ];

        $credential ??= $this->getActiveCredential();

        if (! $credential) {
            Log::warning('Google Ads hourly refresh check found no active global credential.');
            return $stats;
        }

        $stats['checked']++;

        if (! $credential->access_token || $this->isTokenExpiringSoon($credential)) {
            $refreshed = $this->refreshAccessToken($credential);
            $stats[$refreshed ? 'refreshed' : 'failed']++;
        }

        Log::info('Google Ads hourly refresh check finished.', [
            'credential_id' => $credential->id,
            'stats' => $stats,
        ]);

        return $stats;
    }
}
