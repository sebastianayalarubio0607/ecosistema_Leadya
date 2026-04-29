<?php

namespace App\Http\Services\GoogleAds;

use App\Models\GoogleAdsCredential;
use App\Support\SensitiveValue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAdsApiClient
{
    public function __construct(
        protected GoogleAdsAuthService $authService,
    ) {
    }

    public function searchStream(GoogleAdsCredential $credential, string $googleAdsCustomerId, string $query): array
    {
        $credential = $this->authService->ensureValidAccessToken($credential);

        if (! $credential?->access_token) {
            throw new \RuntimeException('No fue posible obtener un access token válido para Google Ads.');
        }

        $endpointCustomerId = $this->normalizeCustomerId($googleAdsCustomerId);
        $url = "https://googleads.googleapis.com/v24/customers/{$endpointCustomerId}/googleAds:searchStream";

        Log::info('Google Ads searchStream request started.', [
            'credential_id' => $credential->id,
            'google_ads_customer_id' => SensitiveValue::redact($endpointCustomerId),
            'mcc_id_masked' => SensitiveValue::redact($this->normalizeCustomerId((string) $credential->mcc_id)),
            'login_customer_id' => SensitiveValue::redact($this->normalizeCustomerId((string) $credential->mcc_id)),
            'query_excerpt' => mb_substr($query, 0, 250),
        ]);

        $response = Http::timeout(120)
            ->withHeaders([
                'Authorization' => 'Bearer '.$credential->access_token,
                'developer-token' => (string) $credential->mcc_developer_token,
                'login-customer-id' => $this->normalizeCustomerId((string) $credential->mcc_id),
                'Content-Type' => 'application/json',
            ])
            ->post($url, [
                'query' => $query,
            ]);

        $requestId = $response->header('request-id') ?: $response->header('x-request-id');

        if (! $response->successful()) {
            Log::error('Google Ads API request failed.', [
                'credential_id' => $credential->id,
                'google_ads_customer_id' => SensitiveValue::redact($endpointCustomerId),
                'request_id' => $requestId,
                'status' => $response->status(),
                'response_excerpt' => mb_substr($response->body(), 0, 1000),
            ]);

            throw new \RuntimeException('Google Ads API respondió con error controlado.');
        }

        $payload = $response->json();
        $chunks = is_array($payload) ? $payload : [];
        $results = [];

        foreach ($chunks as $chunk) {
            foreach (($chunk['results'] ?? []) as $row) {
                $results[] = $row;
            }
        }

        Log::info('Google Ads searchStream request finished.', [
            'credential_id' => $credential->id,
            'google_ads_customer_id' => SensitiveValue::redact($endpointCustomerId),
            'request_id' => $requestId,
            'chunks_count' => count($chunks),
            'results_count' => count($results),
        ]);

        return [
            'request_id' => $requestId,
            'results' => $results,
            'raw' => $chunks,
        ];
    }

    public function normalizeCustomerId(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }
}
