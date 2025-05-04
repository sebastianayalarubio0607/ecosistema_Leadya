<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Integration;
use App\Models\LeadIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; 

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        if ($customerId == 1) {
            // Admin: devuelve todos los leads
            $leads = Lead::with(['customer', 'integration'])->get();
        } else {
            // Cliente normal: sólo sus propios leads
            $leads = Lead::where('customer_id', $customerId)
                ->with(['customer', 'integration'])
                ->get();
        }

        return response()->json($leads);
    }


    public function store(Request $request)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'last_Name' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'age' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'status' => 'nullable|boolean',
            'tc' => 'nullable|boolean',
            'fields_Custom' => 'nullable|array', // <<<<<< importante: JSON validado como array
            'agent' => 'nullable|string|max:255',
            'service_city' => 'nullable|string|max:255',
            'children' => 'nullable|string|max:255',
            'opening_hours' => 'nullable|string|max:255',
            'effective_lead' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'service' => 'nullable|string|max:255',
            'remote_ip' => 'nullable|string|max:10000', // mediumText = muy largo
            'page' => 'nullable|string|max:255',
            'page_url' => 'nullable|string|max:10000', // mediumText = muy largo
            'campaign_origin' => 'nullable|string|max:255',
            'customer_id' => 'required|exists:customers,id',
            'integration_id' => 'nullable|exists:integrations,id', // corregido para que valide bien como foreignId
            'message' => 'nullable|string|max:10000', // mediumText también
        ]);


        // Si NO es admin (customerId != 1), forzamos que el customer_id sea el del header
        if ($customerId != 1) {
            $validated['customer_id'] = $customerId;
        }

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
            $type = strtolower($integration->integrationType->name ?? 'webhook');

            $response = null;

            switch ($type) {

                case 'google_sheets':

                    $response = $this->sendToGoogleSheets($lead, $integration);
                    break;

                case 'kommo':

                    $response = $this->sendToKommo($lead, $integration);
                    break;

                case 'lety':

                    $response = $this->sendToLety($lead, $integration);
                    break;

                case 'atom':

                    $response = $this->sendToAtom($lead, $integration);
                    break;

                default:
                    Log::warning('Tipo de integración no soportado: ' . $type);
                    break;
            }

            $this->handleIntegrationResponse($response, $leadIntegration);
        } catch (\Exception $e) {
            $this->handleIntegrationError($e, $leadIntegration, $lead, $integration);
        }
    }

    private function sendToGoogleSheets(Lead $lead, Integration $integration)
    {

        $url =  $integration->url;
        return Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($url, [
            'name' => $lead->name,
            'last_Name' => $lead->last_Name,
            'position' => $lead->position,
            'city' => $lead->city,
            'age' => $lead->age,
            'company' => $lead->company,
            'country' => $lead->country,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'status' => $lead->status,
            'tc' => $lead->tc,
            'fields_Custom' => json_encode($lead->fields_Custom),
            'agent' => $lead->agent,
            'service_city' => $lead->service_city,
            'children' => $lead->children,
            'effective_lead' => $lead->effective_lead,
            'reference' => $lead->reference,
            'service' => $lead->service,
            'remote_ip' => $lead->remote_ip,
            'page' => $lead->page,
            'page_url' => $lead->page_url,
            'campaign_origin' => $lead->campaign_origin,
            'customer_id' => $lead->customer_id,
            'integration_id' => $lead->integration_id,
            'message' => $lead->message,
            'form_name' => $lead->form_name ?? 'DefaultForm',
            'opening_hours' => Carbon::now()->format('H:i:s'), 
            'opening_date' => Carbon::now()->format('Y-m-d'),  
        ]);
    }


    private function sendToLety(Lead $lead, Integration $integration)
    {

        $url =  $integration->url;
        return Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($url, [
            'name' => $lead->name,
            'last_Name' => $lead->last_Name,
            'position' => $lead->position,
            'city' => $lead->city,
            'age' => $lead->age,
            'company' => $lead->company,
            'country' => $lead->country,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'status' => $lead->status,
            'tc' => $lead->tc,
            'fields_Custom' => json_encode($lead->fields_Custom),
            'agent' => $lead->agent,
            'service_city' => $lead->service_city,
            'children' => $lead->children,
            'opening_hours' => $lead->opening_hours,
            'effective_lead' => $lead->effective_lead,
            'reference' => $lead->reference,
            'service' => $lead->service,
            'remote_ip' => $lead->remote_ip,
            'page' => $lead->page,
            'page_url' => $lead->page_url,
            'campaign_origin' => $lead->campaign_origin,
            'customer_id' => $lead->customer_id,
            'integration_id' => $lead->integration_id,
            'message' => $lead->message,
            'form_name' => $lead->form_name ?? 'DefaultForm',
            'opening_hours' => Carbon::now()->format('H:i:s'), // <-- Aquí va la hora actual
            'opening_date' => Carbon::now()->format('Y-m-d'),   // <-- Aquí va la fecha actual

        ]);
    }


    private function sendToKommo(Lead $lead, Integration $integration)
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

    private function handleIntegrationResponse($response, LeadIntegration $leadIntegration)
    {
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
    }

    private function handleIntegrationError(\Exception $e, LeadIntegration $leadIntegration, Lead $lead, Integration $integration)
    {
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



    public function show(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $lead = Lead::with(['customer', 'integration'])->find($id);

        if (!$lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }

        if ($customerId != 1 && $lead->customer_id != $customerId) {
            return response()->json(['message' => 'Unauthorized to view this Lead'], 403);
        }

        return response()->json($lead);
    }

    public function update(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $lead = Lead::find($id);

        if (!$lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }

        if ($customerId != 1 && $lead->customer_id != $customerId) {
            return response()->json(['message' => 'Unauthorized to update this Lead'], 403);
        }

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

    public function destroy(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        if (!$customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $lead = Lead::find($id);

        if (!$lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }

        if ($customerId != 1 && $lead->customer_id != $customerId) {
            return response()->json(['message' => 'Unauthorized to delete this Lead'], 403);
        }

        $lead->delete();

        return response()->json([
            'message' => 'Lead deleted successfully',
        ]);
    }
}
