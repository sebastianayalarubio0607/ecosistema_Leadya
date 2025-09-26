<?php

namespace App\Http\Services\Lead;

use App\Models\Lead;

/**
 * Servicio para manejar la lógica relacionada con los leads.
 */

class LeadService
{
    /**
     * Obtiene los leads filtrados por customer_id si no es admin (customer_id != 1).
     *
     * @param int $customerId El ID del cliente.
     * @return \Illuminate\Database\Eloquent\Collection Colección de leads.
     */
    public function getLeadsByCustomerId($customerId)
    {
        // Creamos la consulta básica
        $query = Lead::with(['customer', 'integration']);


        // Si no es admin (customerId != 1), aplicamos el filtro de customer_id
        if ($customerId != 1) {
            $query->where('customer_id', $customerId);
        }

        // Ejecutamos la consulta y obtenemos los resultados
        $leads = $query->get();

        return $leads;
    }

    /**
     * Valida los datos del lead.
     *
     * @param \Illuminate\Http\Request $request
     * @return array Datos validados.
     */
    public function validateLeadRequest($request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'last_Name'       => 'nullable|string|max:255',
            'position'        => 'nullable|string|max:255',
            'city'            => 'nullable|string|max:255',
            'age'             => 'nullable|string|max:255',
            'company'         => 'nullable|string|max:255',
            'country'         => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'required|string|max:255',
            'status'          => 'nullable|boolean',
            'tc'              => 'nullable|boolean',
            'fields_Custom'   => 'nullable|array',
            'agent'           => 'nullable|string|max:255',
            'service_city'    => 'nullable|string|max:255',
            'children'        => 'nullable|string|max:255',
            'opening_hours'   => 'nullable|string|max:255',
            'effective_lead'  => 'nullable|string|max:255',
            'reference'       => 'nullable|string|max:255',
            'service'         => 'nullable|string|max:255',
            'remote_ip'       => 'nullable|string|max:10000',
            'page'            => 'nullable|string|max:255',
            'page_url'        => 'nullable|string|max:10000',
            'campaign_origin' => 'nullable|string|max:255',
            'message'         => 'nullable|string|max:10000',
            'customer_id'    => 'required|integer|exists:customers,id',
            'fbp'             => 'nullable|string|max:255',
            'fbc'             => 'nullable|string|max:255',
        ]);

        return $validated;
    }

    // Si necesitas crear un lead, hazlo dentro de un método, por ejemplo:
    
    public function createLead(array $lead)
    {
        return Lead::create($lead);
    }


    

    
}
