<?php

namespace App\Http\Services\Integration;

use App\Http\Services\Integration\Concerns\ResolvesIntegrationVariableMappings;
use App\Models\AtomWebhook;
use App\Models\Integration;
use App\Models\Lead;
use App\Support\SensitiveValue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AtomIntegrationService
{
    use ResolvesIntegrationVariableMappings;

    public function sendToAtom(Lead $lead, Integration $integration): AtomDispatchResult
    {
        Log::info('ATOM SERVICE STARTED', [
            'lead_id' => $lead->id,
            'integration_id' => $integration->id,
            'token' => SensitiveValue::redact($integration->tokent),
        ]);

        $token = $this->resolveToken($integration);
        $payload = $this->buildPayloadFromTemplate((string) $integration->body, $lead, $integration);
        $webhooks = $this->resolveTargetWebhooks($lead, $integration);

        if ($webhooks->isEmpty()) {
            throw new RuntimeException('No existe webhook Atom compatible ni webhook por defecto configurado.');
        }

        $results = [];

        foreach ($webhooks as $webhook) {
            try {
                Log::info('ATOM WEBHOOK REQUEST', [
                    'lead_id' => $lead->id,
                    'integration_id' => $integration->id,
                    'atom_webhook_id' => $webhook->id,
                    'url' => $webhook->url,
                ]);

                $response = Http::acceptJson()
                    ->asJson()
                    ->withToken($token)
                    ->post($webhook->url, $payload);

                Log::info('ATOM WEBHOOK RESPONSE', [
                    'lead_id' => $lead->id,
                    'integration_id' => $integration->id,
                    'atom_webhook_id' => $webhook->id,
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 1000),
                ]);

                $results[] = [
                    'sent' => true,
                    'successful' => $response->successful(),
                    'status' => $response->status(),
                    'webhook_id' => $webhook->id,
                    'webhook_name' => $webhook->name,
                    'response' => Str::limit($response->body(), 1000),
                ];
            } catch (\Throwable $exception) {
                $this->logError($integration, $lead, 'Error enviando webhook Atom.', [
                    'atom_webhook_id' => $webhook->id,
                    'exception_class' => get_class($exception),
                    'exception_message' => $exception->getMessage(),
                ]);

                $results[] = [
                    'sent' => true,
                    'successful' => false,
                    'status' => 500,
                    'webhook_id' => $webhook->id,
                    'webhook_name' => $webhook->name,
                    'response' => $exception->getMessage(),
                ];
            }
        }

        return new AtomDispatchResult($results);
    }

    private function resolveToken(Integration $integration): string
    {
        $token = trim((string) $integration->tokent);

        if (stripos($token, 'bearer ') === 0) {
            $token = trim(substr($token, 7));
        }

        if ($token === '') {
            $this->logError($integration, null, 'No existe token configurado para Atom.');
            throw new RuntimeException('No existe token configurado para Atom.');
        }

        return $token;
    }

    private function resolveTargetWebhooks(Lead $lead, Integration $integration)
    {
        $conditions = $integration->atomConditions()
            ->where('active', true)
            ->with(['webhook' => fn ($query) => $query->where('active', true)])
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $webhooks = collect();

        foreach ($conditions as $condition) {
            if (!$condition->webhook) {
                continue;
            }

            $leadValue = data_get($lead, $condition->lead_field);

            if ((string) $leadValue !== (string) $condition->expected_value) {
                continue;
            }

            Log::info('ATOM CONDITION MATCHED', [
                'lead_id' => $lead->id,
                'integration_id' => $integration->id,
                'atom_condition_id' => $condition->id,
                'atom_webhook_id' => $condition->atom_webhook_id,
                'field' => $condition->lead_field,
                'expected_value' => $condition->expected_value,
            ]);

            $webhooks->push($condition->webhook);
        }

        $matchedWebhooks = $webhooks
            ->unique(fn (AtomWebhook $webhook) => $webhook->id)
            ->values();

        if ($matchedWebhooks->isNotEmpty()) {
            return $matchedWebhooks;
        }

        $defaultWebhook = $integration->atomWebhooks()
            ->where('active', true)
            ->where('is_default', true)
            ->orderBy('order')
            ->orderBy('id')
            ->first();

        if ($defaultWebhook) {
            Log::info('ATOM DEFAULT WEBHOOK USED', [
                'lead_id' => $lead->id,
                'integration_id' => $integration->id,
                'atom_webhook_id' => $defaultWebhook->id,
            ]);

            return collect([$defaultWebhook]);
        }

        $this->logError($integration, $lead, 'No hubo condiciones Atom compatibles ni webhook por defecto activo.');

        return collect();
    }

    private function buildPayloadFromTemplate(string $template, Lead $lead, Integration $integration): array
    {
        $template = trim($template);

        if ($template === '') {
            $this->logError(null, $lead, 'El payload JSON de Atom esta vacio.');
            throw new RuntimeException('El payload JSON de Atom esta vacio.');
        }

        if (!$this->containsOnlySupportedPlaceholders($template)) {
            $this->logError(null, $lead, 'El payload JSON de Atom contiene variables no soportadas.');
            throw new RuntimeException('El payload JSON de Atom contiene variables no soportadas.');
        }

        $decoded = json_decode($this->quoteUnquotedPlaceholders($template), true);

        if (!is_array($decoded)) {
            $this->logError(null, $lead, 'El payload JSON de Atom no es un JSON valido.', [
                'json_error' => json_last_error_msg(),
            ]);
            throw new RuntimeException('El payload JSON de Atom no es un JSON valido.');
        }

        $payload = $this->resolveValue($decoded, $lead, $integration, $this->integrationVariableMappings($integration));

        if (!is_array($payload) || json_encode($payload) === false) {
            $this->logError(null, $lead, 'El payload JSON de Atom quedo invalido despues de reemplazar variables.');
            throw new RuntimeException('El payload JSON de Atom quedo invalido despues de reemplazar variables.');
        }

        return $payload;
    }

    private function resolveValue($value, Lead $lead, Integration $integration, $mappings, ?string $targetVariable = null)
    {
        if (is_array($value)) {
            $resolved = [];

            foreach ($value as $key => $nestedValue) {
                $resolved[$key] = $this->resolveValue($nestedValue, $lead, $integration, $mappings, (string) $key);
            }

            return $resolved;
        }

        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if (preg_match('/^\{\{\s*\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}$/', $trimmed, $matches)) {
            $leadValue = data_get($lead, $matches[1], '');

            return $this->resolveMappedIntegrationValue($mappings, $targetVariable, $matches[1], $leadValue, $leadValue, 'ATOM');
        }

        return preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}/', function ($matches) use ($lead, $mappings, $targetVariable) {
            $expression = trim($matches[1]);

            if (preg_match('/^\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $expression, $leadMatches)) {
                $leadValue = data_get($lead, $leadMatches[1], '');

                return (string) $this->resolveMappedIntegrationValue($mappings, $targetVariable, $leadMatches[1], $leadValue, $leadValue, 'ATOM');
            }

            return $matches[0];
        }, $value);
    }

    private function containsOnlySupportedPlaceholders(string $template): bool
    {
        preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/', $template, $matches);

        foreach ($matches[1] ?? [] as $expression) {
            if (!preg_match('/^\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', trim($expression))) {
                return false;
            }
        }

        return true;
    }

    private function quoteUnquotedPlaceholders(string $template): string
    {
        $result = '';
        $length = strlen($template);
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $template[$i];

            if ($char === '"' && !$escaped) {
                $inString = !$inString;
                $result .= $char;
                continue;
            }

            if (!$inString && $char === '{' && ($template[$i + 1] ?? null) === '{') {
                $end = strpos($template, '}}', $i + 2);

                if ($end !== false) {
                    $expression = trim(substr($template, $i + 2, $end - ($i + 2)));
                    $result .= json_encode('{{' . $expression . '}}', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $i = $end + 1;
                    $escaped = false;
                    continue;
                }
            }

            $result .= $char;
            $escaped = $char === '\\' && !$escaped;

            if ($char !== '\\') {
                $escaped = false;
            }
        }

        return $result;
    }

    private function logError(?Integration $integration, ?Lead $lead, string $message, array $context = []): void
    {
        Log::error('ATOM ERROR', array_merge([
            'integration_id' => $integration?->id,
            'lead_id' => $lead?->id,
            'error' => $message,
        ], $context));
    }
}
