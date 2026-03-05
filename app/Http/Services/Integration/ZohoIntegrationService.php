<?php

namespace App\Http\Services\Integration;

use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class zohoIntegrationService
{
    public function sendTozoho(Lead $lead, Integration $integration)
    {
        // 1) OJO: NO devuelvas $lead aquí, porque corta el envío
        // return $lead;

        // 2) Normaliza URL: zoho "complex leads" suele ser /api/v4/leads/complex
        $url = rtrim((string) $integration->url, '/');

        // Si tu BD guarda solo el dominio o una ruta distinta, fuerza el endpoint correcto:
        // (Si ya viene completo, esto NO lo rompe)
        if (!str_contains($url, '/api/v4/leads/complex')) {
            $url .= '/api/v4/leads/complex';
        }

        // 3) Normaliza token: withToken agrega "Bearer ".
        // Si tu token en BD ya trae "Bearer ", lo limpiamos para que no quede "Bearer Bearer ..."
        $token = trim((string) $integration->tokent);
        if (stripos($token, 'bearer ') === 0) {
            $token = trim(substr($token, 7));
        }

        // 4) Construye payload como ARRAY RAÍZ (muy importante para /leads/complex)
        $payload = [
            [
                'name' => $lead->name . ' ' . $lead->last_name,
                '_embedded' => [
                    'contacts' => [
                        [
                            'name' =>$lead->name . ' ' . $lead->last_name,
                            'custom_fields_values' => [
                                [
                                    'field_id' => (int) $integration->crm_Id_phone,
                                    'values' => [
                                        ['value' => $lead->phone],
                                    ],
                                ],
                                [
                                    'field_id' => (int) $integration->crm_Id_email,
                                    'values' => [
                                        ['value' => $lead->email, 'enum_code' => 'WORK'],
                                    ],
                                ],
                                [
                                    'field_id' => (int) $integration->crm_Id_service,
                                    'values' => [
                                        ['value' => $lead->service],
                                    ],
                                ],
                                [
                                    'field_id' => (int) $integration->crm_Id_fuente,
                                    'values' => [
                                        ['value' => 'LeadsYa'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // 5) Logs para confirmar qué sale REALMENTE
        Log::info('zoho URL', ['url' => $url]);
        Log::info('zoho PAYLOAD JSON', [
            'json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        // 6) Envío
        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token) // Laravel pone Authorization: Bearer {token}
            ->post($url, $payload);

        Log::info('zoho RESPONSE', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

         // 6) Si éxito: extraer "id" del lead creado y actualizar crm_id en BD
        if ($response->successful()) {
            $json = $response->json();

            // Respuesta zoho típica:
            // [{"id":20742592,"contact_id":21822756,"company_id":null,"request_id":["0"],"merged":false}]
            $zohoLeadId = (isset($json[0]['id'])) ? (string) $json[0]['id'] : null;

            if ($zohoLeadId !== null) {
                $lead->crm_id = $integration->id . '-' . $zohoLeadId;
                $lead->save();

                Log::info('LEAD UPDATED crm_id', [
                    'local_lead_id' => $lead->id,
                    'crm_id' => $lead->crm_id,
                    'zoho_lead_id' => $zohoLeadId,
                ]);
            } else {
                Log::warning('zoho OK pero sin "id" de lead en respuesta', [
                    'local_lead_id' => $lead->id,
                    'json' => $json,
                ]);
            }
        }

        return $response;
    }

    /**
     * Crea una estructura de custom field para zoho,
     * o devuelve null si field_id no existe o no hay values reales.
     */
    private function customField($fieldId, array $values): ?array
    {
        $id = (int) $fieldId;
        if ($id <= 0) {
            return null;
        }

        // Evita mandar valores vacíos
        $hasAnyValue = false;
        foreach ($values as $v) {
            if (isset($v['value']) && $v['value'] !== null && $v['value'] !== '') {
                $hasAnyValue = true;
                break;
            }
        }
        if (!$hasAnyValue) {
            return null;
        }

        return [
            'field_id' => $id,
            'values' => $values,
        ];
    }

    /**
     * Devuelve el primer valor no vacío.
     */
    private function firstNonEmpty(...$values)
    {
        foreach ($values as $v) {
            if ($v !== null && $v !== '') {
                return $v;
            }
        }
        return null;
    }

       
}

