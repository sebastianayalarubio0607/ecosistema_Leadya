<?php

namespace App\Http\Services\Integration;

use App\Http\Services\Lead\LeadAdSourceClassifier;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Jobs\SendLeadToFacebook;
use App\Jobs\SendLeadToGoogleAds;
use App\Models\GohighlevelOpportunitySyncLog;
use App\Models\GohighlevelOpportunitySyncRun;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\CrmState;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GohighlevelOpportunityPollingService
{
    public function __construct(private readonly GohighlevelService $gohighlevel) {}

    public function sync(GohighlevelOpportunitySyncRun $run): void
    {
        $run->loadMissing('integration.integrationtype');
        $integration = $run->integration;
        if (! $integration) {
            throw new RuntimeException('La integración de GoHighLevel ya no existe.');
        }

        if (! $this->gohighlevel->supportsOpportunity($integration)) {
            throw new RuntimeException('La integración seleccionada no es GoHighLevel-Oportunidad.');
        }

        if ($integration->gohighlevel_return_mode !== 'api') {
            throw new RuntimeException('La consulta API solo está activa para integraciones configuradas en modo API.');
        }

        $locationId = trim((string) $integration->location_id);
        if ($locationId === '') {
            throw new RuntimeException('Configura locationId antes de consultar oportunidades.');
        }

        $prefix = $integration->crmIdPrefix();
        $stageToCrmState = $this->stageToCrmStateMap($prefix);
        if ($stageToCrmState === []) {
            throw new RuntimeException('No hay crm_states de esta integración para consultar. Sincroniza primero las etapas de GoHighLevel.');
        }

        $pipelines = $this->gohighlevel->getPipelines($integration, $locationId);
        $opportunitiesChecked = 0;
        $leadsUpdated = 0;
        $leadsNotFound = 0;

        foreach ($pipelines as $pipeline) {
            $pipelineId = trim((string) ($pipeline['id'] ?? ''));
            if ($pipelineId === '') {
                continue;
            }

            $matchingStageIds = collect($pipeline['stages'] ?? [])
                ->filter(fn ($stage) => is_array($stage) && isset($stage['id']) && isset($stageToCrmState[(string) $stage['id']]))
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
            if ($matchingStageIds === []) {
                continue;
            }

            // Fetch each relevant pipeline once, then select only opportunities
            // whose stage maps to a local CrmState for this integration.
            foreach ($this->gohighlevel->iterateOpportunitiesForPipeline($integration, $locationId, $pipelineId) as $opportunity) {
                $stageId = trim((string) ($opportunity['pipelineStageId'] ?? ''));
                $opportunityId = trim((string) ($opportunity['id'] ?? ''));
                if ($opportunityId === '' || ! in_array($stageId, $matchingStageIds, true)) {
                    continue;
                }

                $opportunitiesChecked++;
                $newCrmState = $stageToCrmState[$stageId] ?? null;
                if ($newCrmState === null) {
                    continue;
                }

                $leads = $this->findLeadsForOpportunity($integration, $prefix, $opportunityId);

                if ($leads->isEmpty()) {
                    GohighlevelOpportunitySyncLog::query()->create([
                        'sync_run_id' => $run->id,
                        'integration_id' => $integration->id,
                        'opportunity_id' => $opportunityId,
                        'crm_state_id' => $newCrmState,
                        'new_crm_state' => $newCrmState,
                        'pipeline_id' => $pipelineId,
                        'stage_id' => $stageId,
                        'state_status' => 'not_found',
                        'message' => 'No se encontró Lead por crm_id_oportunidad.',
                    ]);
                    $leadsNotFound++;
                    continue;
                }

                foreach ($leads as $lead) {
                    $valueChanged = $this->updateLeadValue($lead, $opportunity['monetaryValue'] ?? null);
                    $stateChanged = (string) $lead->crm_state !== $newCrmState;
                    if (! $stateChanged && ! $valueChanged) {
                        continue;
                    }

                    $syncLog = GohighlevelOpportunitySyncLog::query()->create([
                        'sync_run_id' => $run->id,
                        'integration_id' => $integration->id,
                        'lead_id' => $lead->id,
                        'opportunity_id' => $opportunityId,
                        'crm_state_id' => $newCrmState,
                        'previous_crm_state' => $lead->crm_state,
                        'new_crm_state' => $newCrmState,
                        'pipeline_id' => $pipelineId,
                        'stage_id' => $stageId,
                        'state_status' => $stateChanged ? 'updated' : 'unchanged',
                        'message' => $stateChanged ? null : 'Se actualizó el valor; el estado ya estaba sincronizado.',
                    ]);

                    if ($stateChanged) {
                        $this->applyLeadStateChange($lead, $newCrmState, $syncLog, app(LeadFunnelHistoryService::class));
                        $leadsUpdated++;
                    }
                }
            }
        }

        $run->forceFill([
            'status' => 'completed',
            'opportunities_checked' => $opportunitiesChecked,
            'leads_updated' => $leadsUpdated,
            'leads_not_found' => $leadsNotFound,
            'completed_at' => now(),
        ])->save();
    }

    private function stageToCrmStateMap(string $prefix): array
    {
        $escapedPrefix = addcslashes($prefix, '\\%_');
        $states = CrmState::query()->where('id', 'like', $escapedPrefix.'-%')->get(['id']);
        $map = [];

        foreach ($states as $state) {
            $id = (string) $state->id;
            $stageId = substr($id, strlen($prefix) + 1);
            if ($stageId !== '') {
                $map[$stageId] = $id;
            }
        }

        return $map;
    }

    private function findLeadsForOpportunity(Integration $integration, string $prefix, string $opportunityId)
    {
        $query = Lead::query()
            ->where('crm_id_oportunidad', $prefix.'-'.$opportunityId);

        if ($integration->customer_id) {
            $query->where('customer_id', $integration->customer_id);
        }

        $leads = $query->get();
        if ($leads->isNotEmpty() || ! $integration->customer_id) {
            return $leads;
        }

        // Recover leads whose opportunity was stored before the current
        // integration prefix was applied, or without a prefix. This lookup is
        // scoped to the integration's customer to avoid crossing tenants.
        return Lead::query()
            ->where('customer_id', $integration->customer_id)
            ->where(function ($query) use ($opportunityId) {
                $query->where('crm_id_oportunidad', $opportunityId)
                    ->orWhere('crm_id_oportunidad', 'like', '%-'.$opportunityId);
            })
            ->get();
    }

    private function updateLeadValue(Lead $lead, mixed $incomingValue): bool
    {
        if (! is_numeric($incomingValue)) {
            return false;
        }

        $normalizedValue = number_format((float) $incomingValue, 2, '.', '');
        if ((string) $lead->value === $normalizedValue) {
            return false;
        }

        $lead->value = $normalizedValue;
        $lead->save();

        return true;
    }

    private function applyLeadStateChange(
        Lead $lead,
        string $newCrmState,
        GohighlevelOpportunitySyncLog $syncLog,
        LeadFunnelHistoryService $historyService
    ): void {
        $lead->crm_state = $newCrmState;
        $lead->save();
        $historyService->recordIfFunnelChanged($lead);

        // Keep this new polling path aligned with the CRM-state controller's
        // existing conversion eligibility rules without changing its webhook.
        $adSource = LeadAdSourceClassifier::classify($lead);
        if ($adSource['is_meta_ads']) {
            $lead->unsetRelation('crmState');
            $lead->load('crmState.metaEvent', 'crmState.whatsappEvent', 'customer');
            $isWhatsappDataset = (string) $lead->campaign_origin === 'whatsapp'
                && (bool) $lead->customer?->Meta_whatsapp_dataset;
            $hasConversionEvent = $isWhatsappDataset
                ? (! empty($lead->crmState?->whatsapp_event_id) || ! empty($lead->crmState?->meta_event_id))
                : ! empty($lead->crmState?->meta_event_id);

            if (! $hasConversionEvent) {
                return;
            }

            $syncLog->forceFill([
                'conversion_channel' => $isWhatsappDataset && ! empty($lead->crmState?->whatsapp_event_id) ? 'whatsapp' : 'meta',
                'facebook_conversion_status' => 'pending',
                'conversion_requested_at' => now(),
            ])->save();

            try {
                SendLeadToFacebook::dispatch($lead->id, $lead->customer_id, null, (int) $syncLog->id);
            } catch (\Throwable $exception) {
                $syncLog->forceFill(['facebook_conversion_status' => 'failed', 'message' => $exception->getMessage()])->save();
                Log::warning('No fue posible despachar SendLeadToFacebook desde polling GoHighLevel', [
                    'lead_id' => $lead->id,
                    'crm_state' => $newCrmState,
                    'message' => $exception->getMessage(),
                ]);
            }
        } elseif ($adSource['is_google_ads']) {
            $syncLog->forceFill([
                'conversion_channel' => 'google_ads',
                'google_ads_conversion_status' => 'pending',
                'conversion_requested_at' => now(),
            ])->save();

            try {
                SendLeadToGoogleAds::dispatch($lead->id, $newCrmState, (int) $syncLog->id);
            } catch (\Throwable $exception) {
                $syncLog->forceFill(['google_ads_conversion_status' => 'failed', 'message' => $exception->getMessage()])->save();
                Log::warning('No fue posible despachar SendLeadToGoogleAds desde polling GoHighLevel', [
                    'lead_id' => $lead->id,
                    'crm_state' => $newCrmState,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
