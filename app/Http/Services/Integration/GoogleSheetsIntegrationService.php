<?php

namespace App\Http\Services\Integration;

use App\Models\Integration;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class GoogleSheetsIntegrationService
{
    public function sendToGoogleSheets(Lead $lead, Integration $integration)
    {
        $url = $integration->url;

        // Para poder leer el nombre del cliente
        $lead->loadMissing('customer');

        // 1) TODO el lead automáticamente (incluye campos nuevos)
        $payload = $lead->getAttributes();

        // 2) Asegurar fields_custom (si es array/objeto)
        $payload['fields_custom'] = $lead->fields_custom ?? [];

        // 3) Hoja = nombre del cliente
        $customerName = $lead->customer?->name ?? ('Customer_' . ($lead->customer_id ?? 'NA'));

        // Guardar el form_name original por si lo necesitas
        $payload['lead_form_name'] = $payload['form_name'] ?? null;

        // Apps Script usa "form_name" para el nombre de la hoja
        $payload['form_name'] = $customerName;

        // Campos auxiliares (si no existen ya)
        $payload['opening_hours'] = $payload['opening_hours'] ?? Carbon::now()->format('H:i:s');
        $payload['opening_date']  = $payload['opening_date']  ?? Carbon::now()->format('Y-m-d');

        // Normalizar para x-www-form-urlencoded
        $payload = $this->normalizeForForm($payload);

        return Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($url, $payload);
    }

    private function normalizeForForm(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_null($value)) {
                $payload[$key] = '';
            } elseif (is_bool($value)) {
                $payload[$key] = $value ? '1' : '0';
            } elseif (is_array($value) || is_object($value)) {
                $payload[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        return $payload;
    }
}
