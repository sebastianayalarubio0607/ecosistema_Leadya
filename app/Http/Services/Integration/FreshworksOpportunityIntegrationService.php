<?php

namespace App\Http\Services\Integration;

use App\Http\Services\Integration\Concerns\ResolvesIntegrationVariableMappings;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Isolated Freshworks deal flow. The legacy Freshworks integration remains untouched.
 */
class FreshworksOpportunityIntegrationService
{
    use ResolvesIntegrationVariableMappings;

    private const LEAD_TOKEN_PREFIX = '__freshworks_opportunity_lead__:';
    private const CONTACT_TOKEN_PREFIX = '__freshworks_opportunity_contact__:';
    public function supports(Integration $integration): bool
    {
        return $this->type($integration) === 'freshworks_oportunidad';
    }

    public function getPipelines(Integration $integration): array
    {
        $response = $this->request($integration)->get($this->endpoint($integration, '/crm/sales/api/selector/deal_pipelines'));
        $this->throwForResponse($response, 'consultar los pipelines');

        return collect($response->json('deal_pipelines', []))
            ->filter(fn ($pipeline) => is_array($pipeline) && filled($pipeline['id'] ?? null) && filled($pipeline['name'] ?? null))
            ->map(fn (array $pipeline) => ['id' => (string) $pipeline['id'], 'name' => trim((string) $pipeline['name'])])
            ->values()->all();
    }

    public function getStages(Integration $integration, string $pipelineId): array
    {
        $response = $this->request($integration)->get($this->endpoint(
            $integration,
            '/crm/sales/api/selector/deal_pipelines/'.rawurlencode($pipelineId).'/deal_stages'
        ));
        $this->throwForResponse($response, 'consultar los stages');

        return collect($response->json('deal_stages', []))
            ->filter(fn ($stage) => is_array($stage) && filled($stage['id'] ?? null) && filled($stage['name'] ?? null))
            ->map(fn (array $stage) => ['id' => (string) $stage['id'], 'name' => trim((string) $stage['name'])])
            ->values()->all();
    }

    public function sendToFreshworksOpportunity(Lead $lead, Integration $integration): Response
    {
        if (! $this->supports($integration)) {
            throw new RuntimeException('La integración no es de tipo Freshworks-Oportunidad.');
        }

        $mobileNumber = $this->validMobileNumber($lead);
        $contactId = $this->findContactIdByMobileNumber($integration, $mobileNumber);

        if ($contactId === null) {
            $contact = $this->contactPayload($integration, $lead, $mobileNumber);
            Log::info('FRESHWORKS OPPORTUNITY CONTACT CREATE REQUEST', [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
                'url' => $this->endpoint($integration, '/crm/sales/api/contacts'),
                'contact_fields' => array_keys($contact),
            ]);
            $contactResponse = $this->request($integration)
                ->post($this->endpoint($integration, '/crm/sales/api/contacts'), ['contact' => $contact]);
            $this->throwForResponse($contactResponse, 'crear el contacto');

            $contactId = $contactResponse->json('contact.id') ?? $contactResponse->json('id');
            if (! filled($contactId)) {
                throw new RuntimeException('Freshworks no devolvió el ID del contacto creado.');
            }
        } else {
            Log::info('FRESHWORKS OPPORTUNITY EXISTING CONTACT USED', [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
                'contact_id' => $contactId,
            ]);
        }

        $lead->crm_id = $integration->crmIdPrefix().'-'.$contactId;
        $lead->save();

        $deal = $this->dealPayload($integration, $lead, (string) $contactId);
        $dealResponse = $this->request($integration)
            ->post($this->endpoint($integration, '/crm/sales/api/deals'), ['deal' => $deal]);
        $this->throwForResponse($dealResponse, 'crear la oportunidad');

        $dealId = $dealResponse->json('deal.id') ?? $dealResponse->json('id');
        if (! filled($dealId)) {
            throw new RuntimeException('Freshworks no devolvió el ID de la oportunidad creada.');
        }

        $lead->crm_id_oportunidad = $integration->crmIdPrefix().'-'.$dealId;
        $lead->save();

        return $dealResponse;
    }

    public function baseUrl(Integration $integration): string
    {
        $url = trim((string) $integration->url);
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user'], $parts['pass'])) {
            throw new RuntimeException('La URL de Freshworks debe ser una URL base HTTPS válida.');
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        if ($path !== '' || isset($parts['query'], $parts['fragment'])) {
            throw new RuntimeException('Guarda únicamente la URL principal de Freshworks, sin rutas ni parámetros.');
        }

        return 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function request(Integration $integration)
    {
        $token = trim((string) $integration->tokent);
        if ($token === '') {
            throw new RuntimeException('No existe token de Freshworks guardado.');
        }

        $token = preg_replace('/^Token\\s+token=/i', '', $token);

        return Http::acceptJson()->asJson()->connectTimeout(5)->timeout(15)
            ->withHeader('Authorization', 'Token token='.$token);
    }

    private function endpoint(Integration $integration, string $path): string
    {
        return $this->baseUrl($integration).$path;
    }

    private function throwForResponse(Response $response, string $action): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $message = $this->responseErrorMessage($response);
        Log::warning('FRESHWORKS OPPORTUNITY REQUEST FAILED', [
            'action' => $action,
            'status' => $status,
            'response_message' => $message,
        ]);

        $summary = match ($status) {
            401 => 'Token de Freshworks no válido.',
            403 => 'El token no tiene permisos para '.$action.'.',
            422 => 'Freshworks rechazó los datos al '.$action.'.',
            429 => 'Freshworks alcanzó su límite de consultas. Intenta más tarde.',
            default => "Freshworks respondió HTTP {$status} al {$action}.",
        };

        throw new RuntimeException($message === '' ? $summary : $summary.' '.$message);
    }

    private function responseErrorMessage(Response $response): string
    {
        $json = $response->json();
        $message = is_array($json)
            ? ($json['message'] ?? $json['error'] ?? $json['errors'] ?? null)
            : null;

        if (is_array($message) || is_object($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $message = trim((string) ($message ?? ''));

        return mb_substr($message, 0, 1000);
    }

    private function validMobileNumber(Lead $lead): string
    {
        $raw = trim((string) $lead->phone);
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === null || strlen($digits) < 7 || strlen($digits) > 15) {
            throw new RuntimeException('El lead no tiene un mobile_number válido; no se creó contacto ni oportunidad.');
        }

        return str_starts_with($raw, '+') ? '+'.$digits : $digits;
    }

    private function findContactIdByMobileNumber(Integration $integration, string $mobileNumber): ?string
    {
        $response = $this->request($integration)
            ->post($this->endpoint($integration, '/crm/sales/api/filtered_search/contact'), [
                'filter_rule' => [[
                    'attribute' => 'mobile_number',
                    'operator' => 'is_in',
                    'value' => $mobileNumber,
                ]],
            ]);
        $this->throwForResponse($response, 'buscar el contacto');

        $contacts = $response->json('contacts', []);
        if (! is_array($contacts)) {
            throw new RuntimeException('Freshworks devolvió una respuesta inválida al buscar el contacto.');
        }

        $ids = collect($contacts)
            ->pluck('id')
            ->filter(fn ($id) => filled($id))
            ->unique()
            ->values();

        if ($ids->count() > 1) {
            throw new RuntimeException('Freshworks encontró más de un contacto con el mismo mobile_number.');
        }

        return $ids->first() === null ? null : (string) $ids->first();
    }

    private function contactPayload(Integration $integration, Lead $lead, string $mobileNumber): array
    {
        $payload = $this->templatePayload($integration, $integration->body, $lead, null, 'contacto');
        $payload['mobile_number'] = $mobileNumber;
        $payload['first_name'] ??= filled($lead->name) ? $lead->name : 'Sin nombre';
        $payload['last_name'] ??= filled($lead->last_name) ? $lead->last_name : 'Sin apellido';

        if (filled($lead->email) && ! array_key_exists('email', $payload)) {
            $payload['email'] = $lead->email;
        }

        return $payload;
    }

    private function dealPayload(Integration $integration, Lead $lead, string $contactId): array
    {
        $payload = $this->templatePayload($integration, $integration->body_oportunidad, $lead, $contactId, 'oportunidad');
        $payload['contacts_added_list'] = [(int) $contactId];

        foreach (['name', 'deal_pipeline_id', 'deal_stage_id'] as $field) {
            if (! filled($payload[$field] ?? null)) {
                throw new RuntimeException("El body de oportunidad requiere {$field}.");
            }
        }

        return $payload;
    }

    private function templatePayload(Integration $integration, ?string $template, Lead $lead, ?string $contactId, string $label): array
    {
        $template = trim((string) $template);
        if ($template === '') {
            throw new RuntimeException("El body JSON de {$label} es obligatorio.");
        }

        $normalized = $this->replacePlaceholdersWithTokens($template, $contactId);
        $decoded = json_decode((string) $normalized, true);
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException("El body JSON de {$label} debe ser un objeto JSON válido.");
        }

        return $this->resolvePayloadValues($decoded, $lead, $integration, $this->integrationVariableMappings($integration), $contactId);
    }

    private function replacePlaceholdersWithTokens(string $template, ?string $contactId): string
    {
        $quotedPattern = '/"(\s*\{\{\s*([^}]+?)\s*\}\}\s*)"/';
        $inlinePattern = '/\{\{\s*([^}]+?)\s*\}\}/';
        $template = preg_replace_callback($quotedPattern, function (array $matches) use ($contactId) {
            $token = $this->placeholderTokenForExpression($matches[2], $contactId);

            return $token === null ? $matches[0] : json_encode($token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $template);

        return preg_replace_callback($inlinePattern, function (array $matches) use ($contactId) {
            $token = $this->placeholderTokenForExpression($matches[1], $contactId);

            return $token === null ? $matches[0] : json_encode($token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, (string) $template);
    }

    private function placeholderTokenForExpression(string $expression, ?string $contactId): ?string
    {
        $expression = trim($expression);
        if ($expression === 'contactId' && $contactId !== null) {
            return self::CONTACT_TOKEN_PREFIX.'id';
        }

        $leadPath = $this->normalizeIntegrationLeadPlaceholderPath($expression);
        if ($leadPath !== null) {
            return self::LEAD_TOKEN_PREFIX.$leadPath;
        }

        $variable = $this->normalizeIntegrationVariableExpression($expression);

        return $variable === null ? null : $this->integrationVariableToken($variable);
    }

    private function resolvePayloadValues(array $payload, Lead $lead, Integration $integration, $mappings, ?string $contactId): array
    {
        foreach ($payload as $key => $value) {
            $payload[$key] = $this->resolvePayloadValue($value, $lead, $integration, $mappings, (string) $key, $contactId);
        }

        return $payload;
    }

    private function resolvePayloadValue(mixed $value, Lead $lead, Integration $integration, $mappings, string $targetVariable, ?string $contactId): mixed
    {
        if (is_array($value)) {
            return $this->resolvePayloadValues($value, $lead, $integration, $mappings, $contactId);
        }

        if (! is_string($value)) {
            return $value;
        }

        if ($this->isIntegrationVariableToken($value)) {
            return $this->resolveIntegrationVariableTokenValue($value, $lead, $integration, 'FRESHWORKS OPPORTUNITY');
        }

        if ($value === self::CONTACT_TOKEN_PREFIX.'id') {
            return $contactId;
        }

        if (! str_starts_with($value, self::LEAD_TOKEN_PREFIX)) {
            return $value;
        }

        $leadField = substr($value, strlen(self::LEAD_TOKEN_PREFIX));
        $resolved = data_get($lead, $leadField);

        return $this->resolveMappedIntegrationValue(
            $mappings,
            $targetVariable,
            $leadField,
            $resolved,
            $resolved ?? '',
            'FRESHWORKS OPPORTUNITY'
        );
    }

    private function type(Integration $integration): string
    {
        $integration->loadMissing('integrationtype:id,name');

        return Str::of((string) optional($integration->integrationtype)->name)
            ->ascii()->lower()->replace([' ', '-'], '_')->replaceMatches('/_+/', '_')->trim('_')->toString();
    }
}
