<?php

namespace App\Http\Services\Integration;

use App\Http\Services\Integration\FreshworksIntegrationService;
use App\Http\Services\Integration\GohighlevelService;
use App\Http\Services\Integration\GoogleSheetsIntegrationService;
use App\Http\Services\Integration\KommoIntegrationService;
use App\Http\Services\Integration\LetyIntegrationService;
use App\Http\Services\Integration\SalesforceIntegrationService;
use App\Http\Services\Integration\ZohoIntegrationService;
use App\Http\Services\Integration\MondayIntegrationService;
use App\Http\Services\Integration\HubspotIntegrationService;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadIntegration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class IntegrationService
{
    private $GooglesheetsIntegrationService;
    private $KommoIntegrationService;
    private $LetyIntegrationService;
    private $ZohoIntegrationService;
    private $FreshworksIntegrationService;
    private $SalesforceIntegrationService;
    private $MondayIntegrationService;
    private $HubspotIntegrationService;
    private $GohighlevelService;

    public function __construct(
        GoogleSheetsIntegrationService $GooglesheetsIntegrationService,
        KommoIntegrationService $KommoIntegrationService,
        LetyIntegrationService $LetyIntegrationService,
        ZohoIntegrationService $ZohoIntegrationService,
        FreshworksIntegrationService $FreshworksIntegrationService,
        SalesforceIntegrationService $SalesforceIntegrationService,
        MondayIntegrationService $MondayIntegrationService,
        HubspotIntegrationService $HubspotIntegrationService,
        GohighlevelService $GohighlevelService
    ) {
        $this->GooglesheetsIntegrationService = $GooglesheetsIntegrationService;
        $this->KommoIntegrationService = $KommoIntegrationService;
        $this->LetyIntegrationService = $LetyIntegrationService;
        $this->ZohoIntegrationService = $ZohoIntegrationService;
        $this->FreshworksIntegrationService = $FreshworksIntegrationService;
        $this->SalesforceIntegrationService = $SalesforceIntegrationService;
        $this->MondayIntegrationService = $MondayIntegrationService;
        $this->HubspotIntegrationService = $HubspotIntegrationService;
        $this->GohighlevelService = $GohighlevelService;
    }

    public function getActiveIntegrations($customer_id)
    {
        return Integration::where('customer_id', $customer_id)
            ->where('status', 1)
            ->with(['integrationtype' => function ($query) {
            }])
            ->get();
    }

    public function processLeadIntegrations($lead, $integrations)
    {
        $prueba = null;

        foreach ($integrations as $integration) {
            $leadIntegration = LeadIntegration::create([
                'lead_id' => $lead->id,
                'integration_id' => $integration->id,
                'status' => 'pending',
            ]);

            $this->sendToIntegration($lead, $integration, $leadIntegration);
        }

        return $prueba;
    }

    protected function sendToIntegration(Lead $lead, Integration $integration, LeadIntegration $leadIntegration)
    {
        try {
            $rawType = (string) data_get($integration, 'integrationtype.name', 'webhook');
            $type = $this->normalizeIntegrationType($rawType);

            $handlers = [
                'google_sheets' => fn() => $this->GooglesheetsIntegrationService->sendToGoogleSheets($lead, $integration),
                'kommo' => fn() => $this->KommoIntegrationService->sendToKommo($lead, $integration),
                'lety' => fn() => $this->LetyIntegrationService->sendToLety($lead, $integration),
                'zoho' => fn() => $this->ZohoIntegrationService->sendToZoho($lead, $integration),
                'freshworks' => fn() => $this->FreshworksIntegrationService->sendToFreshworks($lead, $integration),
                'salesforce' => fn() => $this->SalesforceIntegrationService->sendToSalesforce($lead, $integration),
                'monday' => fn() => $this->MondayIntegrationService->sendToMonday($lead, $integration),
                'hubspot' => fn() => $this->HubspotIntegrationService->sendToHubspot($lead, $integration),
                'gohighlevel' => fn() => $this->GohighlevelService->sendToGohighlevel($lead, $integration),
            ];

            $serviceMap = [
                'google_sheets' => $this->GooglesheetsIntegrationService::class,
                'kommo' => $this->KommoIntegrationService::class,
                'lety' => $this->LetyIntegrationService::class,
                'zoho' => $this->ZohoIntegrationService::class,
                'freshworks' => $this->FreshworksIntegrationService::class,
                'salesforce' => $this->SalesforceIntegrationService::class,
                'monday' => $this->MondayIntegrationService::class,
                'hubspot' => $this->HubspotIntegrationService::class,
                'gohighlevel' => $this->GohighlevelService::class,
            ];

            $handler = $handlers[$type] ?? null;

            Log::info('INTEGRATION HANDLER RESOLUTION', [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
                'raw_type' => $rawType,
                'normalized_type' => $type,
                'available_types' => array_keys($handlers),
                'resolved_service' => $serviceMap[$type] ?? null,
                'config_snapshot' => $this->integrationConfigSnapshot($type, $integration),
            ]);

            if (!is_callable($handler)) {
                throw new RuntimeException(
                    'No existe handler para el tipo de integracion [' . $rawType . '] normalizado como [' . $type . ']. Tipos disponibles: ' . implode(', ', array_keys($handlers))
                );
            }

            $response = $handler();

            if (!$response) {
                Log::warning("Tipo de integracion no soportado: {$type}");
            }

            $this->handleIntegrationResponse($response, $leadIntegration);
        } catch (\Exception $e) {
            $this->handleIntegrationError($e, $leadIntegration, $lead, $integration);
        }
    }

    private function handleIntegrationResponse($response, LeadIntegration $leadIntegration)
    {
        $success = $response && $response->successful();

        $leadIntegration->update([
            'status' => $success ? 'completed' : 'failed',
            'answer' => $response ? $response->body() : 'Unknown error',
            'answer_code' => $response ? $response->status() : 500,
        ]);
    }

    private function handleIntegrationError(\Exception $e, LeadIntegration $leadIntegration, Lead $lead, Integration $integration)
    {
        $leadIntegration->update([
            'status' => 'failed',
            'answer' => $e->getMessage(),
            'answer_code' => 500,
        ]);

        Log::error('Error enviando integracion', [
            'lead_id' => $leadIntegration->lead_id,
            'integration_id' => $leadIntegration->integration_id,
            'customer_id' => $lead->customer_id,
            'integration_url' => $integration->url,
            'integration_type' => $integration->integrationtype->name ?? 'desconocido',
            'error_message' => $e->getMessage(),
            'exception_class' => get_class($e),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    private function normalizeIntegrationType(?string $type): string
    {
        $normalized = Str::of((string) $type)
            ->ascii()
            ->lower()
            ->replace([' ', '-'], '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();

        return match ($normalized) {
            'googlesheets' => 'google_sheets',
            'go_high_level', 'leadconnector', 'lead_connector' => 'gohighlevel',
            default => $normalized !== '' ? $normalized : 'webhook',
        };
    }

    private function integrationConfigSnapshot(string $type, Integration $integration): array
    {
        return match ($type) {
            'monday' => [
                'integration_url' => $integration->url,
                'integration_url_present' => filled($integration->url),
                'token_present' => filled($integration->tokent),
                'config_base_url' => data_get(config('monday', []), 'base_url'),
                'config_api_version' => data_get(config('monday', []), 'api_version'),
            ],
            'zoho' => [
                'integration_url' => $integration->url,
                'url_present' => filled($integration->url),
                'api_domain' => $integration->api_domain,
                'api_domain_present' => filled($integration->api_domain),
                'client_id_present' => filled($integration->client_id),
                'refresh_token_present' => filled($integration->refresh_token),
                'token_present' => filled($integration->tokent),
            ],
            'kommo' => [
                'integration_url' => $integration->url,
                'url_present' => filled($integration->url),
                'token_present' => filled($integration->tokent),
                'crm_id_phone_present' => filled($integration->crm_Id_phone),
                'crm_id_email_present' => filled($integration->crm_Id_email),
                'crm_id_service_present' => filled($integration->crm_Id_service),
                'crm_id_fuente_present' => filled($integration->crm_Id_fuente),
            ],
            'hubspot' => [
                'integration_url' => $integration->url,
                'url_present' => filled($integration->url),
                'search_url_present' => filled($integration->url_consulta_lead),
                'deal_url_present' => filled($integration->url_negocio),
                'create_lead_url_present' => filled($integration->url_creacionlead),
                'token_present' => filled($integration->tokent),
                'dealname_present' => filled($integration->dealname),
                'dealstage_present' => filled($integration->dealstage),
                'body_present' => filled($integration->body),
            ],
            'gohighlevel' => [
                'integration_url' => $integration->url,
                'url_present' => filled($integration->url),
                'token_present' => filled($integration->tokent),
                'body_present' => filled($integration->body),
            ],
            default => [
                'integration_url' => $integration->url,
                'url_present' => filled($integration->url),
                'token_present' => filled($integration->tokent),
            ],
        };
    }
}
