<?php

namespace App\Http\Services\Integration;

use App\Http\Services\Integration\Concerns\ResolvesIntegrationVariableMappings;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GohighlevelService
{
    use ResolvesIntegrationVariableMappings;

    private const DEFAULT_CONTACTS_UPSERT_URL = 'https://services.leadconnectorhq.com/contacts/upsert';

    private const DEFAULT_CONTACTS_DUPLICATE_SEARCH_URL = 'https://services.leadconnectorhq.com/contacts/search/duplicate';

    private const DEFAULT_OPPORTUNITIES_URL = 'https://services.leadconnectorhq.com/opportunities/';

    private const LEAD_TOKEN_PREFIX = '__gohighlevel_lead_field__:';

    private const CONTEXT_TOKEN_PREFIX = '__gohighlevel_context_field__:';

    public function getPipelines(Integration $integration, string $locationId): array
    {
        $response = Http::acceptJson()
            ->withToken($this->resolveToken($integration))
            ->withHeaders(['Version' => 'v3'])
            ->connectTimeout(5)
            ->timeout(15)
            ->get(self::DEFAULT_OPPORTUNITIES_URL.'pipelines', ['locationId' => $locationId]);

        if (! $response->successful()) {
            // Do not expose upstream response bodies, which may contain credentials.
            throw new RuntimeException(match ($response->status()) {
                401 => 'Token no válido. Revisa el token guardado y sus permisos.',
                403 => 'Sin acceso. Revisa el locationId y el permiso opportunities.readonly del token.',
                400, 422 => 'GoHighLevel rechazó la consulta. Revisa el locationId.',
                429 => 'GoHighLevel alcanzó el límite de consultas. Intenta nuevamente más tarde.',
                default => 'No fue posible consultar GoHighLevel. Intenta nuevamente.',
            });
        }

        $pipelines = $response->json('pipelines');
        if (! is_array($pipelines)) {
            throw new RuntimeException('GoHighLevel devolvió una respuesta de pipelines no válida.');
        }

        return collect($pipelines)
            ->filter(fn ($pipeline) => is_array($pipeline) && is_string($pipeline['id'] ?? null) && is_string($pipeline['name'] ?? null))
            ->map(function (array $pipeline) {
                $stages = collect(is_array($pipeline['stages'] ?? null) ? $pipeline['stages'] : [])
                    ->filter(fn ($stage) => is_array($stage) && is_string($stage['id'] ?? null) && is_string($stage['name'] ?? null))
                    ->map(fn (array $stage) => $this->pipelineCatalogFields($stage, [
                        'id', 'name', 'position', 'showInFunnel', 'showInPieChart', 'stageWinProbability', 'originId',
                    ]))
                    ->sortBy('position', SORT_NUMERIC)->values()->all();

                return $this->pipelineCatalogFields($pipeline, [
                    'id', 'name', 'showInFunnel', 'showInPieChart', 'useOpportunityProbability', 'dateAdded', 'dateUpdated',
                ]) + ['stages' => $stages];
            })->values()->all();
    }

    public function supportsOpportunity(Integration $integration): bool
    {
        $type = \Illuminate\Support\Str::of((string) optional($integration->integrationtype)->name)
            ->ascii()->lower()->replace([' ', '-'], '_')->replaceMatches('/_+/', '_')->trim('_')->toString();

        return in_array($type, ['gohighlevel_oportunidad', 'gohighleve_oportunidad', 'gohighlevel_opportunity'], true);
    }

    private function pipelineCatalogFields(array $item, array $fields): array
    {
        return collect($fields)->mapWithKeys(fn ($field) => [
            $field => is_scalar($item[$field] ?? null) ? $item[$field] : null,
        ])->all();
    }

    public function sendToGohighlevel(Lead $lead, Integration $integration)
    {
        $url = $this->resolveUrl($integration);

        $token = $this->resolveToken($integration);

        $payload = $this->buildPayloadFromTemplate((string) $integration->body, $lead, $integration);

        $response = $this->postContactPayload($url, $token, $payload);

        if ($response->successful()) {
            $gohighlevelLeadId = $this->extractContactId($response);

            Log::info('GOHIGHLEVEL CREATE RESULT', [
                'integration_id' => $integration->id,
                'local_lead_id' => $lead->id,
                'gohighlevel_lead_id' => $gohighlevelLeadId,
                'response_json' => $response->json(),
            ]);

            if ($gohighlevelLeadId !== null) {
                $this->storeContactCrmId($lead, $integration, $gohighlevelLeadId);
            }
        }

        return $response;
    }

    public function sendToGohighlevelOportunidad(Lead $lead, Integration $integration)
    {
        $url = $this->resolveUrl($integration);
        $token = $this->resolveToken($integration);
        $contactPayload = $this->buildPayloadFromTemplate((string) $integration->body, $lead, $integration);
        $contactPayload = $this->omitEmptyPayloadFieldsWhenEnabled($contactPayload, $integration);
        $locationId = $this->resolveLocationId($contactPayload, $lead, $integration);

        Log::info('[gohighlevel-oportunidad] Iniciando integracion', [
            'integration_id' => $integration->id,
            'lead_id' => $lead->id,
        ]);

        $contactId = $this->findExistingContactId($lead, $integration, $token, $locationId);

        if ($contactId === null) {
            Log::info('[gohighlevel-oportunidad] Contacto no encontrado. Creando contacto.', [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
            ]);

            $contactResponse = $this->postContactPayload($url, $token, $contactPayload, '[gohighlevel-oportunidad]');

            if (! $contactResponse->successful()) {
                $this->logGohighlevelError('[gohighlevel-oportunidad] Error creando contacto', $contactResponse, [
                    'integration_id' => $integration->id,
                    'lead_id' => $lead->id,
                ]);

                return $contactResponse;
            }

            $contactId = $this->extractContactId($contactResponse);

            Log::info('[gohighlevel-oportunidad] Contacto creado', [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
                'contact_id' => $contactId,
            ]);
        } else {
            Log::info('[gohighlevel-oportunidad] Contacto encontrado', [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
                'contact_id' => $contactId,
            ]);
        }

        if ($contactId === null) {
            throw new RuntimeException('GoHighLevel creo o actualizo el contacto pero no devolvio id.');
        }

        $this->storeContactCrmId($lead, $integration, $contactId);

        $payload = $this->buildPayloadFromTemplate((string) $integration->body_oportunidad, $lead, $integration, 'body_oportunidad', [
            'contactId' => $contactId,
        ]);
        $payload['contactId'] = $contactId;
        $payload = $this->omitEmptyPayloadFieldsWhenEnabled($payload, $integration);
        // The service owns this structural field; it is never optional.
        $payload['contactId'] = $contactId;

        Log::info('[gohighlevel-oportunidad] Creando oportunidad', [
            'integration_id' => $integration->id,
            'lead_id' => $lead->id,
            'contact_id' => $contactId,
            'url' => self::DEFAULT_OPPORTUNITIES_URL,
        ]);

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->withHeaders([
                'Version' => 'v3',
            ])
            ->post(self::DEFAULT_OPPORTUNITIES_URL, $payload);

        if (! $response->successful()) {
            $this->logGohighlevelError('[gohighlevel-oportunidad] Error creando oportunidad', $response, [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
                'contact_id' => $contactId,
            ]);
        }

        if ($response->successful()) {
            $opportunityId = $response->json('opportunity.id')
                ?? $response->json('id')
                ?? $response->json('opportunityId');

            if ($opportunityId === null) {
                throw new RuntimeException('GoHighLevel creo la oportunidad pero no devolvio id.');
            }

            $lead->crm_id_oportunidad = $integration->crmIdPrefix().'-'.$opportunityId;
            $lead->save();

            Log::info('[gohighlevel-oportunidad] Oportunidad creada', [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
                'contact_id' => $contactId,
                'opportunity_id' => $opportunityId,
            ]);
        }

        return $response;
    }

    private function resolveUrl(Integration $integration): string
    {
        $url = rtrim((string) $integration->url, '/');

        return $url !== '' ? $url : self::DEFAULT_CONTACTS_UPSERT_URL;
    }

    /**
     * GoHighLevel validates an empty optional email as an invalid email. The
     * opt-in flag preserves legacy integrations while allowing bodies to omit
     * optional null/blank attributes. Numeric zero and false remain intact.
     */
    private function omitEmptyPayloadFieldsWhenEnabled(array $payload, Integration $integration): array
    {
        if (! $integration->omit_empty_payload_fields) {
            return $payload;
        }

        return $this->omitEmptyObjectFields($payload);
    }

    private function omitEmptyObjectFields(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $clean[$key] = array_is_list($value)
                    ? array_map(fn ($item) => is_array($item) && ! array_is_list($item) ? $this->omitEmptyObjectFields($item) : $item, $value)
                    : $this->omitEmptyObjectFields($value);

                continue;
            }

            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private function buildPayloadFromTemplate(string $template, $lead, Integration $integration, string $field = 'body', array $context = []): array
    {
        $template = trim($template);
        if ($template === '') {
            throw new RuntimeException("El campo {$field} de GoHighLevel debe estar configurado.");
        }

        $decoded = $this->decodeJsonTemplate($template, $context);
        if (! is_array($decoded)) {
            throw new RuntimeException("El campo {$field} de GoHighLevel debe ser un JSON valido.");
        }

        return $this->resolveLeadPlaceholders($decoded, $lead, $integration, $this->integrationVariableMappings($integration), $context);
    }

    private function extractContactId($response): ?string
    {
        $contactId = $response->json('contact.id')
            ?? $response->json('id')
            ?? $response->json('contact.contact_id')
            ?? $response->json('contactId')
            ?? $response->json('data.id')
            ?? $response->json('data.contact.id')
            ?? $response->json('data.contactId')
            ?? $response->json('data.contact_id')
            ?? $response->json('contacts.0.id');

        return $contactId === null ? null : (string) $contactId;
    }

    private function decodeJsonTemplate(string $template, array $context = []): ?array
    {
        $normalized = $this->replacePlaceholdersWithTokens($template, $context);
        if (preg_match('/\{\{.*?\}\}/s', $normalized)) {
            return null;
        }

        $decoded = json_decode($normalized, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function replacePlaceholdersWithTokens(string $value, array $context = []): string
    {
        $quotedPattern = '/"(\s*\{\{\s*([^}]+?)\s*\}\}\s*)"/';
        $inlinePattern = '/\{\{\s*([^}]+?)\s*\}\}/';

        $value = preg_replace_callback($quotedPattern, function ($matches) use ($context) {
            $token = $this->placeholderTokenForExpression($matches[2], $context);

            return $token === null
                ? $matches[0]
                : json_encode($token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $value);

        return preg_replace_callback($inlinePattern, function ($matches) use ($context) {
            $token = $this->placeholderTokenForExpression($matches[1], $context);

            return $token === null
                ? $matches[0]
                : json_encode($token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $value);
    }

    private function resolveLeadPlaceholders(array $payload, $lead, Integration $integration, $mappings, array $context = []): array
    {
        $resolved = [];

        foreach ($payload as $key => $value) {
            $resolved[$key] = $this->resolveLeadValue($value, $lead, $integration, $mappings, (string) $key, $context);
        }

        return $resolved;
    }

    private function resolveLeadValue($value, $lead, Integration $integration, $mappings, ?string $targetVariable = null, array $context = [])
    {
        if (is_array($value)) {
            $resolved = [];

            foreach ($value as $key => $nestedValue) {
                $resolved[$key] = $this->resolveLeadValue($nestedValue, $lead, $integration, $mappings, (string) $key, $context);
            }

            return $resolved;
        }

        if (! is_string($value)) {
            return $value;
        }

        if ($this->isIntegrationVariableToken($value)) {
            return $this->resolveIntegrationVariableTokenValue($value, $lead, $integration, 'GOHIGHLEVEL');
        }

        if (preg_match('/^'.preg_quote(self::CONTEXT_TOKEN_PREFIX, '/').'(.+)$/', $value, $matches)) {
            return data_get($context, $matches[1], '');
        }

        if (! preg_match('/^'.preg_quote(self::LEAD_TOKEN_PREFIX, '/').'(.+)$/', $value, $matches)) {
            return $value;
        }

        $leadField = $matches[1];
        $resolved = data_get($lead, $leadField);

        if ($resolved === null) {
            $leadField = $this->leadFieldAlias($matches[1]);
            $resolved = data_get($lead, $leadField, '');
        }

        return $this->resolveMappedIntegrationValue($mappings, $targetVariable, $leadField, $resolved, $resolved ?? '', 'GOHIGHLEVEL');
    }

    private function placeholderToken(string $field): string
    {
        return self::LEAD_TOKEN_PREFIX.$field;
    }

    private function placeholderTokenForExpression(string $expression, array $context = []): ?string
    {
        $contextField = $this->normalizeContextField($expression, $context);

        if ($contextField !== null) {
            return self::CONTEXT_TOKEN_PREFIX.$contextField;
        }

        $field = $this->normalizeLeadField($expression);

        if ($field !== null) {
            return $this->placeholderToken($field);
        }

        $variableName = $this->normalizeIntegrationVariableExpression($expression);

        return $variableName === null ? null : $this->integrationVariableToken($variableName);
    }

    private function normalizeLeadField(string $expression): ?string
    {
        $expression = trim($expression);

        if (! preg_match('/^\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $expression, $matches)) {
            return null;
        }

        return $matches[1] !== '' ? $matches[1] : null;
    }

    private function normalizeContextField(string $expression, array $context): ?string
    {
        $expression = trim($expression);

        foreach (array_keys($context) as $key) {
            if (strcasecmp($expression, (string) $key) === 0) {
                return (string) $key;
            }
        }

        return null;
    }

    private function leadFieldAlias(string $field): string
    {
        return match ($field) {
            'firstName' => 'name',
            'lastName' => 'last_name',
            default => $field,
        };
    }

    private function resolveToken(Integration $integration): string
    {
        $token = trim((string) $integration->tokent);

        if ($token === '') {
            throw new RuntimeException('No existe token de autenticacion configurado para GoHighLevel.');
        }

        return str_starts_with(strtolower($token), 'bearer ')
            ? trim(substr($token, 7))
            : $token;
    }

    private function resolveLocationId(array $contactPayload, Lead $lead, Integration $integration): string
    {
        $locationId = $this->payloadLocationId($contactPayload);

        if ($locationId !== '') {
            return $locationId;
        }

        if (filled($integration->body_oportunidad)) {
            $opportunityPayload = $this->buildPayloadFromTemplate((string) $integration->body_oportunidad, $lead, $integration, 'body_oportunidad', [
                'contactId' => '',
            ]);
            $locationId = $this->payloadLocationId($opportunityPayload);
        }

        if ($locationId === '') {
            throw new RuntimeException('GoHighLevel-Oportunidad requiere locationId en body o body_oportunidad para buscar contactos.');
        }

        return $locationId;
    }

    private function payloadLocationId(array $payload): string
    {
        return trim((string) (data_get($payload, 'locationId') ?? data_get($payload, 'location_id') ?? ''));
    }

    private function findExistingContactId(Lead $lead, Integration $integration, string $token, string $locationId): ?string
    {
        $phone = $this->normalizePhoneForSearch($lead->phone);
        $email = $this->normalizeEmailForSearch($lead->email);

        Log::info('[gohighlevel-oportunidad] Buscando contacto', [
            'integration_id' => $integration->id,
            'lead_id' => $lead->id,
            'phone_present' => $phone !== '',
            'email_present' => $email !== '',
        ]);

        $phoneContactId = $phone !== ''
            ? $this->findDuplicateContactId($token, $locationId, 'number', $phone, $lead, $integration)
            : null;

        $emailContactId = $email !== ''
            ? $this->findDuplicateContactId($token, $locationId, 'email', $email, $lead, $integration)
            : null;

        if ($phoneContactId !== null && $emailContactId !== null && $phoneContactId !== $emailContactId) {
            Log::warning('[gohighlevel-oportunidad] Conflicto de contactos por telefono y email', [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
                'phone_contact_id' => $phoneContactId,
                'email_contact_id' => $emailContactId,
            ]);

            throw new RuntimeException('GoHighLevel encontro contactos distintos por telefono y correo; revisa duplicados antes de crear oportunidad.');
        }

        return $phoneContactId ?? $emailContactId;
    }

    private function findDuplicateContactId(string $token, string $locationId, string $field, string $value, Lead $lead, Integration $integration): ?string
    {
        $response = Http::acceptJson()
            ->withToken($token)
            ->withHeaders([
                'Version' => '2021-07-28',
            ])
            ->get(self::DEFAULT_CONTACTS_DUPLICATE_SEARCH_URL, [
                'locationId' => $locationId,
                $field => $value,
            ]);

        if (! $response->successful()) {
            $this->logGohighlevelError('[gohighlevel-oportunidad] Error buscando contacto', $response, [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
                'criteria' => $field,
            ]);

            throw new RuntimeException($this->gohighlevelResponseMessage('buscar contacto', $response));
        }

        return $this->extractContactId($response);
    }

    private function postContactPayload(string $url, string $token, array $payload, string $label = 'GOHIGHLEVEL')
    {
        Log::info($label.' CONTACT URL', ['url' => $url]);
        Log::info($label.' CONTACT PAYLOAD JSON', $label === 'GOHIGHLEVEL'
            ? ['json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
            : ['payload_keys' => array_keys($payload)]);

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->withHeaders([
                'Version' => '2021-07-28',
            ])
            ->post($url, $payload);

        Log::info($label.' CONTACT RESPONSE', $label === 'GOHIGHLEVEL'
            ? ['status' => $response->status(), 'body' => $response->body()]
            : ['status' => $response->status(), 'response_message' => $response->json('message')]);

        return $response;
    }

    private function storeContactCrmId(Lead $lead, Integration $integration, string $contactId): void
    {
        $lead->crm_id = $integration->crmIdPrefix().'-'.$contactId;
        $lead->save();

        Log::info('LEAD UPDATED crm_id', [
            'local_lead_id' => $lead->id,
            'crm_id' => $lead->crm_id,
            'gohighlevel_lead_id' => $contactId,
        ]);
    }

    private function normalizeEmailForSearch($email): string
    {
        return strtolower(trim((string) $email));
    }

    private function normalizePhoneForSearch($phone): string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return $phone;
        }

        return str_starts_with($phone, '+') ? '+'.$digits : $digits;
    }

    private function logGohighlevelError(string $message, $response, array $context = []): void
    {
        Log::warning($message, array_merge($context, [
            'status' => $response->status(),
            'response_message' => $response->json('message') ?? $response->body(),
        ]));
    }

    private function gohighlevelResponseMessage(string $action, $response): string
    {
        $message = trim((string) ($response->json('message') ?? $response->body()));

        if ($response->status() === 401) {
            return 'GoHighLevel no autorizo '.$action.': revisa token y scopes.';
        }

        if ($response->status() === 403 && str_contains(strtolower($message), 'location')) {
            return 'GoHighLevel no permite acceder al locationId configurado.';
        }

        return 'GoHighLevel fallo al '.$action.($message !== '' ? ': '.$message : '.');
    }
}
