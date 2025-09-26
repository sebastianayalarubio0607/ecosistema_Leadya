<?php

namespace App\Http\Services\Integration;

use App\Models\LeadIntegration;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use App\Http\Services\Integration\GoogleSheetsIntegrationService;
use App\Http\Services\Integration\KommoIntegrationService;
use App\Http\Services\Integration\LetyIntegrationService;

/**
 * Servicio para manejar la lógica relacionada con los integrations.
 */
class IntegrationService
{
    private $GooglesheetsIntegrationService;
    private $KommoIntegrationService;
    private $LetyIntegrationService;

    public function __construct(
        GoogleSheetsIntegrationService $GooglesheetsIntegrationService,
        KommoIntegrationService $KommoIntegrationService,
        LetyIntegrationService $LetyIntegrationService
    ) {
        $this->GooglesheetsIntegrationService = $GooglesheetsIntegrationService;
        $this->KommoIntegrationService = $KommoIntegrationService;
        $this->LetyIntegrationService = $LetyIntegrationService;
    }

    /**
     * Buscar integraciones activas del customer.
     */
    public function getActiveIntegrations($customer_id)
    {
        return Integration::where('customer_id', $customer_id)
            ->where('status', 1)
            ->with(['integrationType' => function ($query) {
                // Aquí puedes agregar filtros adicionales si lo necesitas
            }])
            ->get();
    }

    /**
     * Procesar integraciones para un lead.
     */
    public function processLeadIntegrations($lead, $integrations)
    {
        $prueba = null;

        foreach ($integrations as $integration) {
            $leadIntegration = LeadIntegration::create([
                'lead_id'        => $lead->id,
                'integration_id' => $integration->id,
                'status'         => 'pending',
            ]);

            $this->sendToIntegration($lead, $integration, $leadIntegration);
        }

        return $prueba;
    }

    /**
     * Enviar lead a una integración específica.
     */
    protected function sendToIntegration(Lead $lead, Integration $integration, LeadIntegration $leadIntegration)
    {
        try {
            $type = strtolower($integration->integrationType->name ?? 'webhook');

            // Mapa de integraciones y sus manejadores
            $handlers = [
                'google_sheets' => fn() => $this->GooglesheetsIntegrationService->sendToGoogleSheets($lead, $integration),
                'kommo'         => fn() => $this->KommoIntegrationService->sendToKommo($lead, $integration),
                'lety'          => fn() => $this->LetyIntegrationService->sendToLety($lead, $integration),
            ];

            $response = $handlers[$type]() ?? null;

            if (!$response) {
                Log::warning("Tipo de integración no soportado: {$type}");
            }

            $this->handleIntegrationResponse($response, $leadIntegration);
        } catch (\Exception $e) {
            $this->handleIntegrationError($e, $leadIntegration, $lead, $integration);
        }
    }

    /**
     * Maneja la respuesta de la integración y actualiza el estado del LeadIntegration.
     */
    private function handleIntegrationResponse($response, LeadIntegration $leadIntegration)
    {
        $success = $response && $response->successful();

        $leadIntegration->update([
            'status'      => $success ? 'completed' : 'failed',
            'answer'      => $response ? $response->body() : 'Unknown error',
            'answer_code' => $response ? $response->status() : 500,
        ]);
    }

    /**
     * Maneja errores durante el proceso de integración y registra logs detallados.
     */
    private function handleIntegrationError(\Exception $e, LeadIntegration $leadIntegration, Lead $lead, Integration $integration)
    {
        $leadIntegration->update([
            'status'      => 'failed',
            'answer'      => $e->getMessage(),
            'answer_code' => 500,
        ]);

        Log::error('Error enviando integración', [
            'lead_id'          => $leadIntegration->lead_id,
            'integration_id'   => $leadIntegration->integration_id,
            'customer_id'      => $lead->customer_id,
            'integration_url'  => $integration->url,
            'integration_type' => $integration->integrationType->name ?? 'desconocido',
            'error_message'    => $e->getMessage(),
            'exception_class'  => get_class($e),
            'timestamp'        => now()->toDateTimeString(),
        ]);
    }
}
