<?php

namespace App\Http\Services\Convention;

use App\Http\Services\GoogleAds\GoogleAdsApiClient;
use App\Http\Services\GoogleAds\GoogleAdsAuthService;
use App\Models\CrmState;
use App\Models\Customer;
use App\Models\Lead;
use App\Support\SensitiveValue;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsConversionsService
{
    private const API_VERSION = 'v24';
    private const DEFAULT_VALUE = 0.5;
    private const DEFAULT_CURRENCY = 'COP';
    private const TIMEZONE = 'America/Bogota';

    public function __construct(
        protected GoogleAdsAuthService $authService,
        protected GoogleAdsApiClient $apiClient,
    ) {
    }

    public function listConversionActions(Customer $customer): array
    {
        $credential = $this->authService->ensureValidAccessToken();
        $googleAdsCustomerId = $this->normalizeCustomerId((string) $customer->id_Gads);

        if (! $credential) {
            return [
                'success' => false,
                'status_code' => null,
                'actions' => [],
                'error_message' => 'No hay credenciales activas de Google Ads.',
            ];
        }

        if ($googleAdsCustomerId === '') {
            return [
                'success' => false,
                'status_code' => null,
                'actions' => [],
                'error_message' => 'El customer no tiene id_Gads configurado.',
            ];
        }

        $query = "SELECT conversion_action.id, conversion_action.name, conversion_action.resource_name, conversion_action.type, conversion_action.category, conversion_action.status FROM conversion_action WHERE conversion_action.type = 'UPLOAD_CLICKS' AND conversion_action.status = 'ENABLED' ORDER BY conversion_action.name";
        $url = "https://googleads.googleapis.com/".self::API_VERSION."/customers/{$googleAdsCustomerId}/googleAds:search";

        try {
            $response = Http::timeout(60)
                ->withHeaders($this->headers($credential))
                ->post($url, ['query' => $query]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'status_code' => $response->status(),
                    'actions' => [],
                    'error_message' => 'Google Ads no devolvio acciones de conversion validas.',
                ];
            }

            $actions = collect($response->json('results', []))
                ->map(fn (array $row) => data_get($row, 'conversionAction', []))
                ->filter(fn (array $action) => $this->isUploadClickEnabled($action))
                ->map(fn (array $action) => [
                    'id' => (string) data_get($action, 'id', ''),
                    'name' => (string) data_get($action, 'name', ''),
                    'resource_name' => (string) data_get($action, 'resourceName', data_get($action, 'resource_name', '')),
                    'type' => (string) data_get($action, 'type', ''),
                    'category' => (string) data_get($action, 'category', ''),
                    'status' => (string) data_get($action, 'status', ''),
                ])
                ->filter(fn (array $action) => $action['id'] !== '' && $action['resource_name'] !== '')
                ->values()
                ->all();

            return [
                'success' => true,
                'status_code' => $response->status(),
                'actions' => $actions,
                'error_message' => null,
            ];
        } catch (\Throwable $exception) {
            Log::error('Google Ads conversion actions lookup failed.', [
                'customer_id' => $customer->id,
                'google_ads_customer_id' => SensitiveValue::redact($googleAdsCustomerId),
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => null,
                'actions' => [],
                'error_message' => $exception->getMessage(),
            ];
        }
    }

    public function sendLeadConversion(Lead $lead, Customer $customer, CrmState $crmState): array
    {
        $credential = $this->authService->ensureValidAccessToken();
        $googleAdsCustomerId = $this->normalizeCustomerId((string) $customer->id_Gads);
        $orderId = $this->buildOrderId($lead, $crmState);

        if (! $credential) {
            return $this->skipped('No hay credenciales activas de Google Ads.', $orderId);
        }

        if ($googleAdsCustomerId === '') {
            return $this->skipped('El customer no tiene id_Gads configurado.', $orderId);
        }

        if (! $crmState->google_ads_conversion_enabled) {
            return $this->skipped('El CrmState no tiene habilitado el envio a Google Ads.', $orderId);
        }

        [$identifierType, $identifierValue] = $this->clickIdentifier($lead);

        if (! $identifierType || ! $identifierValue) {
            return $this->skipped('El lead no tiene gclid, gbraid o wbraid.', $orderId);
        }

        $conversionAction = $this->resolveConversionAction($crmState, $googleAdsCustomerId);

        if (! $conversionAction) {
            return $this->skipped('El CrmState no tiene conversion action configurada.', $orderId, [
                'click_identifier_type' => $identifierType,
                'click_identifier_value' => $identifierValue,
            ]);
        }

        $allowed = $this->findAllowedConversionAction($customer, $conversionAction);

        if (! $allowed) {
            return $this->skipped('La conversion action configurada no es UPLOAD_CLICKS habilitada.', $orderId, [
                'conversion_action' => $conversionAction,
                'click_identifier_type' => $identifierType,
                'click_identifier_value' => $identifierValue,
            ]);
        }

        $payload = $this->buildPayload($lead, $crmState, $conversionAction, $identifierType, $identifierValue, $orderId);
        $url = "https://googleads.googleapis.com/".self::API_VERSION."/customers/{$googleAdsCustomerId}:uploadClickConversions";

        try {
            $response = Http::timeout(60)
                ->withHeaders($this->headers($credential))
                ->post($url, $payload);

            $responseBody = $response->json() ?: $response->body();
            $partialFailure = (bool) data_get($responseBody, 'partialFailureError');
            $success = $response->successful() && ! $partialFailure;

            return [
                'success' => $success,
                'status_code' => $response->status(),
                'payload' => $payload,
                'response' => $responseBody,
                'error_message' => $success ? null : $this->extractErrorMessage($responseBody, $response->body()),
                'partial_failure' => $partialFailure,
                'conversion_action' => $conversionAction,
                'order_id' => $orderId,
                'click_identifier_type' => $identifierType,
                'click_identifier_value' => $identifierValue,
            ];
        } catch (\Throwable $exception) {
            Log::error('Google Ads click conversion upload failed.', [
                'lead_id' => $lead->id,
                'customer_id' => $customer->id,
                'google_ads_customer_id' => SensitiveValue::redact($googleAdsCustomerId),
                'order_id' => $orderId,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => null,
                'payload' => $payload,
                'response' => null,
                'error_message' => $exception->getMessage(),
                'partial_failure' => false,
                'conversion_action' => $conversionAction,
                'order_id' => $orderId,
                'click_identifier_type' => $identifierType,
                'click_identifier_value' => $identifierValue,
            ];
        }
    }

    public function buildOrderId(Lead $lead, CrmState $crmState): string
    {
        $status = Str::of($crmState->name ?: $crmState->id)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        return 'lead_'.$lead->id.'_'.($status ?: 'estado');
    }

    public function clickIdentifier(Lead $lead): array
    {
        foreach ([
            'gclid' => ['g_clid', 'gclid'],
            'gbraid' => ['gbraid', 'g_braid'],
            'wbraid' => ['wbraid', 'w_braid'],
        ] as $type => $keys) {
            foreach ($keys as $key) {
                $value = $this->leadValue($lead, $key);

                if ($value !== null && trim((string) $value) !== '') {
                    return [$type, trim((string) $value)];
                }
            }
        }

        return [null, null];
    }

    public function normalizeCustomerId(?string $value): string
    {
        return $this->apiClient->normalizeCustomerId($value);
    }

    protected function headers($credential): array
    {
        return array_filter([
            'Authorization' => 'Bearer '.$credential->access_token,
            'developer-token' => (string) $credential->mcc_developer_token,
            'login-customer-id' => $this->normalizeCustomerId((string) $credential->mcc_id),
            'Content-Type' => 'application/json',
        ], fn ($value) => $value !== '');
    }

    protected function buildPayload(
        Lead $lead,
        CrmState $crmState,
        string $conversionAction,
        string $identifierType,
        string $identifierValue,
        string $orderId
    ): array {
        $conversion = [
            $identifierType => $identifierValue,
            'conversionAction' => $conversionAction,
            'conversionDateTime' => $this->conversionDateTime($lead),
            'conversionValue' => (float) ($crmState->google_ads_conversion_value ?? self::DEFAULT_VALUE),
            'currencyCode' => $crmState->google_ads_conversion_currency ?: self::DEFAULT_CURRENCY,
            'orderId' => $orderId,
            'consent' => [
                'adUserData' => 'GRANTED',
                'adPersonalization' => 'GRANTED',
            ],
        ];

        $userIdentifiers = $this->userIdentifiers($lead);

        if ($userIdentifiers !== []) {
            $conversion['userIdentifiers'] = $userIdentifiers;
        }

        return [
            'conversions' => [$conversion],
            'partialFailure' => true,
        ];
    }

    protected function conversionDateTime(Lead $lead): string
    {
        $date = $lead->updated_at ?: $lead->created_at ?: now();

        if ($date instanceof CarbonInterface) {
            return $date->copy()->timezone(self::TIMEZONE)->format('Y-m-d H:i:sP');
        }

        return now(self::TIMEZONE)->format('Y-m-d H:i:sP');
    }

    protected function userIdentifiers(Lead $lead): array
    {
        $items = [];

        $email = trim(Str::lower((string) ($lead->email ?? '')));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $items[] = [
                'hashedEmail' => hash('sha256', $email),
                'userIdentifierSource' => 'FIRST_PARTY',
            ];
        }

        $phone = $this->normalizeInternationalPhone($lead->phone ?? null);

        if ($phone) {
            $items[] = [
                'hashedPhoneNumber' => hash('sha256', $phone),
                'userIdentifierSource' => 'FIRST_PARTY',
            ];
        }

        return $items;
    }

    protected function normalizeInternationalPhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '' || ! str_starts_with($phone, '+')) {
            return null;
        }

        $normalized = '+'.preg_replace('/\D+/', '', $phone);

        return strlen($normalized) >= 9 ? $normalized : null;
    }

    protected function resolveConversionAction(CrmState $crmState, string $googleAdsCustomerId): ?string
    {
        $resource = trim((string) $crmState->google_ads_conversion_action_resource_name);
        $id = trim((string) $crmState->google_ads_conversion_action_id);

        if ($id === '' && $resource !== '') {
            $id = Str::afterLast($resource, '/');
        }

        if ($id === '') {
            return null;
        }

        return "customers/{$googleAdsCustomerId}/conversionActions/{$id}";
    }

    protected function findAllowedConversionAction(Customer $customer, string $conversionAction): ?array
    {
        $actionId = Str::afterLast($conversionAction, '/');
        $actions = $this->listConversionActions($customer);

        if (! $actions['success']) {
            return null;
        }

        return collect($actions['actions'])
            ->first(fn (array $action) => (string) $action['id'] === (string) $actionId
                || (string) $action['resource_name'] === (string) $conversionAction);
    }

    protected function isUploadClickEnabled(array $action): bool
    {
        return (string) data_get($action, 'type') === 'UPLOAD_CLICKS'
            && (string) data_get($action, 'status') === 'ENABLED';
    }

    protected function leadValue(Lead $lead, string $key): mixed
    {
        if (array_key_exists($key, $lead->getAttributes())) {
            return $lead->{$key};
        }

        return data_get($lead->fields_custom ?? [], $key)
            ?? data_get($lead->meta_payload ?? [], $key);
    }

    protected function extractErrorMessage(mixed $responseBody, string $rawBody): ?string
    {
        if (is_array($responseBody)) {
            return data_get($responseBody, 'partialFailureError.message')
                ?: data_get($responseBody, 'error.message')
                ?: json_encode($responseBody);
        }

        return $rawBody ?: null;
    }

    protected function skipped(string $message, ?string $orderId = null, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'status_code' => null,
            'payload' => null,
            'response' => null,
            'error_message' => $message,
            'partial_failure' => false,
            'conversion_action' => null,
            'order_id' => $orderId,
            'click_identifier_type' => null,
            'click_identifier_value' => null,
            'skipped' => true,
        ], $extra);
    }
}
