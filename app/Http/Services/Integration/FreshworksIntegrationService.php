<?php

namespace App\Http\Services\Integration;

use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FreshworksIntegrationService
{
    public function sendTofreshworks(Lead $lead, Integration $integration)
    {
        $url = rtrim((string) $integration->url, '/');
        if ($url === '') {
            throw new RuntimeException('No existe URL configurada para Freshworks.');
        }

        $token = trim((string) $integration->tokent);
        if ($token === '') {
            throw new RuntimeException('No existe token de autenticacion configurado para Freshworks.');
        }

        $customField = $this->parseCustomField($integration->custom_field);

        $contact = [
            'email' => $lead->email,
            'first_name' => $this->firstNonEmpty($lead->name, 'Sin nombre'),
            'last_name' => $this->firstNonEmpty($lead->last_name, 'Sin apellido'),
            'mobile_number' => $lead->phone,
            'territory_id' => $this->normalizeIntegerLike($integration->territory_id),
            'owner_id' => $this->normalizeIntegerLike($integration->owner_id),
            'City' => $this->firstNonEmpty($integration->city, $lead->city, 'Sin ciudad'),
            'lead_source_id' => $this->normalizeIntegerLike($integration->lead_source_id),
            'custom_field' => $customField,
        ];

        $payload = [
            'contact' => array_filter($contact, static fn ($value) => $value !== null && $value !== ''),
        ];

        Log::info('FRESHWORKS URL', ['url' => $url]);
        Log::info('FRESHWORKS PAYLOAD JSON', [
            'json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => 'Token token=' . $token,
            ])
            ->post($url, $payload);

        Log::info('FRESHWORKS RESPONSE', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->successful()) {
            $freshworksLeadId = $response->json('contact.id')
                ?? $response->json('id')
                ?? $response->json('contact.contact_id')
                ?? null;

            Log::info('FRESHWORKS CREATE RESULT', [
                'integration_id' => $integration->id,
                'local_lead_id' => $lead->id,
                'freshworks_lead_id' => $freshworksLeadId,
                'response_json' => $response->json(),
            ]);

            if ($freshworksLeadId !== null) {
                $lead->crm_id = $integration->id . '-' . $freshworksLeadId;
                $lead->save();

                Log::info('LEAD UPDATED crm_id', [
                    'local_lead_id' => $lead->id,
                    'crm_id' => $lead->crm_id,
                    'freshworks_lead_id' => $freshworksLeadId,
                ]);
            }
        }

        return $response;
    }

    private function parseCustomField(?string $customField): array
    {
        $customField = trim((string) $customField);
        if ($customField === '') {
            return [];
        }

        $decoded = json_decode($customField, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('El campo custom_field de Freshworks debe ser un JSON valido.');
        }

        return $decoded;
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
