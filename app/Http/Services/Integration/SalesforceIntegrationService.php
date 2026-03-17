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

        $apiDomain = rtrim((string) ($oauth['api_domain'] ?? $integration->api_domain), '/');
        if ($apiDomain === '') {
            throw new RuntimeException('No existe api_domain configurado para Salesforce.');
        }

        $url = $apiDomain . '/crm/v8/Leads/upsert';
        $source = ($lead->campaign_origin && in_array($lead->campaign_origin, ['fb', 'meta', 'ig', 'wa', 'mg', 'th']))
            ? 'Facebook'
            : 'Google Ads';

        $leadData = [
            'Last_Name' => $this->firstNonEmpty($lead->last_name, 'Sin apellido'),
            'First_Name' => $this->firstNonEmpty($lead->name, 'Sin nombre'),
            'Company' => $this->firstNonEmpty($lead->company, 'Particular'),
            'Email' => $lead->email,
            'Phone' => $lead->phone,
            'Mobile' => $lead->phone,
            'Assignment_Rule_ID' => '4516191000001033003',
            'Description' => $this->firstNonEmpty($lead->message, 'sin comentarios'),
            'Lead_Status' => 'Sin gestion',
            'Lead_Source' => $this->firstNonEmpty($source, 'Formulario Clientes Potenciales Facebook.'),
        ];

        $numberWorkers = $this->firstInteger($lead->number_workers ?? null);
        if ($numberWorkers !== null) {
            $leadData['No_of_Employees'] = $numberWorkers;
        }

        $numberLocations = $this->firstInteger(
            $lead->number_locations ?? null,
            $lead->umber_locations ?? null
        );
        if ($numberLocations !== null) {
            $leadData['Cantidad_de_Sedes'] = $numberLocations;
        }

        $payload = [
            'data' => [$leadData],
            'duplicate_check_fields' => ['Email'],
        ];

        Log::info('ZOHO URL', ['url' => $url]);
        Log::info('ZOHO PAYLOAD JSON', [
            'json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $oauth['access_token'],
            ])
            ->post($url, $payload);

        Log::info('ZOHO RESPONSE', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->successful()) {
            $item = $response->json('data.0', []);
            $zohoLeadId = $item['details']['id'] ?? $item['id'] ?? null;

            if ($zohoLeadId !== null) {
                $lead->crm_id = $integration->id . '-' . $zohoLeadId;
                $lead->save();

                Log::info('LEAD UPDATED crm_id', [
                    'local_lead_id' => $lead->id,
                    'crm_id' => $lead->crm_id,
                    'zoho_lead_id' => $zohoLeadId,
                ]);
            }
        }

        return $response;
    }

    private function refreshAccessToken(Integration $integration): array
    {
        $accountsUrl = rtrim((string) $integration->url, '/');
        if ($accountsUrl === '') {
            throw new RuntimeException('No existe accounts_url configurado para Zoho (integrations.url).');
        }

        if (!$integration->client_id || !$integration->client_secret || !$integration->refresh_token) {
            throw new RuntimeException('Faltan credenciales OAuth de Zoho (client_id, client_secret, refresh_token).');
        }

        $query = [
            'grant_type' => 'refresh_token',
            'client_id' => trim((string) $integration->client_id),
            'client_secret' => trim((string) $integration->client_secret),
            'refresh_token' => trim((string) $integration->refresh_token),
        ];

        $refreshResponse = Http::acceptJson()->post(
            $accountsUrl . '/oauth/v2/token?' . http_build_query($query)
        );

        Log::info('ZOHO REFRESH RESPONSE', [
            'integration_id' => $integration->id,
            'status' => $refreshResponse->status(),
            'body' => $refreshResponse->body(),
        ]);

        if (!$refreshResponse->successful()) {
            throw new RuntimeException('No se pudo refrescar token Zoho: ' . $refreshResponse->body());
        }

        $data = $refreshResponse->json();
        $accessToken = trim((string) ($data['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new RuntimeException('Respuesta de Zoho sin access_token. body: ' . $refreshResponse->body());
        }

        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : null;

        $updates = [
            'tokent' => $accessToken,
            'api_domain' => $data['api_domain'] ?? $integration->api_domain,
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
            throw new RuntimeException('No fue posible actualizar token Zoho en base de datos.');
        }

        $integration->forceFill($updates);

        Log::info('ZOHO TOKEN UPDATED IN DB', [
            'integration_id' => $integration->id,
            'api_domain' => $updates['api_domain'],
            'expires_in' => $updates['expires_in'],
            'token_expires_at' => (string) $updates['token_expires_at'],
        ]);

        return [
            'access_token' => $accessToken,
            'api_domain' => $updates['api_domain'],
        ];
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

    private function firstInteger(...$values): ?int
    {
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
                return (int) $value;
            }
        }

        return null;
    }
}
