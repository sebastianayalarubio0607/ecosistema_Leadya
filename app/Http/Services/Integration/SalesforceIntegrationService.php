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
        $oauth = $this->refreshAccessToken($integration);

        $url = rtrim((string) $integration->url, '/');
        if ($url === '') {
            throw new RuntimeException('No existe url configurada para Salesforce.');
        }

        $payload = $this->buildPayload($lead, $integration);

        Log::info('SALESFORCE URL', ['url' => $url]);
        Log::info('SALESFORCE PAYLOAD JSON', [
            'json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($oauth['access_token'])
            ->post($url, $payload);

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

        $decoded = json_decode($template, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('El campo body de Salesforce debe ser un JSON valido.');
        }

        $replacements = [
            '{{name}}' => $this->firstNonEmpty($lead->name, 'Sin nombre'),
            '{{last_name}}' => $this->firstNonEmpty($lead->last_name, 'Sin apellido'),
            '{{phone}}' => $lead->phone,
            '{{email}}' => $lead->email,
            '{{document}}' => $this->firstNonEmpty($lead->reference, '1055888555'),
            '{{service}}' => $this->firstNonEmpty($lead->service, 'Test Drive'),
        ];

        return $this->replacePlaceholders($decoded, $replacements);
    }

    private function replacePlaceholders($value, array $replacements)
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->replacePlaceholders($item, $replacements);
            }

            return $result;
        }

        if (is_string($value)) {
            return strtr($value, $replacements);
        }

        return $value;
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
