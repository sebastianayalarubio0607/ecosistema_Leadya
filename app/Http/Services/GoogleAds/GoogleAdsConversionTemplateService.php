<?php

namespace App\Http\Services\GoogleAds;

use App\Models\Customer;
use App\Models\GoogleAdsConversionTemplate;
use App\Models\GoogleAdsConversionTemplateHistory;
use App\Models\GoogleAdsCredential;
use App\Support\SensitiveValue;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsConversionTemplateService
{
    private const API_VERSION = 'v24';

    public function __construct(
        protected GoogleAdsAuthService $authService,
        protected GoogleAdsApiClient $apiClient,
    ) {
    }

    public function listCustomerAccounts(bool $recordHistory = true): array
    {
        $credential = $this->authService->ensureValidAccessToken();

        if (! $credential) {
            $this->recordGlobalHistory($recordHistory, [
                'action' => 'accounts_lookup',
                'success' => false,
                'error_message' => 'No hay credenciales activas de Google Ads.',
            ]);

            return $this->failed('No hay credenciales activas de Google Ads.');
        }

        $managerCustomerId = $this->managerCustomerId($credential);

        if ($managerCustomerId === '') {
            $this->recordGlobalHistory($recordHistory, [
                'action' => 'accounts_lookup',
                'success' => false,
                'error_message' => 'La credencial no tiene MCC ID o customer ID configurado.',
            ]);

            return $this->failed('La credencial no tiene MCC ID o customer ID configurado.');
        }

        $query = "SELECT customer_client.client_customer, customer_client.level, customer_client.manager, customer_client.descriptive_name, customer_client.currency_code, customer_client.time_zone, customer_client.id, customer_client.status FROM customer_client WHERE customer_client.level <= 1 AND customer_client.status = 'ENABLED'";

        try {
            $managerIds = [$managerCustomerId];
            $visitedManagers = [];
            $accountsById = [];
            $requestIds = [];

            while ($managerIds !== [] && count($visitedManagers) < 50) {
                $currentManagerId = array_shift($managerIds);

                if (isset($visitedManagers[$currentManagerId])) {
                    continue;
                }

                $visitedManagers[$currentManagerId] = true;
                $result = $this->apiClient->searchStream($credential, $currentManagerId, $query);

                if (! empty($result['request_id'])) {
                    $requestIds[] = $result['request_id'];
                }

                foreach (($result['results'] ?? []) as $row) {
                    $account = $this->mapCustomerClient($row);

                    if ($account['id'] === '') {
                        continue;
                    }

                    $accountsById[$account['id']] = $account;

                    if (($account['manager'] ?? false) && (int) ($account['level'] ?? 0) === 1 && ! isset($visitedManagers[$account['id']])) {
                        $managerIds[] = $account['id'];
                    }
                }
            }

            $accounts = collect(array_values($accountsById))
                ->sortBy(fn (array $account) => Str::lower($account['descriptive_name'].' '.$account['id']))
                ->values()
                ->all();

            $this->recordGlobalHistory($recordHistory, [
                'action' => 'accounts_lookup',
                'payload' => ['query' => $query, 'root_manager_customer_id' => $managerCustomerId],
                'response' => [
                    'accounts_count' => count($accounts),
                    'managers_checked' => count($visitedManagers),
                ],
                'request_id' => $requestIds[0] ?? null,
                'success' => true,
            ]);

            return [
                'success' => true,
                'accounts' => $accounts,
                'request_id' => $requestIds[0] ?? null,
                'error_message' => null,
            ];
        } catch (\Throwable $exception) {
            Log::error('Google Ads customer account lookup failed.', [
                'manager_customer_id' => SensitiveValue::redact($managerCustomerId),
                'message' => $exception->getMessage(),
            ]);

            $this->recordGlobalHistory($recordHistory, [
                'action' => 'accounts_lookup',
                'payload' => ['query' => $query],
                'success' => false,
                'error_message' => $exception->getMessage(),
            ]);

            return $this->failed($exception->getMessage(), ['accounts' => []]);
        }
    }

    public function ensureTemplatesForCustomer(
        Customer $customer,
        string $actorType = 'job',
        ?int $actorId = null,
        ?string $actorName = null
    ): array {
        $actor = [
            'type' => $actorType,
            'id' => $actorId,
            'name' => $actorName,
        ];
        $googleAdsCustomerId = $this->normalizeCustomerId((string) $customer->id_Gads);

        if ($googleAdsCustomerId === '') {
            $this->recordCustomerHistory($customer, null, $actor, [
                'action' => 'template_sync_skipped',
                'success' => false,
                'error_message' => 'El customer no tiene id_Gads configurado.',
            ]);

            return $this->failed('El customer no tiene id_Gads configurado.', ['skipped' => true]);
        }

        $credential = $this->authService->ensureValidAccessToken();

        if (! $credential) {
            $this->recordCustomerHistory($customer, null, $actor, [
                'action' => 'template_sync_skipped',
                'google_ads_customer_id' => $googleAdsCustomerId,
                'success' => false,
                'error_message' => 'No hay credenciales activas de Google Ads.',
            ]);

            return $this->failed('No hay credenciales activas de Google Ads.');
        }

        $templates = GoogleAdsConversionTemplate::query()
            ->estadoLqActivo()
            ->orderBy('name')
            ->get();

        if ($templates->isEmpty()) {
            $this->recordCustomerHistory($customer, null, $actor, [
                'action' => 'template_sync_skipped',
                'google_ads_customer_id' => $googleAdsCustomerId,
                'success' => true,
                'error_message' => 'No hay plantillas activas con estado LQ.',
            ]);

            return [
                'success' => true,
                'skipped' => true,
                'checked' => 0,
                'created' => 0,
                'existing' => 0,
                'error_message' => null,
            ];
        }

        $lookup = $this->listConversionActions($customer, $credential, $actor);

        if (! $lookup['success']) {
            return $this->failed($lookup['error_message'] ?? 'No fue posible consultar conversion actions.');
        }

        $existingByName = collect($lookup['actions'])
            ->keyBy(fn (array $action) => $this->nameKey($action['name'] ?? ''));

        $missing = $templates
            ->filter(fn (GoogleAdsConversionTemplate $template) => ! $existingByName->has($this->nameKey($template->name)))
            ->values();

        $existing = $templates->count() - $missing->count();

        $templates
            ->reject(fn (GoogleAdsConversionTemplate $template) => $missing->contains('id', $template->id))
            ->each(function (GoogleAdsConversionTemplate $template) use ($customer, $actor, $googleAdsCustomerId, $existingByName): void {
                $this->recordCustomerHistory($customer, $template, $actor, [
                    'action' => 'template_found_in_google',
                    'google_ads_customer_id' => $googleAdsCustomerId,
                    'payload' => ['expected' => $template->toGoogleCreatePayload()],
                    'response' => $existingByName->get($this->nameKey($template->name)),
                    'success' => true,
                ]);
            });

        if ($missing->isEmpty()) {
            return [
                'success' => true,
                'checked' => $templates->count(),
                'created' => 0,
                'existing' => $existing,
                'error_message' => null,
            ];
        }

        return $this->createMissingTemplates($customer, $credential, $missing, $actor, $googleAdsCustomerId, $existing);
    }

    public function listConversionActions(Customer $customer, GoogleAdsCredential $credential, array $actor): array
    {
        $googleAdsCustomerId = $this->normalizeCustomerId((string) $customer->id_Gads);
        $query = "SELECT conversion_action.id, conversion_action.name, conversion_action.resource_name, conversion_action.type, conversion_action.category, conversion_action.status FROM conversion_action WHERE conversion_action.type = 'UPLOAD_CLICKS' ORDER BY conversion_action.name";

        try {
            $result = $this->apiClient->searchStream($credential, $googleAdsCustomerId, $query);
            $actions = collect($result['results'] ?? [])
                ->map(fn (array $row) => data_get($row, 'conversionAction', []))
                ->filter(fn (array $action) => trim((string) data_get($action, 'name', '')) !== '')
                ->map(fn (array $action) => [
                    'id' => (string) data_get($action, 'id', ''),
                    'name' => (string) data_get($action, 'name', ''),
                    'resource_name' => (string) data_get($action, 'resourceName', data_get($action, 'resource_name', '')),
                    'type' => (string) data_get($action, 'type', ''),
                    'category' => (string) data_get($action, 'category', ''),
                    'status' => (string) data_get($action, 'status', ''),
                ])
                ->values()
                ->all();

            $this->recordCustomerHistory($customer, null, $actor, [
                'action' => 'conversion_actions_lookup',
                'google_ads_customer_id' => $googleAdsCustomerId,
                'payload' => ['query' => $query],
                'response' => ['actions_count' => count($actions)],
                'request_id' => $result['request_id'] ?? null,
                'success' => true,
            ]);

            return [
                'success' => true,
                'actions' => $actions,
                'request_id' => $result['request_id'] ?? null,
                'error_message' => null,
            ];
        } catch (\Throwable $exception) {
            Log::error('Google Ads conversion template lookup failed.', [
                'customer_id' => $customer->id,
                'google_ads_customer_id' => SensitiveValue::redact($googleAdsCustomerId),
                'message' => $exception->getMessage(),
            ]);

            $this->recordCustomerHistory($customer, null, $actor, [
                'action' => 'conversion_actions_lookup',
                'google_ads_customer_id' => $googleAdsCustomerId,
                'payload' => ['query' => $query],
                'success' => false,
                'error_message' => $exception->getMessage(),
            ]);

            return $this->failed($exception->getMessage(), ['actions' => []]);
        }
    }

    protected function createMissingTemplates(
        Customer $customer,
        GoogleAdsCredential $credential,
        Collection $missing,
        array $actor,
        string $googleAdsCustomerId,
        int $existing
    ): array {
        $operations = $missing
            ->map(fn (GoogleAdsConversionTemplate $template) => [
                'create' => $template->toGoogleCreatePayload(),
            ])
            ->values()
            ->all();

        $payload = [
            'operations' => $operations,
            'partialFailure' => true,
        ];
        $url = 'https://googleads.googleapis.com/'.self::API_VERSION."/customers/{$googleAdsCustomerId}/conversionActions:mutate";

        try {
            $response = Http::timeout(60)
                ->withHeaders($this->headers($credential))
                ->post($url, $payload);

            $requestId = $response->header('request-id') ?: $response->header('x-request-id');
            $responseBody = $this->responseBody($response);
            $partialFailure = (bool) data_get($responseBody, 'partialFailureError');
            $success = $response->successful() && ! $partialFailure;
            $errorMessage = $success ? null : $this->extractErrorMessage($responseBody, $response);

            $missing->each(function (GoogleAdsConversionTemplate $template, int $index) use (
                $customer,
                $actor,
                $googleAdsCustomerId,
                $operations,
                $responseBody,
                $requestId,
                $success,
                $errorMessage
            ): void {
                $this->recordCustomerHistory($customer, $template, $actor, [
                    'action' => 'template_created_in_google',
                    'google_ads_customer_id' => $googleAdsCustomerId,
                    'payload' => $operations[$index] ?? null,
                    'response' => [
                        'result' => data_get($responseBody, "results.{$index}"),
                        'partialFailureError' => data_get($responseBody, 'partialFailureError'),
                    ],
                    'request_id' => $requestId,
                    'success' => $success,
                    'error_message' => $errorMessage,
                ]);
            });

            return [
                'success' => $success,
                'checked' => $existing + $missing->count(),
                'created' => $success ? $missing->count() : 0,
                'existing' => $existing,
                'status_code' => $response->status(),
                'request_id' => $requestId,
                'error_message' => $errorMessage,
            ];
        } catch (\Throwable $exception) {
            Log::error('Google Ads conversion template mutate failed.', [
                'customer_id' => $customer->id,
                'google_ads_customer_id' => SensitiveValue::redact($googleAdsCustomerId),
                'message' => $exception->getMessage(),
            ]);

            $missing->each(function (GoogleAdsConversionTemplate $template) use ($customer, $actor, $googleAdsCustomerId, $payload, $exception): void {
                $this->recordCustomerHistory($customer, $template, $actor, [
                    'action' => 'template_create_failed',
                    'google_ads_customer_id' => $googleAdsCustomerId,
                    'payload' => $payload,
                    'success' => false,
                    'error_message' => $exception->getMessage(),
                ]);
            });

            return $this->failed($exception->getMessage(), [
                'checked' => $existing + $missing->count(),
                'created' => 0,
                'existing' => $existing,
            ]);
        }
    }

    protected function mapCustomerClient(array $row): array
    {
        $client = data_get($row, 'customerClient', []);

        return [
            'id' => $this->normalizeCustomerId((string) data_get($client, 'id', '')),
            'resource_name' => (string) data_get($client, 'clientCustomer', data_get($client, 'client_customer', '')),
            'descriptive_name' => (string) data_get($client, 'descriptiveName', data_get($client, 'descriptive_name', '')),
            'currency_code' => (string) data_get($client, 'currencyCode', data_get($client, 'currency_code', '')),
            'time_zone' => (string) data_get($client, 'timeZone', data_get($client, 'time_zone', '')),
            'level' => (int) data_get($client, 'level', 0),
            'manager' => (bool) data_get($client, 'manager', false),
            'status' => (string) data_get($client, 'status', ''),
        ];
    }

    protected function headers(GoogleAdsCredential $credential): array
    {
        return array_filter([
            'Authorization' => 'Bearer '.$credential->access_token,
            'developer-token' => (string) $credential->mcc_developer_token,
            'login-customer-id' => $this->normalizeCustomerId((string) $credential->mcc_id),
            'Content-Type' => 'application/json',
        ], fn ($value) => $value !== '');
    }

    protected function managerCustomerId(GoogleAdsCredential $credential): string
    {
        return $this->normalizeCustomerId((string) ($credential->mcc_id ?: $credential->customer_id));
    }

    protected function normalizeCustomerId(?string $value): string
    {
        return $this->apiClient->normalizeCustomerId($value);
    }

    protected function nameKey(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->squish()
            ->value();
    }

    protected function responseBody(Response $response): array
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return [
            'raw' => Str::limit($response->body(), 5000, ''),
        ];
    }

    protected function extractErrorMessage(array $responseBody, Response $response): ?string
    {
        return data_get($responseBody, 'partialFailureError.message')
            ?: data_get($responseBody, 'error.message')
            ?: ($response->successful() ? null : Str::limit($response->body(), 1000, ''));
    }

    protected function recordCustomerHistory(
        Customer $customer,
        ?GoogleAdsConversionTemplate $template,
        array $actor,
        array $attributes
    ): void {
        GoogleAdsConversionTemplateHistory::record(array_merge($attributes, [
            'template' => $template,
            'customer_id' => $customer->id,
            'google_ads_customer_id' => $attributes['google_ads_customer_id'] ?? $this->normalizeCustomerId((string) $customer->id_Gads),
            'actor' => $actor,
        ]));
    }

    protected function recordGlobalHistory(bool $recordHistory, array $attributes): void
    {
        if (! $recordHistory) {
            return;
        }

        GoogleAdsConversionTemplateHistory::record($attributes);
    }

    protected function failed(string $message, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'error_message' => $message,
        ], $extra);
    }
}
