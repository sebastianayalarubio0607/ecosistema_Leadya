<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Integration;
use App\Models\LeadIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeadController extends Controller
{
    public function index()
    {
        $leads = Lead::with(['customer', 'integration'])->get();
        return response()->json($leads);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'customer_id' => 'required|exists:customers,id',
            // otros campos opcionales...
        ]);

        // Crear el lead
        $lead = Lead::create($validated);

        // Buscar integraciones activas del customer
        $integrations = Integration::where('customer_id', $lead->customer_id)
            ->where('status', 1)
            ->with('integrationType')
            ->get();
        
        foreach ($integrations as $integration) {
            // Crear registro en lead_integrations
            $leadIntegration = LeadIntegration::create([
                'lead_id' => $lead->id,
                'integration_id' => $integration->id,
                'status' => 'pending',
            ]);

            // Enviar lead a integración
            $this->sendToIntegration($lead, $integration, $leadIntegration);
        }

        return response()->json([
            'message' => 'Lead created and integrations sent successfully',
            'data' => $lead,
        ], 201);
    }

    protected function sendToIntegration(Lead $lead, Integration $integration, LeadIntegration $leadIntegration)
    {
        try {
            $url = $integration->url;
            $apiKey = $integration->tokent;
            $type = strtolower($integration->integrationType->name ?? 'webhook');

            switch ($type) {
                case 'google_sheets':
                    $response = Http::post($url, [
                        'Name' => $lead->name,
                        'Email' => $lead->email,
                        'Created_At' => $lead->created_at,
                        'Customer_ID' => $lead->customer_id,
                    ]);
                    break;

                case 'kommo':
                    $response = Http::withHeaders([
                        'authorization' => $apiKey,
                        'content-Type' => 'application/json',
                        ])->post($url , [
                            [
                                '_embedded' => [
                                    'name' => $lead->name ?? 'No Name',
                                    'responsible_user_id'=> 9849371,
                                    /*'tags' => [
                                        ['id' => 9233, 'name' => 'Despigmentación o Blanqueamiento Vulvar'],
                                        ['id' => 12772, 'name' => 'Otra ciudad'],
                                    ],*/
                                    'contacts' => [
                                        [
                                            'name' => $lead->name ?? 'No Name',
                                            'responsible_user_id' => 9849371,
                                            'custom_fields_values' => [
                                                [
                                                    'field_id' => 650078,
                                                    'values' => [
                                                        ['value' => $lead->city ?? 'bogota']
                                                    ],
                                                ],
                                                [
                                                    'field_id' => 650080,
                                                    'values' => [
                                                        ['value' => $lead->phone ?? '3000000425', 'enum_id' => 462824, 'enum_code' => 'WORK']
                                                    ],
                                                ],
                                                [
                                                    'field_id' => 1418887,
                                                    'values' => [
                                                        ['value' => 'Rejuvenecimiento Vaginal y/o Monalisa Touch'/*, 'enum_id' => 1022271*/]
                                                    ],
                                                ],
                                            ],
                                        ]
                                    ],
                                ],
                            ]
                         
                     ]);

                    break;

                default:
                    $response = null;
                    break;
            }

            if ($response && $response->successful()) {
                $leadIntegration->update([
                    'status' => 'completed',
                    'answer' => $response->body(),
                    'answer_code' => $response->status(),
                ]);
            } else {
                $leadIntegration->update([
                    'status' => 'failed',
                    'answer' => $response ? $response->body() : 'Unknown error',
                    'answer_code' => $response ? $response->status() : '500',
                ]);
            }

        } catch (\Exception $e) {
            $leadIntegration->update([
                'status' => 'failed',
                'answer' => $e->getMessage(),
                'answer_code' => '500',
            ]);

            Log::error('Error enviando integración', [
                'lead_id' => $leadIntegration->lead_id,
                'integration_id' => $leadIntegration->integration_id,
                'customer_id' => $lead->customer_id,
                'integration_url' => $integration->url,
                'integration_type' => $integration->integrationType->name ?? 'desconocido',
                'error_message' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString(),
            ]);
        }
    }

    public function show($id)
    {
        $lead = Lead::with(['customer', 'integration'])->findOrFail($id);
        return response()->json($lead);
    }

    public function update(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            // otros campos opcionales...
        ]);

        $lead->update($validated);

        return response()->json([
            'message' => 'Lead updated successfully',
            'data' => $lead,
        ]);
    }

    public function destroy($id)
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return response()->json([
            'message' => 'Lead deleted successfully',
        ]);
    }
}
