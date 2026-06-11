<?php

namespace App\Http\Services\Integration;

use App\Models\Integration;
use App\Models\Lead;
use App\Support\SensitiveValue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KommoPipelineService
{
    public function sendToKommoPipeline(Lead $lead, Integration $integration)
    {
        Log::info('KOMMO PIPELINE SERVICE STARTED', [
            'lead_id' => $lead->id,
            'integration_id' => $integration->id,
            'url' => $integration->url,
            'token' => SensitiveValue::redact($integration->tokent),
        ]);

        $url = $this->resolveUrl($integration);
        $token = $this->resolveToken($integration);
        $target = $this->resolveTarget($lead, $integration);
        $payload = $this->buildPayloadFromTemplate((string) $integration->body, $lead, $target['pipeline_id'], $target['status_id']);

        Log::info('KOMMO PIPELINE URL', [
            'lead_id' => $lead->id,
            'url' => $url,
        ]);

        Log::info('KOMMO PIPELINE PAYLOAD', [
            'lead_id' => $lead->id,
            'payload' => $payload,
        ]);

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->post($url, $payload);

        Log::info('KOMMO PIPELINE RESPONSE', [
            'lead_id' => $lead->id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->successful()) {
            $kommoLeadId = data_get($response->json(), '0.id');

            if ($kommoLeadId !== null) {
                $lead->crm_id = $integration->crmIdPrefix() . '-' . $kommoLeadId;
                $lead->save();

                Log::info('KOMMO PIPELINE LEAD UPDATED crm_id', [
                    'lead_id' => $lead->id,
                    'crm_id' => $lead->crm_id,
                    'kommo_lead_id' => $kommoLeadId,
                ]);
            }
        }

        return $response;
    }

    private function resolveUrl(Integration $integration): string
    {
        $url = rtrim((string) $integration->url, '/');

        if ($url === '') {
            $this->logError($integration, null, 'No existe URL configurada para KommoPipeline.');
            throw new RuntimeException('No existe URL configurada para KommoPipeline.');
        }

        if (!str_contains($url, '/api/v4/leads/complex')) {
            $url .= '/api/v4/leads/complex';
        }

        return $url;
    }

    private function resolveToken(Integration $integration): string
    {
        $token = trim((string) $integration->tokent);

        if (stripos($token, 'bearer ') === 0) {
            $token = trim(substr($token, 7));
        }

        if ($token === '') {
            $this->logError($integration, null, 'No existe token configurado para KommoPipeline.');
            throw new RuntimeException('No existe token configurado para KommoPipeline.');
        }

        return $token;
    }

    private function resolveTarget(Lead $lead, Integration $integration): array
    {
        $conditions = $integration->kommoPipelineConditions()
            ->where('active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        foreach ($conditions as $condition) {
            $leadValue = data_get($lead, $condition->lead_field);

            if ((string) $leadValue !== (string) $condition->expected_value) {
                continue;
            }

            Log::info('KOMMO PIPELINE CONDITION MATCHED', [
                'lead_id' => $lead->id,
                'field' => $condition->lead_field,
                'expected_value' => $condition->expected_value,
                'pipeline_id' => $condition->pipeline_id,
                'status_id' => $condition->status_id,
            ]);

            return [
                'pipeline_id' => $condition->pipeline_id,
                'status_id' => $condition->status_id,
            ];
        }

        if (filled($integration->kommo_pipeline_default_pipeline_id) && filled($integration->kommo_pipeline_default_status_id)) {
            Log::info('KOMMO PIPELINE DEFAULT TARGET USED', [
                'lead_id' => $lead->id,
                'pipeline_id' => $integration->kommo_pipeline_default_pipeline_id,
                'status_id' => $integration->kommo_pipeline_default_status_id,
            ]);

            return [
                'pipeline_id' => $integration->kommo_pipeline_default_pipeline_id,
                'status_id' => $integration->kommo_pipeline_default_status_id,
            ];
        }

        $message = 'No se encontro condicionalidad KommoPipeline compatible ni pipeline/status por defecto.';
        $this->logError($integration, $lead, $message);

        throw new RuntimeException($message);
    }

    private function buildPayloadFromTemplate(string $template, Lead $lead, string $pipelineId, string $statusId): array
    {
        $template = trim($template);

        if ($template === '') {
            $this->logError(null, $lead, 'El payload JSON de KommoPipeline esta vacio.');
            throw new RuntimeException('El payload JSON de KommoPipeline esta vacio.');
        }

        if (!$this->containsOnlySupportedPlaceholders($template)) {
            $this->logError(null, $lead, 'El payload JSON de KommoPipeline contiene variables no soportadas.');
            throw new RuntimeException('El payload JSON de KommoPipeline contiene variables no soportadas.');
        }

        $normalized = $this->quoteUnquotedPlaceholders($template);
        $decoded = json_decode($normalized, true);

        if (!is_array($decoded)) {
            $this->logError(null, $lead, 'El payload JSON de KommoPipeline no es un JSON valido.', [
                'json_error' => json_last_error_msg(),
            ]);
            throw new RuntimeException('El payload JSON de KommoPipeline no es un JSON valido.');
        }

        $payload = $this->resolveValue($decoded, $lead, $pipelineId, $statusId);

        if (!is_array($payload) || json_encode($payload) === false) {
            $this->logError(null, $lead, 'El payload JSON de KommoPipeline quedo invalido despues de reemplazar variables.');
            throw new RuntimeException('El payload JSON de KommoPipeline quedo invalido despues de reemplazar variables.');
        }

        return $payload;
    }

    private function resolveValue($value, Lead $lead, string $pipelineId, string $statusId)
    {
        if (is_array($value)) {
            $resolved = [];

            foreach ($value as $key => $nestedValue) {
                $resolved[$key] = $this->resolveValue($nestedValue, $lead, $pipelineId, $statusId);
            }

            return $resolved;
        }

        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '{{pipeline_id}}') {
            return $this->normalizeIntegerLike($pipelineId);
        }

        if ($trimmed === '{{status_id}}') {
            return $this->normalizeIntegerLike($statusId);
        }

        if (preg_match('/^\{\{\s*\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}$/', $trimmed, $matches)) {
            return data_get($lead, $matches[1], '');
        }

        return preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}/', function ($matches) use ($lead, $pipelineId, $statusId) {
            $expression = trim($matches[1]);

            if ($expression === 'pipeline_id') {
                return (string) $pipelineId;
            }

            if ($expression === 'status_id') {
                return (string) $statusId;
            }

            if (preg_match('/^\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $expression, $leadMatches)) {
                return (string) data_get($lead, $leadMatches[1], '');
            }

            return $matches[0];
        }, $value);
    }

    private function containsOnlySupportedPlaceholders(string $template): bool
    {
        preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/', $template, $matches);

        foreach ($matches[1] ?? [] as $expression) {
            $expression = trim($expression);

            if (in_array($expression, ['pipeline_id', 'status_id'], true)) {
                continue;
            }

            if (preg_match('/^\$?lead\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $expression)) {
                continue;
            }

            return false;
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

    private function logError(?Integration $integration, ?Lead $lead, string $message, array $context = []): void
    {
        Log::error('KOMMO PIPELINE ERROR', array_merge([
            'integration_id' => $integration?->id,
            'lead_id' => $lead?->id,
            'error' => $message,
        ], $context));
    }
}
