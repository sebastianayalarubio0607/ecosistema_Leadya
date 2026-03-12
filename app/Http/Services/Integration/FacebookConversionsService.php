<?php

namespace App\Http\Services\Convention;

use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FacebookConversionsService
{
    /**
     * Si quieres un default "quemado", déjalo aquí.
     * Si está vacío, no se envía.
     */
    private const DEFAULT_TEST_EVENT_CODE = '';

    private const DEFAULT_CURRENCY = 'COP';

    public function sendLeadEvent(Lead $lead, int $customerId): array
    {
        $customer = Customer::findOrFail($customerId);

        $pixelId     = data_get($customer, 'fb_pixel_id');
        $accessToken = data_get($customer, 'fb_access_token');

        // ✅ test_event_code: intenta desde Lead, luego Customer, luego constante
        $testCode = trim((string) (
            $lead->test_event_code
            ?? data_get($customer, 'fb_test_event_code')
            ?? self::DEFAULT_TEST_EVENT_CODE
        ));
        if ($testCode === '') {
            $testCode = null;
        }

        /**
         * ✅ event_name según crmState/metaEvent/nombre
         * Si crmState es null/vacío/no existe => "Lead"
         * (NO se normaliza)
         */
        $event_name = 'CompleteRegistration';
        $usedFallbackLeadEvent = true;

        if (!empty($lead->crm_state)) {
            $lead->loadMissing('crmState.metaEvent');
            $dbEventName = $lead->crmState?->metaEvent?->nombre;

            if (is_string($dbEventName) && trim($dbEventName) !== '') {
                $event_name = trim($dbEventName);
                $usedFallbackLeadEvent = false;
            }
        }

        if (!$pixelId || !$accessToken) {
            return [
                'ok' => false,
                'error' => 'Faltan credenciales de Facebook (pixel o access token).',
                'request' => null,
                'pixel_id' => $pixelId,
                'test_event_code' => $testCode,
                'event_name' => $event_name,
                'lead_id' => $lead->id,
            ];
        }

        [$userData, $customData] = $this->buildPayload($lead);

        $endpoint = "https://graph.facebook.com/v24.0/{$pixelId}/events?access_token={$accessToken}";

        $event_time = $userData['created_at'];
        $event_id   = "lead_{$lead->id}_{$event_time}";

        $event = [
            'event_name' => $event_name,
            'event_time' => $event_time,
            'event_id' => $event_id,
            'action_source' => 'website',
            'event_source_url' =>  'https://app.leadsya.com/',

            'user_data' => $this->filterNulls([
                'client_ip_address' => $customData['client_ip'] ?? null,
                'client_user_agent' => $customData['agent'] ?? null,

                'fbp' => $userData['fbp'] ?? null,
                'fbc' => $userData['fbc'] ?? null,

                'em' => $userData['em'] ?? null,
                'ph' => $userData['ph'] ?? null,

                'fn' => $userData['fn'] ?? null,
                'ln' => $userData['ln'] ?? null,
                'ct' => $userData['ct'] ?? null,
                'country' => $userData['country'] ?? null,

                'external_id' => !empty($userData['external_id']) ? [$userData['external_id']] : null,
            ]),

            'custom_data' => $this->filterNulls(array_merge(
                [
                    'content_name' => $lead->service ?? null,
                    'lead_source' => $lead->campaign_origin ?? $lead->utm_source ?? null,

                    'lead_id' => $lead->id,
                    'lead_key' => $this->buildLeadKey($lead),

                    'status' => $lead->status ?? null,
                    'page' => $lead->page ?? null,
                    'page_url' => $lead->page_url ?? null,

                    'agent' => $lead->agent ?? null,
                    'client_ip' => $customData['client_ip'] ?? null,

                    'currency' => self::DEFAULT_CURRENCY,
                    'value' => $this->normalizeValue($lead->value),
                ],
                // opcional (si vienen en fields_custom)
                $this->extractStandardEventCustomData($lead)
            )),
        ];

        $payload = [
            'data' => [$event],
        ];

        if (!empty($testCode)) {
            $payload['test_event_code'] = $testCode;
        }



        $response = Http::asJson()
            ->timeout(15)
            ->retry(3, 500)
            ->post($endpoint, $payload);

        if ($response->successful()) {
            return [
                'ok' => true,
                'data' => $response->json(),
                'status' => $response->status(),
                'request' => $payload,
                'pixel_id' => $pixelId,
                'test_event_code' => $testCode,
                'event_name' => $event_name,
                'event_id' => $event_id,
                'used_fallback_lead_event' => $usedFallbackLeadEvent,
            ];
        }

        return [
            'ok' => false,
            'status' => $response->status(),
            'error' => $response->json() ?: $response->body(),
            'request' => $payload,
            'pixel_id' => $pixelId,
            'test_event_code' => $testCode,
            'event_name' => $event_name,
            'event_id' => $event_id,
            'used_fallback_lead_event' => $usedFallbackLeadEvent,
        ];
    }

    /**
     * Construye user_data y custom_data base.
     * ✅ NO normaliza IP: se envía la IPv4 tal cual venga (solo se limpia lista "ip,proxy,proxy")
     */
    protected function buildPayload(Lead $lead): array
    {
        $email   = $lead->email ?? null;
        $phone   = $lead->phone ?? null;
        $fname   = $lead->name ?? null;
        $lname   = $lead->last_name ?? null;
        $city    = $lead->city ?? null;
        $country = $lead->country ?? null;

        $fbp = $lead->fbp ?? null;
        $fbc = $lead->fbc ?? null;

        $created_at = optional($lead->created_at)->copy()->timezone('UTC')->timestamp ?? now()->timestamp;

        $userData = [];
        $userData['created_at'] = $created_at;

        if ($email) {
            $userData['em'] = [$this->sha256($this->normalizeEmail($email))];
        }
        if ($phone) {
            $userData['ph'] = [$this->sha256($this->normalizePhone($phone))];
        }
        if ($fname) {
            $userData['fn'] = $this->sha256($this->normLower($fname));
        }
        if ($lname) {
            $userData['ln'] = $this->sha256($this->normLower($lname));
        }
        if ($city) {
            $userData['ct'] = $this->sha256($this->normLower($city));
        }
        if ($country) {
            $userData['country'] = $this->sha256($this->normLower($country));
        }

        if ($fbp) {
            $userData['fbp'] = $fbp;
        }
        if ($fbc) {
            $userData['fbc'] = $fbc;
        }

        $userData['external_id'] = $this->sha256((string) $lead->id);

        $rawIp = $this->extractFirstIp($lead->remote_ip ?? null);

        $customData = array_filter([
            'agent' => $lead->agent ?? null,
            'client_ip' => $rawIp,
        ], fn ($v) => !is_null($v) && $v !== '');

        return [$userData, $customData];
    }

    /**
     * Si viene "ip, proxy1, proxy2", toma la primera.
     * NO convierte, NO normaliza: solo limpia.
     */
    protected function extractFirstIp(?string $ip): ?string
    {
        $ip = trim((string) $ip);
        if ($ip === '') return null;

        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        // opcional: solo devolver si es IP válida
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $ip;
    }

    protected function buildLeadKey(Lead $lead): string
    {
        $phone = trim((string) ($lead->phone ?? ''));
        $service = trim((string) ($lead->service ?? ''));

        $key = trim($phone . '_' . $service, '_');

        return $key !== '' ? $key : (string) $lead->id;
    }

    protected function normalizeValue($value): float
    {
        if ($value === null || $value === '') return 0.0;

        if (is_string($value)) {
            $value = str_replace([',', ' '], ['', ''], $value);
        }

        return (float) $value;
    }

    /**
     * Opcional: parámetros e-commerce si vienen en fields_custom.
     */
    protected function extractStandardEventCustomData(Lead $lead): array
    {
        $fc = $lead->fields_custom ?? [];
        if (!is_array($fc)) $fc = [];

        $allowed = [
            'content_ids',
            'contents',
            'content_type',
            'content_category',
            'num_items',
            'order_id',
            'predicted_ltv',
            'search_string',
            'delivery_category',
        ];

        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $fc) && $fc[$k] !== null && $fc[$k] !== '') {
                $out[$k] = $fc[$k];
            }
        }

        return $out;
    }

    protected function filterNulls(array $data): array
    {
        return array_filter($data, static fn ($v) => !is_null($v) && $v !== '');
    }

    protected function normLower(?string $v): string
    {
        return trim(Str::lower($v ?? ''));
    }

    protected function normalizeEmail(string $email): string
    {
        return $this->normLower($email);
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        return ltrim($digits, '0');
    }

    protected function sha256(string $value): string
    {
        return hash('sha256', trim($value));
    }
}
