<?php

namespace App\Http\Controllers;


use App\Models\Lead;
use Illuminate\Http\Request;

use App\Http\Services\Customer\CustomerService;
use App\Http\Services\Lead\LeadService;
use App\Http\Services\Integration\IntegrationService;
use App\Jobs\ProcessLeadIntegrationsJob;
use App\Jobs\SendLeadToFacebook;


class LeadController extends Controller
{
    /**
     * Servicios inyectados
     */
    private $customerService;
    private $leadsService;
    private $integrationService;

    /**
     * Constructor para inyectar los servicios necesarios.
     */
    public function __construct(
        /**
         * Inyección de dependencias de los servicios
         */
        CustomerService $customerService,
        LeadService $leadsService,
        IntegrationService $integrationService
    ) {
        /**
         * Asignar los servicios inyectados a las propiedades de la clase
         */
        $this->customerService = $customerService;
        $this->leadsService = $leadsService;
        $this->integrationService = $integrationService;
    }

    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        /**
         *  valida si Aplicar el ID de cliente desde el encabezado de la solicitud o si no existe el header X-Customer-ID coge el del formulario
         */
        $customerId = $this->customerService->applyCustomerIdFromHeader($request);

        /*
        * Extrae el customer_id del request (ya sea del header o del body del formulario)
        */
        $customerId = $customerId->customer_id;

        /**
         * Obtiene los leads filtrados por customer_id si no es admin (customer_id != 1).
         */
        $leads = $this->leadsService->getLeadsByCustomerId($customerId);

        return response()->json($leads);
    }


    public function store(Request $request)
    {


        /**
         *  valida si Aplicar el ID de cliente desde el encabezado de la solicitud o si no existe el header X-Customer-ID coge el del formulario 
         * 
         */
        $request = $this->customerService->applyCustomerIdFromHeader($request);


        /**
         * Valida los datos del lead.
         */
        $lead = $this->leadsService->validateLeadRequest($request);

        /*
        * Crea el lead en la base de datos
        */
        $lead = $this->leadsService->createLead($lead);


        /*
        * Buscar integraciones activas del customer
        */
        $integrations = $this->integrationService->getActiveIntegrations($lead->customer_id);





        if ($lead->campaign_origin == 'fb') {

            // Dispara el job en cola (no bloquea la respuesta HTTP)
           SendLeadToFacebook::dispatch($lead->id, $lead->customer_id);
        }


        /**
         * Procesa las integraciones para el lead y devuelve las integraciones procesadas.
         */

        ProcessLeadIntegrationsJob::dispatch($lead, $integrations);



        return response()->json([
            'message' => 'Lead creado con éxito',
            'data' => $lead,
            // 'integrations' => $integrations,

        ], 201);
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
