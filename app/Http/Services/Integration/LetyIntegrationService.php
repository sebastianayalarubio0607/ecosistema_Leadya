<?php

namespace App\Http\Services\Integration;

use App\Http\Services\Integration\Concerns\ResolvesIntegrationVariableMappings;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LetyWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class LetyIntegrationService
{
    use ResolvesIntegrationVariableMappings;

    public function sendToLety(Lead $lead, Integration $integration): LetyDispatchResult
    {
        Log::info('LETY SERVICE STARTED', [
            'lead_id' => $lead->id,
            'integration_id' => $integration->id,
        ]);

        $webhooks = $this->resolveMatchingWebhooks($lead, $integration);

        if ($webhooks->isEmpty()) {
            Log::info('LETY NO CONDITIONS MATCHED', [
                'lead_id' => $lead->id,
                'integration_id' => $integration->id,
            ]);

            return new LetyDispatchResult([
                [
                    'sent' => false,
                    'successful' => true,
                    'status' => 204,
                    'message' => 'No hubo condiciones Lety compatibles para este lead.',
                ],
            ]);
        }

        $results = [];

        foreach ($webhooks as $webhook) {
            try {
                $payload = $this->buildPayloadFromTemplate((string) $webhook->body, $lead, $integration);

                Log::info('LETY WEBHOOK REQUEST', [
                    'lead_id' => $lead->id,
                    'integration_id' => $integration->id,
                    'lety_webhook_id' => $webhook->id,
                    'url' => $webhook->url,
                ]);

                $response = Http::acceptJson()
                    ->asForm()
                    ->post($webhook->url, $payload);

                Log::info('LETY WEBHOOK RESPONSE', [
                    'lead_id' => $lead->id,
                    'integration_id' => $integration->id,
                    'lety_webhook_id' => $webhook->id,
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
                $this->logError($integration, $lead, 'Error enviando webhook Lety.', [
                    'lety_webhook_id' => $webhook->id,
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

        return new LetyDispatchResult($results);
    }

    private function resolveMatchingWebhooks(Lead $lead, Integration $integration)
    {
        $conditions = $integration->letyConditions()
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

            Log::info('LETY CONDITION MATCHED', [
                'lead_id' => $lead->id,
                'integration_id' => $integration->id,
                'lety_condition_id' => $condition->id,
                'lety_webhook_id' => $condition->lety_webhook_id,
                'field' => $condition->lead_field,
                'expected_value' => $condition->expected_value,
            ]);

            $webhooks->push($condition->webhook);
        }

        return $webhooks
            ->unique(fn (LetyWebhook $webhook) => $webhook->id)
            ->values();
    }

    public function buildPayloadFromTemplate(string $template, Lead $lead, ?Integration $integration = null): array
    {
        $template = trim($template);

        if ($template === '') {
            $this->logError(null, $lead, 'El payload form-urlencoded de Lety esta vacio.');
            throw new RuntimeException('El payload form-urlencoded de Lety esta vacio.');
        }

        if (!$this->containsOnlySupportedPlaceholders($template)) {
            $this->logError(null, $lead, 'El payload form-urlencoded de Lety contiene variables no soportadas.');
            throw new RuntimeException('El payload form-urlencoded de Lety contiene variables no soportadas.');
        }

        $payload = [];
        $pairs = preg_split('/(?:\r\n|\r|\n|&)+/', $template) ?: [];

        foreach ($pairs as $pair) {
            $pair = trim($pair);

            if ($pair === '') {
                continue;
            }

            if (!str_contains($pair, '=')) {
                throw new RuntimeException('Cada linea del payload Lety debe tener formato campo=valor.');
            }

            [$key, $value] = explode('=', $pair, 2);
            $key = trim(rawurldecode($key));

            if (!$this->isValidPayloadKey($key)) {
                throw new RuntimeException("El campo [{$key}] del payload Lety no es valido.");
            }

            $payload[$key] = $this->resolveTemplateValue(
                rawurldecode(trim($value)),
                $lead,
                $integration,
                $integration ? $this->integrationVariableMappings($integration) : collect(),
                $key
            );
        }

        if ($payload === []) {
            throw new RuntimeException('El payload form-urlencoded de Lety no tiene campos validos.');
        }

        return $payload;
    }

    private function resolveTemplateValue(string $value, Lead $lead, ?Integration $integration, $mappings, string $targetVariable): string
    {
        return preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}/', function ($matches) use ($lead, $integration, $mappings, $targetVariable) {
            $expression = trim($matches[1]);

            if (preg_match('/^\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $expression, $leadMatches)) {
                $value = data_get($lead, $leadMatches[1], '');

                if (is_array($value) || is_object($value)) {
                    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                }

                if ($integration) {
                    $value = $this->resolveMappedIntegrationValue($mappings, $targetVariable, $leadMatches[1], $value, $value, 'LETY');
                }

                return (string) $value;
            }

            return $matches[0];
        }, $value) ?? '';
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

    public function isValidTemplate(string $template): bool
    {
        try {
            $this->buildPayloadFromTemplate($template, new Lead());
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    private function isValidPayloadKey(string $key): bool
    {
        return preg_match('/^[A-Za-z0-9_.\-\[\]]+$/', $key) === 1;
    }

    private function logError(?Integration $integration, ?Lead $lead, string $message, array $context = []): void
    {
        Log::error('LETY ERROR', array_merge([
            'integration_id' => $integration?->id,
            'lead_id' => $lead?->id,
            'error' => $message,
        ], $context));
    }
}
