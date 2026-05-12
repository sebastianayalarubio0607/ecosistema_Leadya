<?php

namespace App\Http\Services\Integration;

use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GohighlevelService
{
    private const DEFAULT_CONTACTS_UPSERT_URL = 'https://services.leadconnectorhq.com/contacts/upsert';

    public function sendToGohighlevel(Lead $lead, Integration $integration)
    {
        $url = $this->resolveUrl($integration);

        $token = trim((string) $integration->tokent);
        if ($token === '') {
            throw new RuntimeException('No existe token de autenticacion configurado para GoHighLevel.');
        }

        $payload = $this->buildPayloadFromTemplate((string) $integration->body, $lead);

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
            $gohighlevelLeadId = $response->json('contact.id')
                ?? $response->json('id')
                ?? $response->json('contact.contact_id')
                ?? $response->json('contactId')
                ?? null;

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

    private function resolveUrl(Integration $integration): string
    {
        $url = rtrim((string) $integration->url, '/');

        return $url !== '' ? $url : self::DEFAULT_CONTACTS_UPSERT_URL;
    }

    private function buildPayloadFromTemplate(string $template, $lead): array
    {
        $template = trim($template);
        if ($template === '') {
            throw new RuntimeException('El campo body de GoHighLevel debe estar configurado.');
        }

        $decoded = $this->decodeJsonTemplate($template);
        if (!is_array($decoded)) {
            throw new RuntimeException('El campo body de GoHighLevel debe ser un JSON valido.');
        }

        return $this->resolveLeadPlaceholders($decoded, $lead);
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

    private function resolveLeadPlaceholders(array $payload, $lead): array
    {
        $resolved = [];

        foreach ($payload as $key => $value) {
            $resolved[$key] = $this->resolveLeadValue($value, $lead);
        }

        return $resolved;
    }

    private function resolveLeadValue($value, $lead)
    {
        if (is_array($value)) {
            $resolved = [];

            foreach ($value as $key => $nestedValue) {
                $resolved[$key] = $this->resolveLeadValue($nestedValue, $lead);
            }

            return $resolved;
        }

        if (!is_string($value)) {
            return $value;
        }

        if (!preg_match('/^__gohighlevel_lead_field__:(.+)$/', $value, $matches)) {
            return $value;
        }

        $resolved = data_get($lead, $matches[1]);

        if ($resolved === null) {
            $resolved = data_get($lead, $this->leadFieldAlias($matches[1]), '');
        }

        return $resolved ?? '';
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
