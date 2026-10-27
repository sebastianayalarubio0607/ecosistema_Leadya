<?php

namespace App\Http\Services\Integration;

use App\Http\Services\Lead\LeadCrmStateProgressionService;
use App\Models\CrmState;
use App\Models\HubspotOpportunitySyncLog;
use App\Models\HubspotOpportunitySyncRun;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HubspotOpportunityPollingService
{
    public function sync(HubspotOpportunitySyncRun $run): void
    {
        $run->loadMissing('integration.integrationtype');
        $integration = $run->integration;
        if (! $integration || strtolower((string) $integration->integrationtype?->name) !== 'hubspot') {
            throw new RuntimeException('La integración seleccionada no es HubSpot.');
        }

        if (! $integration->status || $integration->hubspot_return_mode !== 'api') {
            throw new RuntimeException('La consulta API solo está activa para integraciones HubSpot activas en modo API.');
        }

        $token = trim((string) $integration->tokent);
        if ($token === '') {
            throw new RuntimeException('No existe access_token configurado para HubSpot.');
        }

        $baseUrl = $this->hubspotBaseUrl($integration);
        $prefix = $integration->crmIdPrefix();
        if ($prefix === '') {
            throw new RuntimeException('El prefijo CRM de HubSpot está vacío.');
        }

        $run->forceFill([
            'status' => 'running',
            'started_at' => now(),
            'error_message' => null,
            'opportunities_checked' => 0,
            'leads_updated' => 0,
            'leads_not_found' => 0,
        ])->save();

        $selectedPipelineId = trim((string) $integration->hubspot_pipeline_id);
        if ($selectedPipelineId === '') {
            throw new RuntimeException('Selecciona y sincroniza un pipeline HubSpot antes de activar la consulta API.');
        }

        // Refresh only the selected pipeline so its stage order supports
        // forward-only transitions and intermediate states.
        app(HubspotPipelineSyncService::class)->syncCrmStates($integration, $selectedPipelineId);

        $opportunitiesChecked = 0;
        $leadsUpdated = 0;
        $leadsNotFound = 0;
        $escapedPrefix = addcslashes($prefix, '\\%_');

        Lead::query()
            ->where('customer_id', $integration->customer_id)
            ->where('crm_id_oportunidad', 'like', $escapedPrefix.'-%')
            ->orderBy('id')
            ->chunkById(100, function ($leads) use (
                $run,
                $integration,
                $token,
                $baseUrl,
                $prefix,
                $selectedPipelineId,
                &$opportunitiesChecked,
                &$leadsUpdated,
                &$leadsNotFound
            ): void {
                foreach ($leads as $lead) {
                    $storedOpportunityId = trim((string) $lead->crm_id_oportunidad);
                    $opportunityId = substr($storedOpportunityId, strlen($prefix) + 1);
                    if ($opportunityId === '') {
                        continue;
                    }

                    $response = Http::acceptJson()
                        ->withToken($token)
                        ->get($baseUrl.'/crm/v3/objects/deals/'.rawurlencode($opportunityId), [
                            'properties' => 'dealstage,pipeline',
                            'archived' => 'true',
                        ]);
                    $opportunitiesChecked++;

                    if ($response->status() === 404) {
                        $leadsNotFound++;
                        HubspotOpportunitySyncLog::query()->create([
                            'sync_run_id' => $run->id,
                            'integration_id' => $integration->id,
                            'lead_id' => $lead->id,
                            'opportunity_id' => $opportunityId,
                            'state_status' => 'not_found',
                            'message' => 'HubSpot no encontró la oportunidad consultada.',
                        ]);
                        continue;
                    }

                    if (! $response->successful()) {
                        throw new RuntimeException(sprintf(
                            'HubSpot no permitió consultar la oportunidad %s (HTTP %d): %s',
                            $opportunityId,
                            $response->status(),
                            $response->body()
                        ));
                    }

                    $stageId = trim((string) $response->json('properties.dealstage', ''));
                    $pipelineExternalId = trim((string) $response->json('properties.pipeline', ''));
                    if ($stageId === '' || $pipelineExternalId === '') {
                        HubspotOpportunitySyncLog::query()->create([
                            'sync_run_id' => $run->id,
                            'integration_id' => $integration->id,
                            'lead_id' => $lead->id,
                            'opportunity_id' => $opportunityId,
                            'state_status' => 'error',
                            'message' => 'La respuesta de HubSpot no incluyó pipeline y dealstage.',
                        ]);
                        continue;
                    }

                    if ($pipelineExternalId !== $selectedPipelineId) {
                        HubspotOpportunitySyncLog::query()->create([
                            'sync_run_id' => $run->id,
                            'integration_id' => $integration->id,
                            'lead_id' => $lead->id,
                            'opportunity_id' => $opportunityId,
                            'pipeline_id' => $pipelineExternalId,
                            'stage_id' => $stageId,
                            'state_status' => 'skipped',
                            'message' => 'La oportunidad pertenece a un pipeline distinto del seleccionado para esta integración.',
                        ]);
                        continue;
                    }

                    $state = CrmState::query()
                        ->with('pipeline')
                        ->whereKey($prefix.'-'.$stageId)
                        ->first();
                    if (! $state || ! $state->pipeline || (int) $state->pipeline->integration_id !== (int) $integration->id
                        || (string) $state->pipeline->external_id !== $pipelineExternalId) {
                        HubspotOpportunitySyncLog::query()->create([
                            'sync_run_id' => $run->id,
                            'integration_id' => $integration->id,
                            'lead_id' => $lead->id,
                            'opportunity_id' => $opportunityId,
                            'pipeline_id' => $pipelineExternalId,
                            'stage_id' => $stageId,
                            'state_status' => 'not_found',
                            'message' => 'No existe un crm_state sincronizado para la etapa y pipeline de HubSpot.',
                        ]);
                        $leadsNotFound++;
                        continue;
                    }

                    $previousState = (string) $lead->crm_state;
                    $updated = app(LeadCrmStateProgressionService::class)
                        ->transition($lead, (string) $state->id, null, true);

                    if ($updated) {
                        $leadsUpdated++;
                    }

                    HubspotOpportunitySyncLog::query()->create([
                        'sync_run_id' => $run->id,
                        'integration_id' => $integration->id,
                        'lead_id' => $lead->id,
                        'opportunity_id' => $opportunityId,
                        'previous_crm_state' => $previousState !== '' ? $previousState : null,
                        'new_crm_state' => $state->id,
                        'pipeline_id' => $pipelineExternalId,
                        'stage_id' => $stageId,
                        'state_status' => $updated ? 'updated' : 'unchanged',
                        'message' => $updated ? null : 'El estado local es igual o tiene mayor avance.',
                    ]);
                }
            });

        $run->forceFill([
            'status' => 'completed',
            'opportunities_checked' => $opportunitiesChecked,
            'leads_updated' => $leadsUpdated,
            'leads_not_found' => $leadsNotFound,
            'completed_at' => now(),
        ])->save();
    }

    private function hubspotBaseUrl(Integration $integration): string
    {
        $parts = parse_url(trim((string) ($integration->url_negocio ?: $integration->url)));
        if (! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('No existe una URL base válida para consultar negocios de HubSpot.');
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
