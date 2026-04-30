<?php

namespace App\Http\Controllers;

use App\Http\Services\Customer\CustomerService;
use App\Http\Services\Integration\IntegrationService;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Http\Services\Lead\LeadService;
use App\Jobs\ProcessLeadIntegrationsJob;
use App\Jobs\SendLeadToFacebook;
use App\Jobs\SendLeadToGoogleAds;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    public function store(Request $request, LeadFunnelHistoryService $historyService)
    {

        /**
         *  valida si Aplicar el ID de cliente desde el encabezado de la solicitud o si no existe el header X-Customer-ID coge el del formulario
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

        /**
         * Registra el cambio en el funnel del lead, si es que aplica. Esto se hace después de crear el lead para asegurar que cualquier cambio relevante en el estado del lead se capture correctamente en el historial del funnel. El servicio LeadFunnelHistoryService se encarga de determinar si hubo un cambio significativo en el funnel y registra esa información para su posterior análisis o visualización.
         */
        $historyService->recordIfFunnelChanged($lead);

        if ($this->isGoogleCampaignOrigin($lead->campaign_origin)) {
            try {
                SendLeadToGoogleAds::dispatch($lead->id);
            } catch (\Throwable $exception) {
                Log::warning('No fue posible despachar SendLeadToGoogleAds.', [
                    'lead_id' => $lead->id,
                    'campaign_origin' => $lead->campaign_origin,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        /*
        * Buscar integraciones activas del customer
        */
        $integrations = $this->integrationService->getActiveIntegrations($lead->customer_id);

        if (in_array($lead->campaign_origin, ['fb', 'meta', 'ig', 'wa', 'mg', 'th'], true)) {
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

        if (! $customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $lead = Lead::with(['customer', 'integration'])->find($id);

        if (! $lead) {
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

        if (! $customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $lead = Lead::find($id);

        if (! $lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }

        if ($customerId != 1 && $lead->customer_id != $customerId) {
            return response()->json(['message' => 'Unauthorized to update this Lead'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',

            // ✅ permitir crm_state (si este endpoint lo modifica)
            'crm_state' => 'sometimes|nullable|string|max:255',
        ]);

        $lead->fill($validated);
        $crmChanged = $lead->isDirty('crm_state');

        $lead->save();

        // ✅ solo si cambió crm_state, entra al servicio
        if ($crmChanged) {
            $historyService->recordIfFunnelChanged($lead);
        }

        return response()->json([
            'message' => 'Lead updated successfully',
            'data' => $lead,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $customerId = $request->header('X-Customer-ID');

        if (! $customerId) {
            return response()->json(['message' => 'Missing X-Customer-ID header'], 400);
        }

        $lead = Lead::find($id);

        if (! $lead) {
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

    private function isGoogleCampaignOrigin(?string $campaignOrigin): bool
    {
        $origin = mb_strtolower(trim((string) $campaignOrigin));

        return in_array($origin, ['google', 'gads', 'google_ads', 'google ads'], true);
    }
}
