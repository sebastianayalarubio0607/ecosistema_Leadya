<?php

namespace App\Http\Services\Lead;

use App\Models\Lead;

/**
 * Servicio para manejar la logica relacionada con los leads.
 */
class LeadService
{
    /**
     * Obtiene los leads filtrados por customer_id si no es admin (customer_id != 1).
     *
     * @param int $customerId El ID del cliente.
     * @return \Illuminate\Database\Eloquent\Collection Coleccion de leads.
     */
    public function getLeadsByCustomerId($customerId)
    {
        $query = Lead::with(['customer', 'integration']);

        if ($customerId != 1) {
            $query->where('customer_id', $customerId);
        }

        return $query->get();
    }

    /**
     * Valida los datos del lead.
     *
     * @param \Illuminate\Http\Request $request
     * @return array Datos validados.
     */
    public function validateLeadRequest($request)
    {
        return $request->validate([
            'name'            => 'required|string|max:255',
            'last_name'       => 'sometimes|nullable|string|max:255',
            'position'        => 'sometimes|nullable|string|max:255',
            'city'            => 'sometimes|nullable|string|max:255',
            'age'             => 'sometimes|nullable|string|max:255',
            'company'         => 'sometimes|nullable|string|max:255',
            'country'         => 'sometimes|nullable|string|max:255',
            'email'           => 'sometimes|nullable|email|max:255',
            'phone'           => 'required|string|max:255',
            'status'          => 'sometimes|nullable|boolean',
            'tc'              => 'sometimes|nullable|boolean',
            'fields_custom'   => 'sometimes|nullable|array',
            'service_city'    => 'sometimes|nullable|string|max:255',
            'children'        => 'sometimes|nullable|string|max:255',
            'opening_hours'   => 'sometimes|nullable|string|max:255',
            'effective_lead'  => 'sometimes|nullable|string|max:255',
            'reference'       => 'sometimes|nullable|string|max:255',
            'service'         => 'sometimes|nullable|string|max:255',
            'remote_ip'       => 'sometimes|nullable|string|max:10000',
            'page'            => 'sometimes|nullable|string|max:255',
            'page_url'        => 'sometimes|nullable|string|max:10000',
            'campaign_origin' => 'sometimes|nullable|string|max:255',
            'campaign_objective' => 'sometimes|nullable|integer',
            'message'         => 'sometimes|nullable|string|max:10000',
            'customer_id'     => 'required|integer|exists:customers,id',
            'fbp'             => 'sometimes|nullable|string|max:255',
            'fbc'             => 'sometimes|nullable|string|max:255',
            'plataforma'      => 'sometimes|nullable|string|max:255',
            'lenguaje'        => 'sometimes|nullable|string|max:255',
            'geo'             => 'sometimes|nullable|string|max:255',
            'agent'           => 'sometimes|nullable|string|max:10000',
            'meta_id_ad'      => 'sometimes|nullable|string|max:255',
            'g_ad'            => 'sometimes|nullable|string|max:255',
            'g_clid'          => 'sometimes|nullable|string|max:255',
            'gclid'           => 'sometimes|nullable|string|max:255',
            'gbraid'          => 'sometimes|nullable|string|max:255',
            'wbraid'          => 'sometimes|nullable|string|max:255',
            'gad_source'      => 'sometimes|nullable|string|max:255',
            'gad_campaignid'  => 'sometimes|nullable|string|max:255',
            'google_ad_id'    => 'sometimes|nullable|string|max:255',
            'google_adgroup_id' => 'sometimes|nullable|string|max:255',
            'google_campaign_id' => 'sometimes|nullable|string|max:255',
            'matchtype'       => 'sometimes|nullable|string|max:255',
            'device'          => 'sometimes|nullable|string|max:255',
            'value'           => 'sometimes|nullable|numeric',
            'crm_state'       => 'sometimes|nullable|string|max:255',
            

            'number_workers'  => 'sometimes|nullable|integer',
            'number_locations' => 'sometimes|nullable|integer',
            'campo_numero_1'  => 'sometimes|nullable|integer',
            'campo_numero_2'  => 'sometimes|nullable|integer',
            'campo_numero_3'  => 'sometimes|nullable|integer',
            'campo_numero_4'  => 'sometimes|nullable|integer',
            'campo_numero_5'  => 'sometimes|nullable|integer',

            'campo_text_1'    => 'sometimes|nullable|string',
            'campo_text_2'    => 'sometimes|nullable|string',
            'campo_text_3'    => 'sometimes|nullable|string',
            'campo_text_4'    => 'sometimes|nullable|string',
            'campo_text_5'    => 'sometimes|nullable|string',
            
        ]);
    }

    public function createLead(array $lead)
    {
        return Lead::create($lead);
    }
}
