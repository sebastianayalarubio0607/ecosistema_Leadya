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
    private const DEFAULT_OPPORTUNITIES_URL = 'https://services.leadconnectorhq.com/opportunities/';

    public function sendToGohighlevel(Lead $lead, Integration $integration)
    {
        $url = $this->resolveUrl($integration);

        $token = trim((string) $integration->tokent);
        if ($token === '') {
            throw new RuntimeException('No existe token de autenticacion configurado para GoHighLevel.');
        }

        $payload = $this->buildPayloadFromTemplate((string) $integration->body, $lead, $integration);

        Log::info('GOHIGHLEVEL URL', ['url' => $url]);
        Log::info('GOHIGHLEVEL PAYLOAD JSON', [
            'json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->withHeaders([
                'Version' => '2021-07-28',
            ])
            ->post($url, $payload);

        Log::info('GOHIGHLEVEL RESPONSE', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->successful()) {
            $gohighlevelLeadId = $this->extractContactId($response);

            Log::info('GOHIGHLEVEL CREATE RESULT', [
                'integration_id' => $integration->id,
                'local_lead_id' => $lead->id,
                'gohighlevel_lead_id' => $gohighlevelLeadId,
                'response_json' => $response->json(),
            ]);

            if ($gohighlevelLeadId !== null) {
                $lead->crm_id = $integration->crmIdPrefix() . '-' . $gohighlevelLeadId;
                $lead->save();

                Log::info('LEAD UPDATED crm_id', [
                    'local_lead_id' => $lead->id,
                    'crm_id' => $lead->crm_id,
                    'gohighlevel_lead_id' => $gohighlevelLeadId,
                ]);
            }
        }

        return $response;
    }

    public function sendToGohighlevelOportunidad(Lead $lead, Integration $integration)
    {
        $contactResponse = $this->sendToGohighlevel($lead, $integration);

        if (! $contactResponse->successful()) {
            return $contactResponse;
        }

        $contactId = $this->extractContactId($contactResponse);
        if ($contactId === null) {
            throw new RuntimeException('GoHighLevel creo o actualizo el contacto pero no devolvio id.');
        }

        $token = trim((string) $integration->tokent);
        $payload = $this->buildPayloadFromTemplate((string) $integration->body_oportunidad, $lead, $integration, 'body_oportunidad');
        $payload['contactId'] = $contactId;

        Log::info('GOHIGHLEVEL OPPORTUNITY URL', ['url' => self::DEFAULT_OPPORTUNITIES_URL]);
        Log::info('GOHIGHLEVEL OPPORTUNITY PAYLOAD JSON', [
            'json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->withHeaders([
                'Version' => 'v3',
            ])
            ->post(self::DEFAULT_OPPORTUNITIES_URL, $payload);

        Log::info('GOHIGHLEVEL OPPORTUNITY RESPONSE', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->successful()) {
            $opportunityId = $response->json('opportunity.id')
                ?? $response->json('id')
                ?? $response->json('opportunityId');

            if ($opportunityId === null) {
                throw new RuntimeException('GoHighLevel creo la oportunidad pero no devolvio id.');
            }

            $lead->crm_id_oportunidad = $integration->crmIdPrefix() . '-' . $opportunityId;
            $lead->save();
        }

        return $response;
    }

    private function resolveUrl(Integration $integration): string
    {
        $url = rtrim((string) $integration->url, '/');

        return $url !== '' ? $url : self::DEFAULT_CONTACTS_UPSERT_URL;
    }

    private function buildPayloadFromTemplate(string $template, $lead, Integration $integration, string $field = 'body'): array
    {
        $template = trim($template);
        if ($template === '') {
            throw new RuntimeException("El campo {$field} de GoHighLevel debe estar configurado.");
        }

        $decoded = $this->decodeJsonTemplate($template);
        if (!is_array($decoded)) {
            throw new RuntimeException("El campo {$field} de GoHighLevel debe ser un JSON valido.");
        }

        return $this->resolveLeadPlaceholders($decoded, $lead, $integration, $this->integrationVariableMappings($integration));
    }

    private function extractContactId($response): ?string
    {
        $contactId = $response->json('contact.id')
            ?? $response->json('id')
            ?? $response->json('contact.contact_id')
            ?? $response->json('contactId');

        return $contactId === null ? null : (string) $contactId;
    }

    private function decodeJsonTemplate(string $template): ?array
    {
        $normalized = $this->replaceLeadPlaceholdersWithTokens($template);
        if (preg_match('/\{\{.*?\}\}/s', $normalized)) {
            return null;
        }

        $decoded = json_decode($normalized, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function replaceLeadPlaceholdersWithTokens(string $value): string
    {
        $quotedPattern = '/"(\s*\{\{\s*([^}]+?)\s*\}\}\s*)"/';
        $inlinePattern = '/\{\{\s*([^}]+?)\s*\}\}/';

        $value = preg_replace_callback($quotedPattern, function ($matches) {
            $field = $this->normalizeLeadField($matches[2]);

            return $field === null
                ? $matches[0]
                : json_encode($this->placeholderToken($field), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $value);

        return preg_replace_callback($inlinePattern, function ($matches) {
            $field = $this->normalizeLeadField($matches[1]);

            return $field === null
                ? $matches[0]
                : json_encode($this->placeholderToken($field), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $value);
    }

    private function resolveLeadPlaceholders(array $payload, $lead, Integration $integration, $mappings): array
    {
        $resolved = [];

        foreach ($payload as $key => $value) {
            $resolved[$key] = $this->resolveLeadValue($value, $lead, $integration, $mappings, (string) $key);
        }

        return $resolved;
    }

    private function resolveLeadValue($value, $lead, Integration $integration, $mappings, ?string $targetVariable = null)
    {
        if (is_array($value)) {
            $resolved = [];

            foreach ($value as $key => $nestedValue) {
                $resolved[$key] = $this->resolveLeadValue($nestedValue, $lead, $integration, $mappings, (string) $key);
            }

            return $resolved;
        }

        if (!is_string($value)) {
            return $value;
        }

        if (!preg_match('/^__gohighlevel_lead_field__:(.+)$/', $value, $matches)) {
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
        return '__gohighlevel_lead_field__:' . $field;
    }

    private function normalizeLeadField(string $expression): ?string
    {
        $expression = trim($expression);

        if (!preg_match('/^\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $expression, $matches)) {
            return null;
        }

        return $matches[1] !== '' ? $matches[1] : null;
    }

    private function leadFieldAlias(string $field): string
    {
        return match ($field) {
            'firstName' => 'name',
            'lastName' => 'last_name',
            default => $field,
        };
    }

}
