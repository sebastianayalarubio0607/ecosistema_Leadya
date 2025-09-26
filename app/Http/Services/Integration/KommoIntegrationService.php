<?php



namespace App\Http\Services\Integration;
/**
 * Servicio para manejar integraciones Google Sheets.
 */

use App\Models\integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;



/**
 * Servicio para manejar integraciones google sheets.
 */
class KommoIntegrationService
{

    /** Procesa la integración del lead según el tipo de integración.
     *
     * @param Lead $lead El lead a integrar.
     * @return void
     */
    public function sendToKommo(Lead $lead, Integration $integration)
    {


        return Http::withHeaders([
            'authorization' => $integration->tokent,
            'content-Type' => 'application/json',
        ])->post($integration->url, [
            [
                '_embedded' => [
                    'name' => $lead->name ?? 'No Name',
                    'responsible_user_id' => 9849371,
                    'contacts' => [
                        [
                            'name' => $lead->name ?? 'No Name',
                            'responsible_user_id' => 9849371,
                            'custom_fields_values' => [
                                [
                                    'field_id' => 650078,
                                    'values' => [
                                        ['value' => $lead->city ?? 'No city'],
                                    ],
                                ],
                                [
                                    'field_id' => 650080,
                                    'values' => [
                                        ['value' => $lead->phone ?? null, 'enum_code' => 'WORK'],
                                    ],
                                ],
                                [
                                    'field_id' => 1418887,
                                    'values' => [
                                        ['value' => 'Rejuvenecimiento Vaginal y/o Monalisa Touch'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        ]);
    }
}
