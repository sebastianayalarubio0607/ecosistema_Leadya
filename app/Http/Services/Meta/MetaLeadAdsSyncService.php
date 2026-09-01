<?php

namespace App\Http\Services\Meta;

use App\Http\Services\Integration\IntegrationService;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Http\Services\Lead\LeadService;
use App\Jobs\ProcessLeadIntegrationsJob;
use App\Jobs\SendLeadToFacebook;
use App\Models\Lead;
use App\Models\MetaAccessToken;
use App\Models\MetaForm;
use App\Models\MetaFormFieldMapping;
use App\Models\MetaPage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Servicio principal para sincronizar páginas, formularios y leads de Meta Lead Ads.
 * Este servicio se encarga de:
 * - Intercambiar tokens de acceso de corta duración por tokens de larga duración.
 * - Sincronizar las páginas de Meta asociadas a los tokens de acceso.
 * - Sincronizar los formularios de generación de leads (Lead Ads) para las páginas activas.
 * - Sincronizar los leads generados a partir de los formularios, creando o actualizando registros de leads en la base de datos.
 * - Manejar errores y registrar información relevante para depuración y monitoreo.
 * - Proporcionar métodos auxiliares para normalizar datos y resolver mapeos de campos entre los formularios de Meta y los campos de leads en el sistema.
 */
class MetaLeadAdsSyncService
{
    private const DEFAULT_CAMPAIGN_ORIGIN = 'meta';

    private const DEFAULT_PLATFORM = 'Formulario instantáneo Meta';

    private const META_IDENTIFIER_FIELDS = ['id', 'ad_id', 'adset_id', 'campaign_id', 'form_id', 'page_id'];

    private const PHONE_LEAD_FIELDS = ['phone', 'number_whatsApp_companies'];

    private const META_IDENTIFIER_LEAD_FIELDS = ['meta_id_ad'];

    /**
     * MetaLeadAdsSyncService constructor.
     */
    public function __construct(
        private readonly MetaGraphService $graphService,
        private readonly IntegrationService $integrationService,
        private readonly LeadFunnelHistoryService $leadFunnelHistoryService,
        private readonly LeadService $leadService,
    ) {}

    /**
     * Intercambia un token de acceso de corta duración por un token de larga duración utilizando la API de Graph de Meta.
     * Requiere el ID de la aplicación y el secreto de la aplicación para realizar el intercambio.
     * Retorna un array con los detalles del token de larga duración, incluyendo 'access_token', 'token_type', 'expires_in', etc.
     * Lanza una excepción si las credenciales de Meta no están configuradas o si la API devuelve un error.
     *
     * @throws \RuntimeException
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function exchangeLongLivedToken(string $shortLivedToken, ?string $metaAppId = null, ?string $metaAppSecret = null): array
    {
        [$appId, $appSecret] = $this->resolveMetaCredentials($metaAppId, $metaAppSecret);

        $this->ensureMetaCredentialsConfigured($appId, $appSecret);

        return $this->graphService->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);
    }

    /**
     * Llena un modelo de MetaAccessToken con los detalles del token de larga duración obtenido a partir de un token de corta duración.
     * Si el tipo de token no es un token de acceso de usuario, simplemente copia el token corto al largo sin realizar el intercambio.
     * Para tokens de acceso de usuario, realiza el intercambio utilizando la función exchangeLongLivedToken y llena los campos del modelo con la información obtenida.
     * Retorna el modelo de MetaAccessToken actualizado pero no guardado en la base de datos.
     * Lanza una excepción si hay problemas durante el intercambio del token, que debe ser manejada por el llamador.
     *
     * @throws \RuntimeException
     * @throws \Illuminate\Http\Client\RequestException
     */
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

    /**
     * Refresca un token de acceso de Meta si está próximo a expirar, actualizando su información con un token de larga duración.
     * Si el token no es del tipo de token de acceso de usuario, simplemente actualiza la marca de tiempo de actualización sin realizar el intercambio.
     * Para tokens de acceso de usuario, intenta obtener un nuevo token de larga duración utilizando el token corto actual y actualiza el modelo con la nueva información.
     * Guarda los cambios en la base de datos y retorna el modelo actualizado.
     * Si ocurre un error durante el proceso, registra el error en el modelo y en los logs, y lanza la excepción para que sea manejada por el llamador.
     *
     * @throws \RuntimeException
     * @throws \Illuminate\Http\Client\RequestException
     */
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

    /**
     * Busca todos los tokens de acceso activos que estén próximos a expirar y los refresca.
     * Si se proporciona un token específico, solo se intentará refrescar ese token.
     * Retorna un array con el conteo de tokens verificados y tokens refrescados.
     * Lanza excepciones si hay problemas durante el proceso de refresco, que deben ser manejadas por el llamador.
     *
     * @throws \RuntimeException
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function refreshDueTokens(?MetaAccessToken $onlyToken = null): array
    {
        $query = MetaAccessToken::query()
            ->select(MetaAccessToken::SYNC_COLUMNS)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('purpose')
                    ->orWhere('purpose', MetaAccessToken::PURPOSE_GENERAL);
            })
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

    /**
     * Sync pages for all active user access tokens or a specific token if provided.
     * Returns an array with counts of processed tokens, created pages, and updated pages.
     * Throws exceptions if there are issues with syncing, which should be handled by the caller.
     */
    public function syncPages(?MetaAccessToken $onlyToken = null): array
    {
        $tokens = $onlyToken
            ? MetaAccessToken::query()
                ->select(MetaAccessToken::SYNC_COLUMNS)
                ->where(function ($query) {
                    $query->whereNull('purpose')
                        ->orWhere('purpose', MetaAccessToken::PURPOSE_GENERAL);
                })
                ->whereKey($onlyToken->id)
                ->get()
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
                    $metaPageId = $this->normalizeMetaIdentifier($pageData['id'] ?? null);

                    if (blank($metaPageId)) {
                        continue;
                    }

                    $page = $this->findPageByMetaId($pageData['id'] ?? null, $metaPageId);

                    if ($page) {
                        $page->fill([
                            'name' => $pageData['name'] ?? $page->name,
                            'meta_page_id' => $metaPageId,
                            'page_access_token' => $pageData['access_token'] ?? $page->page_access_token,
                            'last_synced_at' => now(),
                            'last_token_refresh_at' => now(),
                            'last_error' => null,
                        ])->save();
                        $updated++;
                    } else {
                        MetaPage::create([
                            'customer_id' => null,
                            'meta_page_id' => $metaPageId,
                            'name' => $pageData['name'] ?? $metaPageId,
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

                $metaPageId = $this->normalizeMetaIdentifier($page->meta_page_id);

                if (blank($metaPageId)) {
                    throw new \RuntimeException('La página no tiene meta_page_id utilizable para consultar formularios.');
                }

                if ((string) $page->meta_page_id !== $metaPageId) {
                    $page->forceFill(['meta_page_id' => $metaPageId])->save();
                }

                $forms = $this->graphService->paginatedGet($metaPageId.'/leadgen_forms', [
                    'fields' => 'id,name,status,locale,questions',
                    'access_token' => $page->page_access_token,
                    'limit' => 9999,
                ]);

                foreach ($forms as $formData) {
                    $metaFormId = $this->normalizeMetaIdentifier($formData['id'] ?? null);

                    if (blank($metaFormId)) {
                        continue;
                    }

                    $form = $this->findFormByMetaId($formData['id'] ?? null, $metaFormId);
                    $payload = [
                        'meta_page_id' => $page->id,
                        'meta_form_id' => $metaFormId,
                        'name' => $formData['name'] ?? $metaFormId,
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

    /**
     * Sincroniza los leads de los formularios de Meta Lead Ads, creando o actualizando registros de leads en la base de datos.
     * Si se proporciona un formulario específico, solo se sincronizarán los leads de ese formulario.
     * Si se proporcionan fechas de inicio y fin, solo se sincronizarán los leads creados dentro de ese rango.
     * Retorna un array con el conteo de formularios procesados, leads creados, leads actualizados, y las fechas del rango utilizado para la sincronización.
     * Lanza excepciones si hay problemas durante la sincronización, que deben ser manejadas por el llamador.
     */
    public function syncLeads(?MetaForm $onlyForm = null, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return $this->syncLeadsForForms($from, $to, function ($query) use ($onlyForm): void {
            $query->when($onlyForm, fn ($innerQuery) => $innerQuery->whereKey($onlyForm->id));
        });
    }

    public function syncLeadsForPage(MetaPage $page, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return $this->syncLeadsForForms($from, $to, function ($query) use ($page): void {
            $query->where('meta_page_id', $page->id);
        });
    }

    private function syncLeadsForForms(?Carbon $from = null, ?Carbon $to = null, ?callable $scope = null): array
    {
        /** @var \Carbon\Carbon $fromDate */
        ['from' => $fromDate, 'to' => $toDate] = $this->resolveWindow($from, $to);
        //       $fromDate = $fromDate->copy()->subDays(30);
        $formsQuery = MetaForm::query()
            ->with([
                'page',
                'fieldMappings' => fn ($query) => $query->where('is_active', true)->orderBy('id'),
            ])
            ->where('status', true)
            ->whereHas('page', fn ($query) => $query->where('status', true)->whereNotNull('customer_id'))
            ->whereHas('fieldMappings', fn ($query) => $query->where('is_active', true))
            ->orderBy('id');

        if ($scope) {
            $scope($formsQuery);
        }

        $forms = $formsQuery->get();

        $created = 0;
        $updated = 0;

        // Procesar cada formulario y sus leads
        foreach ($forms as $form) {
            try {
                if (blank($form->page?->page_access_token)) {
                    throw new \RuntimeException('La página asociada no tiene page_access_token para consultar leads.');
                }

                $metaFormId = $this->normalizeMetaIdentifier($form->meta_form_id);

                if (blank($metaFormId)) {
                    throw new \RuntimeException('El formulario no tiene meta_form_id utilizable para consultar leads.');
                }

                if ((string) $form->meta_form_id !== $metaFormId) {
                    $form->forceFill(['meta_form_id' => $metaFormId])->save();
                }

                // Log::info('Syncing leads for form', ['meta_form_id' => $form->id, 'from' => $fromDate->toDateTimeString(), 'to' => $toDate->toDateTimeString()]);
                $leads = $this->graphService->paginatedGet($metaFormId.'/leads', [
                    'fields' => 'id,created_time,ad_id,form_id,field_data,campaign_id',
                    'access_token' => $form->page->page_access_token,
                    'from_date' => $fromDate->toDateTimeString(),
                    'to_date' => $toDate->toDateTimeString(),
                    'limit' => 500,
                ]);
                // Log::info('Leads fetched from Meta', ['meta_form_id' => $form->id, 'leads_count' => count($leads)]);
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

    /**
     * Get available fields from a Meta form's questions to be used in field mappings.
     * Returns an array of available fields with 'name' and 'label' keys.
     * Throws exceptions if the form's raw payload is missing or malformed.
     */
    public function availableMetaFields(MetaForm $form): array
    {
        $questions = collect(data_get($form->raw_payload, 'questions', []));

        return $questions
            ->map(function ($question): array {
                $name = $this->normalizeMetaFieldName($question['key'] ?? $question['name'] ?? null);

                return [
                    'name' => $name,
                    'label' => $question['label'] ?? $name,
                ];
            })
            ->filter(fn ($question) => filled($question['name']))
            ->values()
            ->all();
    }

    /**
     * Upsert a lead from Meta data.
     */
    private function upsertLeadFromMeta(MetaForm $form, array $leadData): array
    {
        $leadData = $this->normalizeMetaLeadData($leadData);
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

        if (blank($leadData['id'] ?? null)) {
            Log::warning('Meta lead skipped because it does not have a usable id', [
                'meta_form_id' => $form->id,
                'meta_payload' => $leadData,
            ]);

            return ['created' => 0, 'updated' => 0];
        }

        $payload = [
            'customer_id' => $form->page->customer_id,
            'meta_page_id' => $form->page->id,
            'meta_form_id' => $form->id,
            'meta_lead_id' => $leadData['id'],
            'meta_created_time' => filled($leadData['created_time'] ?? null) ? Carbon::parse($leadData['created_time']) : null,
            'meta_payload' => $leadData,
            'meta_id_ad' => isset($leadData['ad_id']) ? (string) $leadData['ad_id'] : null,
            'page' => $form->page->name,
            'campaign_origin' => self::DEFAULT_CAMPAIGN_ORIGIN,
            'plataforma' => self::DEFAULT_PLATFORM,
            'fields_custom' => $this->buildUnmappedFieldsPayload($normalizedFields, $mappings),
        ];

        foreach ($mappings as $mapping) {
            $mappedValue = $this->resolveMappingValue($mapping, $normalizedFields);

            if (blank($mappedValue)) {
                continue;
            }

            $payload[$mapping->lead_field_name] = $mappedValue;
        }

        $existingLead = Lead::query()
            ->where('meta_lead_id', $leadData['id'])
            ->first();

        if ($existingLead) {
            Log::info('Meta lead skipped because it already exists', [
                'meta_form_id' => $form->id,
                'meta_lead_id' => $leadData['id'] ?? null,
                'lead_id' => $existingLead->id,
            ]);

            return ['created' => 0, 'updated' => 0];
        }

        $lead = $this->leadService->createLead($payload);
        $lead->loadMissing('crmState');

        if (blank($lead->crm_state)) {
            SendLeadToFacebook::dispatch($lead->id, (int) $lead->customer_id, 'Lead');
        } elseif (! empty($lead->crmState?->meta_event_id)) {
            SendLeadToFacebook::dispatch($lead->id, (int) $lead->customer_id);
        }

        $this->leadFunnelHistoryService->recordInitialLead($lead);
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
            $normalizedName = $this->normalizeMetaFieldName($name);

            if (filled($normalizedName)) {
                $normalized[$normalizedName] = $this->normalizeFieldValue($values);
            }
        }

        return $normalized;
    }

    private function normalizeFieldValue(mixed $values): mixed
    {
        if (! is_array($values)) {
            return $this->stripMetaPrefix($values);
        }

        if (count($values) === 1) {
            $value = $values[0];

            if (is_scalar($value) || $value === null) {
                return $this->stripMetaPrefix($value);
            }

            return json_encode($value);
        }

        return collect($values)
            ->map(function ($value) {
                if (is_scalar($value) || $value === null) {
                    return $this->stripMetaPrefix($value);
                }

                return json_encode($value);
            })
            ->filter(fn ($value) => ! is_null($value))
            ->implode(', ');
    }

    private function normalizeMetaLeadData(array $leadData): array
    {
        foreach (self::META_IDENTIFIER_FIELDS as $field) {
            if (array_key_exists($field, $leadData)) {
                $leadData[$field] = $this->normalizeMetaIdentifier($leadData[$field]);
            }
        }

        return $leadData;
    }

    private function buildUnmappedFieldsPayload(array $normalizedFields, Collection $mappings): array
    {
        $mappedNames = $mappings
            ->pluck('meta_field_name')
            ->map(fn ($fieldName) => $this->normalizeMetaFieldName($fieldName))
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
        $metaFieldName = $this->normalizeMetaFieldName($mapping->meta_field_name);

        if (filled($metaFieldName) && array_key_exists($metaFieldName, $normalizedFields) && filled($normalizedFields[$metaFieldName])) {
            return $this->normalizeMappedValue($normalizedFields[$metaFieldName], $mapping->lead_field_name);
        }

        if (filled($mapping->static_value)) {
            return $this->normalizeMappedValue($mapping->static_value, $mapping->lead_field_name);
        }

        return null;
    }

    private function normalizeMappedValue(mixed $value, string $leadFieldName): mixed
    {
        if (in_array($leadFieldName, self::META_IDENTIFIER_LEAD_FIELDS, true)) {
            return $this->normalizeMetaIdentifier($value);
        }

        if (in_array($leadFieldName, self::PHONE_LEAD_FIELDS, true)) {
            return $this->normalizePhoneValue($value);
        }

        return $value;
    }

    private function normalizeMetaFieldName(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = $this->stripMetaPrefix(trim((string) $value));

        return filled($value) ? $value : null;
    }

    private function normalizeMetaIdentifier(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = $this->stripMetaPrefix(trim((string) $value));
        $value = preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? $value;

        return filled($value) ? $value : null;
    }

    private function normalizePhoneValue(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        $value = $this->stripMetaPrefix(trim((string) $value));
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? $digits : $value;
    }

    private function findPageByMetaId(mixed $rawValue, string $normalizedValue): ?MetaPage
    {
        return $this->findModelByMetaId(MetaPage::query(), 'meta_page_id', $rawValue, $normalizedValue);
    }

    private function findFormByMetaId(mixed $rawValue, string $normalizedValue): ?MetaForm
    {
        return $this->findModelByMetaId(MetaForm::query(), 'meta_form_id', $rawValue, $normalizedValue);
    }

    private function findModelByMetaId($query, string $column, mixed $rawValue, string $normalizedValue)
    {
        $model = (clone $query)->where($column, $normalizedValue)->first();

        if ($model || ! is_scalar($rawValue)) {
            return $model;
        }

        $rawValue = trim((string) $rawValue);

        if ($rawValue === '' || $rawValue === $normalizedValue) {
            return null;
        }

        return (clone $query)->where($column, $rawValue)->first();
    }

    private function stripMetaPrefix(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $value = trim($value);

        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $value) === 1) {
            return $value;
        }

        if (preg_match('/^[A-Za-z]+:\s*(.*)$/', $value, $matches) === 1) {
            return trim($matches[1]);
        }

        return $value;
    }

    private function ensureMetaCredentialsConfigured(?string $appId, ?string $appSecret): void
    {
        if (blank($appId) || blank($appSecret)) {
            throw new \RuntimeException('Faltan meta_app_id o meta_app_secret en meta_access_tokens.');
        }
    }

    private function resolveMetaCredentials(?string $appId, ?string $appSecret): array
    {
        if (filled($appId) && filled($appSecret)) {
            return [$appId, $appSecret];
        }

        $token = MetaAccessToken::query()
            ->select(['meta_app_id', 'meta_app_secret'])
            ->where('token_type', MetaAccessToken::TYPE_USER_ACCESS_TOKEN)
            ->where(function ($query) {
                $query->whereNull('purpose')
                    ->orWhere('purpose', MetaAccessToken::PURPOSE_GENERAL);
            })
            ->where('is_active', true)
            ->whereNotNull('meta_app_id')
            ->whereNotNull('meta_app_secret')
            ->latest('id')
            ->first();

        return [
            $appId ?: $token?->meta_app_id,
            $appSecret ?: $token?->meta_app_secret,
        ];
    }
}
