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

        $customField = $this->parseCustomField($integration->custom_field, $lead, $integration);

        $contact = [
            'first_source' => $this->firstNonEmpty($lead->campaignOrigin?->name, 'Organico'),
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
                $lead->crm_id = $integration->crmIdPrefix() . '-' . $freshworksLeadId;
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

    private function parseCustomField(?string $customField, Lead $lead, Integration $integration): array
    {
        $customField = trim((string) $customField);
        if ($customField === '') {
            return [];
        }

        $decoded = $this->decodeCustomFieldTemplate($customField);
        if (!is_array($decoded)) {
            throw new RuntimeException('El campo custom_field de Freshworks debe ser un JSON valido.');
        }

        return $this->resolveCustomFieldPlaceholders(
            $decoded,
            $lead,
            $this->freshworksVariableMappings($integration)
        );
    }

    private function decodeCustomFieldTemplate(string $customField): ?array
    {
        $normalized = $this->replaceCustomFieldPlaceholdersWithTokens($customField);

        $decoded = json_decode($normalized, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function replaceCustomFieldPlaceholdersWithTokens(string $value): string
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

    private function resolveCustomFieldPlaceholders(array $payload, Lead $lead, $mappings, ?string $targetVariable = null): array
    {
        $resolved = [];

        foreach ($payload as $key => $value) {
            $resolvedValue = $this->resolveCustomFieldValue($value, $lead, $mappings, $targetVariable ?? (string) $key);

            if ($resolvedValue === null || $resolvedValue === '') {
                continue;
            }

            $resolved[$key] = $resolvedValue;
        }

        return $resolved;
    }

    private function resolveCustomFieldValue($value, Lead $lead, $mappings, ?string $targetVariable = null)
    {
        if (is_array($value)) {
            return $this->resolveCustomFieldPlaceholders($value, $lead, $mappings, $targetVariable);
        }

        if (!is_string($value)) {
            return $value;
        }

        if (!preg_match('/^__freshworks_lead_field__:(.+)$/', $value, $matches)) {
            return $value;
        }

        $leadField = $matches[1];
        $leadValue = data_get($lead, $leadField);

        return $this->resolveMappedCustomFieldValue($mappings, $targetVariable, $leadField, $leadValue);
    }

    private function freshworksVariableMappings(Integration $integration)
    {
        if ($integration->relationLoaded('freshworksVariableMappings')) {
            return $integration->freshworksVariableMappings
                ->where('active', true)
                ->sortBy(fn ($mapping) => sprintf('%010d-%010d', $mapping->order ?? 0, $mapping->id ?? 0))
                ->values();
        }

        return $integration->freshworksVariableMappings()
            ->where('active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    private function resolveMappedCustomFieldValue($mappings, ?string $targetVariable, string $leadField, $leadValue)
    {
        if ($targetVariable === null || $leadValue === null || $leadValue === '') {
            return $leadValue;
        }

        foreach ($mappings as $mapping) {
            if ((string) $mapping->target_variable !== (string) $targetVariable) {
                continue;
            }

            if ((string) $mapping->lead_field !== (string) $leadField) {
                continue;
            }

            if ((string) $mapping->expected_value !== (string) $leadValue) {
                continue;
            }

            if ($mapping->mapped_value === null || $mapping->mapped_value === '') {
                return $leadValue;
            }

            Log::info('FRESHWORKS VARIABLE MAPPING MATCHED', [
                'target_variable' => $targetVariable,
                'lead_field' => $leadField,
                'expected_value' => $mapping->expected_value,
            ]);

            return $mapping->mapped_value;
        }

        return $leadValue;
    }

    private function placeholderToken(string $field): string
    {
        return '__freshworks_lead_field__:' . $field;
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
