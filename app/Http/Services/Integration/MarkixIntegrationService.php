<?php

namespace App\Http\Services\Integration;

use App\Http\Services\Integration\Concerns\ResolvesIntegrationVariableMappings;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Sends a configured lead payload to Markix without changing other CRM flows. */
class MarkixIntegrationService
{
    use ResolvesIntegrationVariableMappings;

    private const PLACEHOLDER_PREFIX = '__markix_placeholder__:';

    public function sendToMarkix(Lead $lead, Integration $integration): Response
    {
        $url = trim((string) $integration->url);
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('La URL de Markix debe ser un endpoint HTTPS válido.');
        }

        $token = trim((string) $integration->tokent);
        if ($token === '') {
            throw new RuntimeException('No existe un token de Markix configurado.');
        }
        $token = preg_replace('/^Bearer\s+/i', '', $token);

        $payload = $this->payloadFromTemplate((string) $integration->body, $lead, $integration);

        Log::info('[markix] Enviando lead', [
            'integration_id' => $integration->id,
            'lead_id' => $lead->id,
        ]);

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->connectTimeout(5)
            ->timeout(20)
            ->post($url, $payload);

        if (! $response->successful()) {
            Log::warning('[markix] Markix rechazó el lead', [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
                'status' => $response->status(),
            ]);

            return $response;
        }

        $markixId = $response->json('markix_id');
        if (! is_scalar($markixId) || trim((string) $markixId) === '') {
            throw new RuntimeException('Markix aceptó la solicitud pero no devolvió markix_id.');
        }

        $lead->crm_id = $integration->crmIdPrefix().'-'.trim((string) $markixId);
        $lead->save();

        Log::info('[markix] ID CRM guardado', [
            'integration_id' => $integration->id,
            'lead_id' => $lead->id,
        ]);

        return $response;
    }

    private function payloadFromTemplate(string $template, Lead $lead, Integration $integration): array
    {
        $template = trim($template);
        if ($template === '') {
            throw new RuntimeException('El Body JSON de Markix debe estar configurado.');
        }

        $template = preg_replace_callback('/"\s*\{\{\s*([^}]+?)\s*\}\}\s*"/', fn ($matches) => $this->placeholderJsonToken($matches[1]), $template);
        $template = preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}/', fn ($matches) => $this->placeholderJsonToken($matches[1]), $template);
        $decoded = json_decode($template, true);
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('El Body JSON de Markix debe ser un objeto JSON válido.');
        }

        return $this->resolvePayloadValue($decoded, $lead, $integration);
    }

    private function placeholderJsonToken(string $expression): string
    {
        return json_encode(self::PLACEHOLDER_PREFIX.base64_encode(trim($expression)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function resolvePayloadValue($value, Lead $lead, Integration $integration, ?string $targetVariable = null)
    {
        if (is_array($value)) {
            $resolved = [];
            foreach ($value as $key => $item) {
                $resolved[$key] = $this->resolvePayloadValue($item, $lead, $integration, (string) $key);
            }
            return $resolved;
        }

        if (! is_string($value) || ! str_starts_with($value, self::PLACEHOLDER_PREFIX)) {
            return $value;
        }

        $encodedExpression = substr($value, strlen(self::PLACEHOLDER_PREFIX));
        $expression = base64_decode($encodedExpression, true);
        if ($expression === false) {
            return '';
        }

        $leadPath = $this->normalizeIntegrationLeadPlaceholderPath($expression);
        if ($leadPath !== null) {
            $leadValue = data_get($lead, $leadPath);
            if ($leadValue === null) {
                $leadValue = data_get($lead, $this->leadFieldAliasForIntegrationVariable($leadPath), '');
            }

            return $this->resolveMappedIntegrationValue(
                $this->integrationVariableMappings($integration),
                $targetVariable,
                $leadPath,
                $leadValue,
                $leadValue ?? '',
                'MARKIX'
            );
        }

        return $this->renderIntegrationVariablePlaceholder($expression, $lead, $integration, [], 'MARKIX') ?? '';
    }
}
