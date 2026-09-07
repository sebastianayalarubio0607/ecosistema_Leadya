<?php

namespace App\Http\Services\Integration;

use App\Http\Services\Integration\Concerns\ResolvesIntegrationVariableMappings;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZapnitoInvitationIntegrationService
{
    use ResolvesIntegrationVariableMappings;

    private const DEFAULT_BASE_URL = 'https://comunidad.flar.com';
    private const INVITATIONS_PATH = '/api/v1/invitations';
    private const PLACEHOLDER_TOKEN_PREFIX = '__zapnito_lead_field__:';

    public function sendToZapnitoInvitation(Lead $lead, Integration $integration)
    {
        $url = $this->resolveUrl($integration);
        $token = trim((string) $integration->tokent);

        if ($token === '') {
            throw new RuntimeException('No existe token de autenticacion configurado para Zapnito invitacion.');
        }

        $payload = $this->buildPayloadFromTemplate((string) $integration->body, $lead, $integration);

        Log::info('ZAPNITO INVITATION URL', ['url' => $url]);
        Log::info('ZAPNITO INVITATION PAYLOAD JSON', [
            'json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = Http::asJson()
            ->withHeaders([
                'Authorization' => 'Token token=' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'charset' => 'utf-8',
            ])
            ->post($url, $payload);

        Log::info('ZAPNITO INVITATION RESPONSE', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response;
    }

    private function resolveUrl(Integration $integration): string
    {
        $url = rtrim(trim((string) $integration->url), '/');
        $url = $url !== '' ? $url : self::DEFAULT_BASE_URL;

        if (preg_match('#/api/v1/invitations/?$#', $url)) {
            return rtrim($url, '/');
        }

        return $url . self::INVITATIONS_PATH;
    }

    private function buildPayloadFromTemplate(string $template, Lead $lead, Integration $integration): array
    {
        $template = trim($template);

        if ($template === '') {
            throw new RuntimeException('El campo body de Zapnito invitacion debe estar configurado.');
        }

        $decoded = $this->decodeJsonTemplate($template);

        if (!is_array($decoded)) {
            throw new RuntimeException('El campo body de Zapnito invitacion debe ser un JSON valido.');
        }

        return $this->resolveLeadPlaceholders(
            $decoded,
            $lead,
            $integration,
            $this->integrationVariableMappings($integration)
        );
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

    private function resolveLeadPlaceholders(array $payload, Lead $lead, Integration $integration, $mappings): array
    {
        $resolved = [];

        foreach ($payload as $key => $value) {
            $resolved[$key] = $this->resolveLeadValue($value, $lead, $integration, $mappings, (string) $key);
        }

        return $resolved;
    }

    private function resolveLeadValue($value, Lead $lead, Integration $integration, $mappings, ?string $targetVariable = null)
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

        if (!preg_match('/^' . preg_quote(self::PLACEHOLDER_TOKEN_PREFIX, '/') . '(.+)$/', $value, $matches)) {
            return $value;
        }

        $leadField = $matches[1];
        $resolved = data_get($lead, $leadField);

        if ($resolved === null) {
            $leadField = $this->leadFieldAlias($leadField);
            $resolved = data_get($lead, $leadField, '');
        }

        return $this->resolveMappedIntegrationValue($mappings, $targetVariable, $leadField, $resolved, $resolved ?? '', 'ZAPNITO INVITATION');
    }

    private function placeholderToken(string $field): string
    {
        return self::PLACEHOLDER_TOKEN_PREFIX . $field;
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

    private function leadFieldAlias(string $field): string
    {
        return match ($field) {
            'firstName' => 'name',
            'lastName' => 'last_name',
            default => $field,
        };
    }
}
