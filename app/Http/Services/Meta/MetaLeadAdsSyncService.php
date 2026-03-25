<?php

namespace App\Http\Services\Meta;

use App\Http\Services\Integration\IntegrationService;
use App\Jobs\ProcessLeadIntegrationsJob;
use App\Models\Lead;
use App\Models\MetaAccessToken;
use App\Models\MetaForm;
use App\Models\MetaFormFieldMapping;
use App\Models\MetaPage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class MetaLeadAdsSyncService
{
    public function __construct(
        private readonly MetaGraphService $graphService,
        private readonly IntegrationService $integrationService,
    ) {
    }

    public function exchangeLongLivedToken(string $shortLivedToken, ?string $metaAppId = null, ?string $metaAppSecret = null): array
    {
        $appId = $metaAppId ?: config('services.meta.app_id');
        $appSecret = $metaAppSecret ?: config('services.meta.app_secret');

        $this->ensureMetaCredentialsConfigured($appId, $appSecret);

        return $this->graphService->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);
    }

    public function fillLongLivedToken(MetaAccessToken $accessToken, string $shortLivedToken): MetaAccessToken
    {
        if ($accessToken->token_type !== MetaAccessToken::TYPE_USER_ACCESS_TOKEN) {
            $accessToken->fill([
                'short_lived_token' => $shortLivedToken,
                'long_lived_token' => $shortLivedToken,
                'token_type' => $accessToken->token_type,
                'expires_in' => null,
                'expires_at' => null,
                'refresh_last_run_at' => now(),
                'last_error' => null,
            ]);

            return $accessToken;
        }

        $response = $this->exchangeLongLivedToken(
            shortLivedToken: $shortLivedToken,
            metaAppId: $accessToken->meta_app_id,
            metaAppSecret: $accessToken->meta_app_secret,
        );

        $accessToken->fill([
            'short_lived_token' => $shortLivedToken,
            'long_lived_token' => $response['access_token'] ?? null,
            'token_type' => $accessToken->token_type ?: MetaAccessToken::TYPE_USER_ACCESS_TOKEN,
            'expires_in' => $response['expires_in'] ?? null,
            'expires_at' => isset($response['expires_in']) ? now()->addSeconds((int) $response['expires_in']) : null,
            'refresh_last_run_at' => now(),
            'last_error' => null,
        ]);

        return $accessToken;
    }

    public function refreshToken(MetaAccessToken $accessToken): MetaAccessToken
    {
        try {
            $sourceToken = $accessToken->long_lived_token ?: $accessToken->short_lived_token;
            $this->fillLongLivedToken($accessToken, $sourceToken);
            $accessToken->save();
        } catch (\Throwable $exception) {
            $accessToken->forceFill([
                'refresh_last_run_at' => now(),
                'last_error' => $exception->getMessage(),
            ])->save();

            Log::error('Meta token refresh failed', [
                'meta_access_token_id' => $accessToken->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $accessToken;
    }

    public function refreshDueTokens(?MetaAccessToken $onlyToken = null): array
    {
        $query = MetaAccessToken::query()
            ->where('is_active', true)
            ->whereNotNull('long_lived_token')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(10));

        if ($onlyToken) {
            $query->whereKey($onlyToken->id);
        }

        $tokens = $query->get();
        $refreshed = 0;

        foreach ($tokens as $token) {
            $this->refreshToken($token);
            $refreshed++;
        }

        return ['tokens_checked' => $tokens->count(), 'tokens_refreshed' => $refreshed];
    }

    public function syncPages(?MetaAccessToken $onlyToken = null): array
    {
        $tokens = $onlyToken
            ? MetaAccessToken::query()->whereKey($onlyToken->id)->get()
            : collect(array_filter([MetaAccessToken::activeByType(MetaAccessToken::TYPE_USER_ACCESS_TOKEN)]));

        $created = 0;
        $updated = 0;

        foreach ($tokens as $token) {
            try {
                if (blank($token->working_token)) {
                    throw new \RuntimeException('El token de usuario no tiene un access token utilizable.');
                }

                $pages = $this->graphService->paginatedGet('me/accounts', [
                    'fields' => 'id,name,access_token',
                    'access_token' => $token->working_token,
                    'limit' => 100,
                ]);

                foreach ($pages as $pageData) {
                    $page = MetaPage::query()->firstWhere('meta_page_id', $pageData['id']);

                    if ($page) {
                        $page->fill([
                            'name' => $pageData['name'] ?? $page->name,
                            'page_access_token' => $pageData['access_token'] ?? $page->page_access_token,
                            'last_synced_at' => now(),
                            'last_token_refresh_at' => now(),
                            'last_error' => null,
                        ])->save();
                        $updated++;
                    } else {
                        MetaPage::create([
                            'customer_id' => null,
                            'meta_page_id' => $pageData['id'],
                            'name' => $pageData['name'] ?? $pageData['id'],
                            'page_access_token' => $pageData['access_token'] ?? null,
                            'status' => false,
                            'last_synced_at' => now(),
                            'last_token_refresh_at' => now(),
                            'last_error' => null,
                        ]);
                        $created++;
                    }
                }
            } catch (\Throwable $exception) {
                $token->forceFill([
                    'refresh_last_run_at' => now(),
                    'last_error' => $exception->getMessage(),
                ])->save();

                Log::error('Meta page sync failed', [
                    'meta_access_token_id' => $token->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return ['tokens_processed' => $tokens->count(), 'pages_created' => $created, 'pages_updated' => $updated];
    }

    public function syncForms(?MetaPage $onlyPage = null): array
    {
        $pages = MetaPage::query()
            ->when($onlyPage, fn ($query) => $query->whereKey($onlyPage->id))
            ->where('status', true)
            ->whereNotNull('customer_id')
            ->orderBy('id')
            ->get();

        $created = 0;
        $updated = 0;

        foreach ($pages->values() as $index => $page) {
            try {
                if (blank($page->page_access_token)) {
                    throw new \RuntimeException('La página no tiene page_access_token para consultar formularios.');
                }

                $forms = $this->graphService->paginatedGet($page->meta_page_id.'/leadgen_forms', [
                    'fields' => 'id,name,status,locale,questions',
                    'access_token' => $page->page_access_token,
                    'limit' => 100,
                ]);

                foreach ($forms as $formData) {
                    $form = MetaForm::query()->firstWhere('meta_form_id', $formData['id']);
                    $payload = [
                        'meta_page_id' => $page->id,
                        'meta_form_id' => $formData['id'],
                        'name' => $formData['name'] ?? $formData['id'],
                        'locale' => $formData['locale'] ?? null,
                        'meta_status' => $formData['status'] ?? null,
                        'raw_payload' => $formData,
                        'last_synced_at' => now(),
                        'last_error' => null,
                    ];

                    if ($form) {
                        $form->fill($payload)->save();
                        $updated++;
                    } else {
                        MetaForm::create($payload + ['status' => false]);
                        $created++;
                    }
                }

                $page->forceFill([
                    'last_synced_at' => now(),
                    'last_error' => null,
                ])->save();
            } catch (\Throwable $exception) {
                $page->forceFill([
                    'last_error' => $exception->getMessage(),
                ])->save();

                Log::error('Meta form sync failed', [
                    'meta_page_id' => $page->id,
                    'message' => $exception->getMessage(),
                ]);
            }

            if ($index < ($pages->count() - 1)) {
                sleep(3);
            }
        }

        return ['pages_processed' => $pages->count(), 'forms_created' => $created, 'forms_updated' => $updated];
    }

    public function syncLeads(?MetaForm $onlyForm = null, ?Carbon $from = null, ?Carbon $to = null): array
    {
        ['from' => $fromDate, 'to' => $toDate] = $this->resolveWindow($from, $to);

        $forms = MetaForm::query()
            ->with([
                'page',
                'fieldMappings' => fn ($query) => $query->where('is_active', true)->orderBy('id'),
            ])
            ->when($onlyForm, fn ($query) => $query->whereKey($onlyForm->id))
            ->where('status', true)
            ->whereHas('page', fn ($query) => $query->where('status', true)->whereNotNull('customer_id'))
            ->whereHas('fieldMappings', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->get();

        $created = 0;
        $updated = 0;

        foreach ($forms as $form) {
            try {
                if (blank($form->page?->page_access_token)) {
                    throw new \RuntimeException('La página asociada no tiene page_access_token para consultar leads.');
                }

                $leads = $this->graphService->paginatedGet($form->meta_form_id.'/leads', [
                    'fields' => 'id,created_time,ad_id,form_id,field_data,campaign_id',
                    'access_token' => $form->page->page_access_token,
                    'from_date' => $fromDate->toDateTimeString(),
                    'to_date' => $toDate->toDateTimeString(),
                    'limit' => 500,
                ]);

                foreach ($leads as $leadData) {
                    try {
                        $result = $this->upsertLeadFromMeta($form, $leadData);
                        $created += $result['created'];
                        $updated += $result['updated'];
                    } catch (\Throwable $exception) {
                        Log::error('Meta lead sync skipped for a single lead', [
                            'meta_form_id' => $form->id,
                            'meta_lead_id' => $leadData['id'] ?? null,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }

                $form->forceFill([
                    'last_synced_at' => now(),
                    'last_error' => null,
                ])->save();
            } catch (\Throwable $exception) {
                $form->forceFill([
                    'last_error' => $exception->getMessage(),
                ])->save();

                Log::error('Meta lead sync failed', [
                    'meta_form_id' => $form->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'forms_processed' => $forms->count(),
            'leads_created' => $created,
            'leads_updated' => $updated,
            'from' => $fromDate->toDateTimeString(),
            'to' => $toDate->toDateTimeString(),
        ];
    }

    public function availableMetaFields(MetaForm $form): array
    {
        $questions = collect(data_get($form->raw_payload, 'questions', []));

        return $questions
            ->map(fn ($question) => [
                'name' => $question['key'] ?? $question['name'] ?? null,
                'label' => $question['label'] ?? $question['key'] ?? $question['name'] ?? null,
            ])
            ->filter(fn ($question) => filled($question['name']))
            ->values()
            ->all();
    }

    private function upsertLeadFromMeta(MetaForm $form, array $leadData): array
    {
        $normalizedFields = $this->normalizeFieldData($leadData['field_data'] ?? []);
        $mappings = $form->fieldMappings;

        foreach ($mappings->where('is_required', true) as $mapping) {
            $mappedValue = $this->resolveMappingValue($mapping, $normalizedFields);

            if (blank($mappedValue)) {
                Log::warning('Meta lead skipped because a required mapping is missing', [
                    'meta_form_id' => $form->id,
                    'meta_lead_id' => $leadData['id'] ?? null,
                    'meta_field_name' => $mapping->meta_field_name,
                ]);

                return ['created' => 0, 'updated' => 0];
            }
        }

        $payload = [
            'customer_id' => $form->page->customer_id,
            'meta_page_id' => $form->page->id,
            'meta_form_id' => $form->id,
            'meta_lead_id' => $leadData['id'],
            'meta_created_time' => filled($leadData['created_time'] ?? null) ? Carbon::parse($leadData['created_time']) : null,
            'meta_payload' => $leadData,
            'meta_id_ad' => isset($leadData['ad_id']) ? (string) $leadData['ad_id'] : null,
            'reference' => isset($leadData['campaign_id']) ? (string) $leadData['campaign_id'] : null,
            'page' => $form->page->name,
            'campaign_origin' => (string) data_get($leadData, 'platform', 'meta'),
            'plataforma' => 'Formulario instantáneo Meta',
            'fields_custom' => $this->buildUnmappedFieldsPayload($normalizedFields, $mappings),
        ];

        foreach ($mappings as $mapping) {
            $mappedValue = $this->resolveMappingValue($mapping, $normalizedFields);

            if (blank($mappedValue)) {
                continue;
            }

            $payload[$mapping->lead_field_name] = $mappedValue;
        }

        $existingLead = Lead::query()->firstWhere('meta_lead_id', $leadData['id']);

        if ($existingLead) {
            Log::info('Meta lead skipped because it already exists', [
                'meta_form_id' => $form->id,
                'meta_lead_id' => $leadData['id'] ?? null,
                'lead_id' => $existingLead->id,
            ]);

            return ['created' => 0, 'updated' => 0];
        }

        $lead = Lead::create($payload);
        $this->dispatchLeadIntegrations($lead);

        return ['created' => 1, 'updated' => 0];
    }

    private function dispatchLeadIntegrations(Lead $lead): void
    {
        if (blank($lead->customer_id)) {
            return;
        }

        $integrations = $this->integrationService->getActiveIntegrations($lead->customer_id);

        if ($integrations->isEmpty()) {
            return;
        }

        ProcessLeadIntegrationsJob::dispatch($lead, $integrations);
    }

    private function normalizeFieldData(array $fieldData): array
    {
        $normalized = [];

        foreach ($fieldData as $item) {
            $name = $item['name'] ?? null;

            if (! $name) {
                continue;
            }

            $values = $item['values'] ?? [];
            $normalized[$name] = $this->normalizeFieldValue($values);
        }

        return $normalized;
    }

    private function normalizeFieldValue(mixed $values): mixed
    {
        if (! is_array($values)) {
            return $values;
        }

        if (count($values) === 1) {
            $value = $values[0];

            if (is_scalar($value) || $value === null) {
                return $value;
            }

            return json_encode($value);
        }

        return collect($values)
            ->map(function ($value) {
                if (is_scalar($value) || $value === null) {
                    return $value;
                }

                return json_encode($value);
            })
            ->filter(fn ($value) => ! is_null($value))
            ->implode(', ');
    }

    private function buildUnmappedFieldsPayload(array $normalizedFields, Collection $mappings): array
    {
        $mappedNames = $mappings
            ->pluck('meta_field_name')
            ->filter()
            ->all();

        return collect($normalizedFields)
            ->reject(fn ($value, $key) => in_array($key, $mappedNames, true))
            ->all();
    }

    private function resolveWindow(?Carbon $from, ?Carbon $to): array
    {
        $windowEnd = $to ?: now(config('app.timezone'))->startOfHour();
        $windowStart = $from ?: $windowEnd->copy()->subHour();

        return ['from' => $windowStart, 'to' => $windowEnd];
    }

    private function resolveMappingValue(MetaFormFieldMapping $mapping, array $normalizedFields): mixed
    {
        if (filled($mapping->meta_field_name) && array_key_exists($mapping->meta_field_name, $normalizedFields) && filled($normalizedFields[$mapping->meta_field_name])) {
            return $normalizedFields[$mapping->meta_field_name];
        }

        if (filled($mapping->static_value)) {
            return $mapping->static_value;
        }

        return null;
    }

    private function ensureMetaCredentialsConfigured(?string $appId, ?string $appSecret): void
    {
        if (blank($appId) || blank($appSecret)) {
            throw new \RuntimeException('Faltan META_APP_ID o META_APP_SECRET en la configuración.');
        }
    }
}
