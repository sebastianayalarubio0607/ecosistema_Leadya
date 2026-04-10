<?php

namespace App\Http\Services\Integration;

use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SalesforceIntegrationService
{
    public function sendToSalesforce(Lead $lead, Integration $integration)
    {
        $url = rtrim((string) $integration->url, '/');
        if ($url === '') {
            throw new RuntimeException('No existe url configurada para Salesforce.');
        }

        $payload = $this->buildPayload($lead, $integration);
        $oauth = $this->getValidAccessToken($integration);

        Log::info('SALESFORCE URL', ['url' => $url]);
        Log::info('SALESFORCE PAYLOAD JSON', [
            'json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = $this->sendLeadRequest($url, $payload, $oauth['access_token']);

        if ($response->status() === 401) {
            $oauth = $this->refreshAccessToken($integration);
            $response = $this->sendLeadRequest($url, $payload, $oauth['access_token']);
        }

        Log::info('SALESFORCE RESPONSE', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->successful()) {
            $salesforceLeadId = $response->json('id')
                ?? $response->json('ServiceOutput.id')
                ?? $response->json('data.id')
                ?? $response->json('result.id')
                ?? null;

            Log::info('SALESFORCE CREATE RESULT', [
                'integration_id' => $integration->id,
                'local_lead_id' => $lead->id,
                'salesforce_lead_id' => $salesforceLeadId,
                'response_json' => $response->json(),
            ]);

            if ($salesforceLeadId !== null) {
                $lead->crm_id = $integration->id . '-' . $salesforceLeadId;
                $lead->save();
            }
        }

        return $response;
    }

    private function getValidAccessToken(Integration $integration): array
    {
        $accessToken = trim((string) $integration->tokent);

        if ($accessToken === '' || $this->tokenNeedsRefresh($integration)) {
            return $this->refreshAccessToken($integration);
        }

        return [
            'access_token' => $accessToken,
        ];
    }

    private function tokenNeedsRefresh(Integration $integration): bool
    {
        if (!$integration->token_expires_at) {
            return true;
        }

        return $integration->token_expires_at->copy()->subSeconds(60)->lessThanOrEqualTo(now());
    }

    private function refreshAccessToken(Integration $integration): array
    {
        $credentialsUrl = rtrim((string) $integration->url_credenciales, '/');
        if ($credentialsUrl === '') {
            throw new RuntimeException('No existe url_credenciales configurada para Salesforce.');
        }

        if (!$integration->username || !$integration->password) {
            throw new RuntimeException('Faltan credenciales basicas de Salesforce (username, password).');
        }

        $refreshResponse = Http::acceptJson()
            ->withBasicAuth((string) $integration->username, (string) $integration->password)
            ->post($credentialsUrl . '?grant_type=client_credentials');

        Log::info('SALESFORCE TOKEN RESPONSE', [
            'integration_id' => $integration->id,
            'status' => $refreshResponse->status(),
            'body' => $refreshResponse->body(),
        ]);

        if (!$refreshResponse->successful()) {
            throw new RuntimeException('No se pudo obtener token Salesforce: ' . $refreshResponse->body());
        }

        $data = $refreshResponse->json();
        $accessToken = trim((string) ($data['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new RuntimeException('Respuesta de Salesforce sin access_token. body: ' . $refreshResponse->body());
        }

        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : null;

        $updates = [
            'tokent' => $accessToken,
            'scope' => $data['scope'] ?? $integration->scope,
            'token_type' => $data['token_type'] ?? $integration->token_type,
            'expires_in' => $expiresIn,
            'token_expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
            'updated_at' => now(),
        ];

        $updatedRows = Integration::query()
            ->whereKey($integration->id)
            ->update($updates);

        if ($updatedRows === 0) {
            throw new RuntimeException('No fue posible actualizar token Salesforce en base de datos.');
        }

        $integration->forceFill($updates);

        return [
            'access_token' => $accessToken,
        ];
    }

    private function buildPayload(Lead $lead, Integration $integration): array
    {
        $template = trim((string) $integration->body);
        if ($template === '') {
            return [
                'ServiceInput' => [
                    'tipo' => 'Test Drive',
                    'tipoDocumento' => 'CC',
                    'documento' => '1055888555',
                    'nombres' => $this->firstNonEmpty($lead->name, 'Sin nombre'),
                    'apellidos' => $this->firstNonEmpty($lead->last_name, 'Sin apellido'),
                    'celular' => $lead->phone,
                ],
            ];
        }

        $replacements = [
            '{{name}}' => $this->firstNonEmpty($lead->name, 'Sin nombre'),
            '{{last_name}}' => $this->firstNonEmpty($lead->last_name, 'Sin apellido'),
            '{{phone}}' => $lead->phone,
            '{{email}}' => $lead->email,
            '{{document}}' => $this->firstNonEmpty($lead->reference, '1055888555'),
            '{{service}}' => $this->firstNonEmpty($lead->service, 'Test Drive'),
        ];

        $template = strtr($template, $replacements);
        $decoded = $this->decodeBodyTemplate($template);
        if (!is_array($decoded)) {
            throw new RuntimeException('El campo body de Salesforce debe ser un JSON valido.');
        }

        return $this->resolveBodyPlaceholders($decoded, $lead);
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
                $result[$key] = $this->replacePlaceholders($item, $replacements, $lead);
            }

            return $result;
        }

        if (is_string($value)) {
            if ($lead && preg_match('/^__salesforce_lead_field__:(.+)$/', $value, $matches)) {
                return data_get($lead, $matches[1]);
            }

            return strtr($value, $replacements);
        }

        return $value;
    }

    private function placeholderToken(string $field): string
    {
        return '__salesforce_lead_field__:' . $field;
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

    private function sendLeadRequest(string $url, array $payload, string $accessToken)
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($accessToken)
            ->post($url, $payload);
    }

    private function firstNonEmpty(...$values)
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
