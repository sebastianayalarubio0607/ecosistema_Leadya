<?php

namespace App\Http\Services\Integration;

use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadIntegration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Adds the new type without editing the existing IntegrationService or queued jobs.
 * Every pre-existing integration is delegated verbatim to the parent implementation.
 */
class FreshworksOpportunityAwareIntegrationService extends IntegrationService
{
    public function __construct(
        GoogleSheetsIntegrationService $googleSheets,
        KommoIntegrationService $kommo,
        KommoPipelineService $kommoPipeline,
        AtomIntegrationService $atom,
        LetyIntegrationService $lety,
        ZohoIntegrationService $zoho,
        FreshworksIntegrationService $freshworks,
        SalesforceIntegrationService $salesforce,
        MondayIntegrationService $monday,
        HubspotIntegrationService $hubspot,
        GohighlevelService $gohighlevel,
        ZapnitoInvitationIntegrationService $zapnito,
        private readonly FreshworksOpportunityIntegrationService $freshworksOpportunity,
    ) {
        parent::__construct(
            $googleSheets, $kommo, $kommoPipeline, $atom, $lety, $zoho, $freshworks,
            $salesforce, $monday, $hubspot, $gohighlevel, $zapnito
        );
    }

    protected function sendToIntegration(Lead $lead, Integration $integration, LeadIntegration $leadIntegration)
    {
        if (! $this->isFreshworksOpportunity($integration)) {
            return parent::sendToIntegration($lead, $integration, $leadIntegration);
        }

        try {
            $response = $this->freshworksOpportunity->sendToFreshworksOpportunity($lead, $integration);
            $leadIntegration->update([
                'status' => $response->successful() ? 'completed' : 'failed',
                'answer' => $response->body(),
                'answer_code' => $response->status(),
            ]);
        } catch (\Throwable $exception) {
            $leadIntegration->update([
                'status' => 'failed',
                'answer' => $exception->getMessage(),
                'answer_code' => 500,
            ]);

            Log::error('Error enviando Freshworks-Oportunidad', [
                'lead_id' => $lead->id,
                'integration_id' => $integration->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function isFreshworksOpportunity(Integration $integration): bool
    {
        $integration->loadMissing('integrationtype:id,name');
        $type = Str::of((string) optional($integration->integrationtype)->name)
            ->ascii()->lower()->replace([' ', '-'], '_')->replaceMatches('/_+/', '_')->trim('_')->toString();

        return $type === 'freshworks_oportunidad';
    }
}
