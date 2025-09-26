<?php
namespace App\Http\Services\Integration;
//namespace App\Http\Services\LetyIntegrationService;

/**
 * Servicio para manejar integraciones Google Sheets.
 */
use App\Models\integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;


/**
 * Servicio para manejar integraciones google sheets.
 */
class LetyIntegrationService
{
    
    /** Procesa la integración del lead según el tipo de integración.
     *
     * @param Lead $lead El lead a integrar.
     * @return void
     */
    public function sendToLety(Lead $lead, Integration $integration)
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
}