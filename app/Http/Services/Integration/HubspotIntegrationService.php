<?php

namespace App\Http\Services\Integration;

use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HubspotIntegrationService
{
    public function sendToHubspot(Lead $lead, Integration $integration)
    {
        $token = trim((string) $integration->tokent);
        if ($token === '') {
            throw new RuntimeException('No existe access_token configurado para HubSpot.');
        }

        $searchUrl = trim((string) $integration->url_consulta_lead);
        $dealUrl = trim((string) $integration->url_negocio);
        $createLeadUrl = trim((string) $integration->url_creacionlead);

        if ($searchUrl === '') {
            throw new RuntimeException('No existe url_consulta_lead configurada para HubSpot.');
        }

        if ($dealUrl === '') {
            throw new RuntimeException('No existe url_negocio configurada para HubSpot.');
        }

        if ($createLeadUrl === '') {
            throw new RuntimeException('No existe url_creacionlead configurada para HubSpot.');
        }

        $this->validateHubspotUrls($searchUrl, $createLeadUrl, $dealUrl);

        Log::info('HUBSPOT URLS', [
            'searchUrl' => $searchUrl,
            'createUrl' => $createLeadUrl,
            'dealUrl' => $dealUrl,
        ]);

        $contactId = $this->findContactIdByEmail($lead, $integration, $token, $searchUrl);
        $response = null;

        if ($contactId === null) {
            $response = $this->createContact($lead, $integration, $token, $createLeadUrl);
            if (!$response->successful()) {
                return $response;
            }

            $contactId = $response->json('id')
                ?? $response->json('vid')
                ?? $response->json('contact.id')
                ?? null;

            if ($contactId === null) {
                throw new RuntimeException('HubSpot creo el contacto pero no devolvio id. Body: ' . $response->body());
            }
        }

        $this->storeCrmId($lead, $integration, $contactId);

        $dealPayload = $this->buildDealPayload($lead, $integration, $contactId);

        Log::info('HUBSPOT DEAL URL', ['url' => $dealUrl]);
        Log::info('HUBSPOT DEAL PAYLOAD JSON', [
            'json' => json_encode($dealPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'request_step' => 'create_deal',
        ]);

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->post($dealUrl, $dealPayload);

        Log::info('HUBSPOT DEAL RESPONSE', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response;
    }

    private function findContactIdByEmail(Lead $lead, Integration $integration, string $token, string $url): ?string
    {
        $payload = [
            'query' => $lead->email,
            'properties' => ['firstname', 'lastname', 'email', 'phone', 'mobilephone'],
            'limit' => 5,
        ];

        Log::info('HUBSPOT SEARCH URL', ['url' => $url]);
        Log::info('HUBSPOT SEARCH PAYLOAD JSON', [
            'json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'crm_id_local' => $lead->crm_id,
            'crm_id_hubspot_clean' => $this->extractHubspotIdFromCrmId($lead, $integration),
            'request_step' => 'search_contact',
        ]);

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->post($url, $payload);

        Log::info('HUBSPOT SEARCH RESPONSE', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('No se pudo consultar contacto en HubSpot: ' . $response->body());
        }

        $contactId = $response->json('results.0.id');

        if ($contactId !== null) {
            $this->storeCrmId($lead, $integration, $contactId);
        }

        return $contactId !== null ? (string) $contactId : null;
    }

    private function createContact(Lead $lead, Integration $integration, string $token, string $url)
    {
        $payload = $this->buildContactPayload($lead, $integration);

        Log::info('HUBSPOT CREATE CONTACT URL', ['url' => $url]);
        Log::info('HUBSPOT CREATE CONTACT PAYLOAD JSON', [
            'json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'request_step' => 'create_contact',
        ]);

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->post($url, $payload);

        Log::info('HUBSPOT CREATE CONTACT RESPONSE', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response;
    }

    private function buildContactPayload(Lead $lead, Integration $integration): array
    {
        $template = trim((string) $integration->body);
        if ($template === '') {
            throw new RuntimeException('El campo body de HubSpot debe estar configurado.');
        }

        $decoded = $this->decodeBodyTemplate($template);
        if (!is_array($decoded)) {
            throw new RuntimeException('El campo body de HubSpot debe ser un JSON valido.');
        }

        return $this->resolveBodyPlaceholders($decoded, $lead);
    }

    private function buildDealPayload(Lead $lead, Integration $integration, string $contactId): array
    {
        $hubspotContactId = $this->extractHubspotIdFromCrmId($lead, $integration) ?? $contactId;

        return [
            'properties' => [
                'dealname' => $this->renderTemplateString((string) $integration->dealname, $lead),
                'dealstage' => (string) $integration->dealstage,
                'pipeline' => 'default',
                'amount' => '500',
            ],
            'associations' => [[
                'to' => [
                    'id' => $this->normalizeIntegerLike($hubspotContactId),
                ],
                'types' => [[
                    'associationCategory' => 'HUBSPOT_DEFINED',
                    'associationTypeId' => 3,
                ]],
            ]],
        ];
    }

    private function storeCrmId(Lead $lead, Integration $integration, string $contactId): void
    {
        $lead->crm_id = $integration->crmIdPrefix() . '-' . $contactId;
        $lead->save();

        Log::info('LEAD UPDATED crm_id', [
            'local_lead_id' => $lead->id,
            'crm_id' => $lead->crm_id,
            'hubspot_contact_id' => $contactId,
        ]);
    }

    private function extractHubspotIdFromCrmId(Lead $lead, Integration $integration): ?string
    {
        $crmId = trim((string) $lead->crm_id);
        if ($crmId === '') {
            return null;
        }

        $prefixes = array_unique([
            $integration->crmIdPrefix(),
            (string) $integration->id,
        ]);

        foreach ($prefixes as $prefix) {
            $prefix = trim((string) $prefix);
            if ($prefix !== '' && str_starts_with($crmId, $prefix . '-')) {
                return substr($crmId, strlen($prefix) + 1);
            }
        }

        return $crmId;
    }

    private function validateHubspotUrls(string $searchUrl, string $createLeadUrl, string $dealUrl): void
    {
        if ($searchUrl === $dealUrl) {
            throw new RuntimeException('url_consulta_lead no puede usar la misma URL de negocios de HubSpot.');
        }

        if ($createLeadUrl === $searchUrl) {
            throw new RuntimeException('url_creacionlead no puede apuntar al endpoint de search de HubSpot.');
        }

        if (str_contains(strtolower($searchUrl), '/deals')) {
            throw new RuntimeException('url_consulta_lead no debe apuntar al endpoint de deals de HubSpot.');
        }

        if (str_contains(strtolower($createLeadUrl), '/search')) {
            throw new RuntimeException('url_creacionlead no debe apuntar al endpoint de search de HubSpot.');
        }
    }

    private function decodeBodyTemplate(string $template): ?array
    {
        $normalized = $this->replaceBodyPlaceholdersWithTokens($template);

        $decoded = json_decode($normalized, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function replaceBodyPlaceholdersWithTokens(string $value): string
    {
        $quotedPattern = '/"(\s*\{\{\s*([^}]+?)\s*\}\}\s*)"/';
        $inlinePattern = '/\{\{\s*([^}]+?)\s*\}\}/';

        $value = preg_replace_callback($quotedPattern, function ($matches) {
            $path = $this->normalizePlaceholderPath($matches[2]);

            return $path === null
                ? $matches[0]
                : json_encode($this->placeholderToken($path), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $value);

        return preg_replace_callback($inlinePattern, function ($matches) {
            $path = $this->normalizePlaceholderPath($matches[1]);

            return $path === null
                ? $matches[0]
                : json_encode($this->placeholderToken($path), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $value);
    }

    private function resolveBodyPlaceholders(array $payload, Lead $lead): array
    {
        return $this->replacePlaceholders($payload, [], $lead);
    }

    private function replacePlaceholders($value, array $replacements, ?Lead $lead = null)
    {
        if (is_array($value)) {
            $result = [];

            foreach ($value as $key => $item) {
                $resolved = $this->replacePlaceholders($item, $replacements, $lead);

                if ($resolved === null || $resolved === '') {
                    continue;
                }

                $result[$key] = $resolved;
            }

            return $result;
        }

        if (is_string($value)) {
            if ($lead && preg_match('/^__hubspot_lead_field__:(.+)$/', $value, $matches)) {
                return data_get($lead, $matches[1]);
            }

            return strtr($value, $replacements);
        }

        return $value;
    }

    private function renderTemplateString(string $value, Lead $lead): string
    {
        return (string) preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}/', function ($matches) use ($lead) {
            $path = $this->normalizePlaceholderPath($matches[1]);

            if ($path === null) {
                return $matches[0];
            }

            $resolved = data_get($lead, $path);

            return $resolved === null ? '' : (string) $resolved;
        }, $value);
    }

    private function placeholderToken(string $field): string
    {
        return '__hubspot_lead_field__:' . $field;
    }

    private function normalizePlaceholderPath(string $expression): ?string
    {
        $expression = trim($expression);

        if (!preg_match('/^\$?lead(?:(?:->|\.)[A-Za-z_][A-Za-z0-9_]*)+$/', $expression)) {
            return null;
        }

        $path = preg_replace('/^\$?lead(?:->|\.)/', '', $expression);
        $path = str_replace('->', '.', (string) $path);
        $path = trim((string) $path, '.');

        if (str_starts_with($path, 'campaign_origin.')) {
            $path = 'campaignOrigin.' . substr($path, strlen('campaign_origin.'));
        }

        return $path !== '' ? $path : null;
    }

    private function normalizeIntegerLike($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int) $value;
        }

        return $value;
    }
}
