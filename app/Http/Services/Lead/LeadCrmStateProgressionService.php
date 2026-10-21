<?php

namespace App\Http\Services\Lead;

use App\Jobs\SendLeadToFacebook;
use App\Jobs\SendLeadToGoogleAds;
use App\Models\CrmState;
use App\Models\GohighlevelOpportunitySyncLog;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeadCrmStateProgressionService
{
    private array $integrationsByCustomer = [];

    public function __construct(private readonly LeadFunnelHistoryService $historyService) {}

    public function transition(Lead $lead, string $newCrmState, ?int $gohighlevelSyncLogId = null): bool
    {
        $newCrmState = trim($newCrmState);
        if ($newCrmState === '') {
            return false;
        }

        $conversionJobs = [];
        $channels = [];
        $leadChanged = DB::transaction(function () use ($lead, $newCrmState, $gohighlevelSyncLogId, &$conversionJobs, &$channels): bool {
            $lockedLead = Lead::query()->lockForUpdate()->find($lead->id);
            if (! $lockedLead || (string) $lockedLead->crm_state === $newCrmState) {
                return false;
            }

            $previousCrmState = (string) ($lockedLead->crm_state ?? '');
            $targetState = CrmState::query()->find($newCrmState);
            if (! $targetState) {
                return false;
            }

            $states = $this->statesToApply($lockedLead, $previousCrmState, $targetState);

            foreach ($states as $state) {
                $lockedLead->crm_state = (string) $state->id;
                $lockedLead->save();
                $this->historyService->recordIfFunnelChanged($lockedLead);

                [$job, $channel] = $this->conversionJobForState($lockedLead, $state, $gohighlevelSyncLogId);
                if ($job) {
                    $conversionJobs[] = $job;
                    $channels[] = $channel;
                }
            }

            return true;
        });

        if (! $leadChanged) {
            return false;
        }

        $lead->crm_state = $newCrmState;
        $lead->unsetRelation('crmState');
        $this->markGohighlevelConversionQueue($gohighlevelSyncLogId, $channels);

        if ($conversionJobs !== []) {
            try {
                app(LeadCrmStateConversionQueueService::class)
                    ->dispatchInOrder((int) $lead->id, $conversionJobs);
            } catch (Throwable $exception) {
                $this->markGohighlevelDispatchFailure($gohighlevelSyncLogId, $channels, $exception);
                Log::warning('No fue posible encolar los trabajos individuales de conversión por avance de crm_state.', [
                    'lead_id' => $lead->id,
                    'crm_state' => $newCrmState,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return true;
    }

    private function statesToApply(Lead $lead, string $previousCrmState, CrmState $targetState): array
    {
        $targetState->loadMissing('qualificationModel.funnel');
        if (mb_strtolower(trim((string) $targetState->qualificationModel?->funnel?->name)) === 'lead no efectivo') {
            return [$targetState];
        }

        $hasPreviousStateId = trim($previousCrmState) !== '';
        $previousState = $hasPreviousStateId ? CrmState::query()->find($previousCrmState) : null;
        $targetProgress = $targetState->progress_percentage;

        // An empty crm_state is treated as progress 0. A non-empty but unknown
        // state remains a direct transition because its starting progress is unknown.
        if (($hasPreviousStateId && ! $previousState)
            || ($previousState && $previousState->progress_percentage === null)
            || $targetProgress === null) {
            return [$targetState];
        }

        $previousProgress = $previousState?->progress_percentage ?? 0;
        if ((float) $targetProgress <= (float) $previousProgress) {
            return [$targetState];
        }

        $customerId = (int) $lead->customer_id;
        $integration = $this->resolveStateIntegration($targetState, $customerId);
        $previousIntegration = $previousState
            ? $this->resolveStateIntegration($previousState, $customerId)
            : null;

        if (! $integration
            || ! $this->stateBelongsToIntegration($targetState, $integration, $customerId)) {
            return [$targetState];
        }

        if ($previousState && (
            ! $previousIntegration
            || (int) $integration->id !== (int) $previousIntegration->id
            || ! $this->stateBelongsToIntegration($previousState, $integration, $customerId)
        )) {
            return [$targetState];
        }

        // If either endpoint has a Pipeline, scope intermediate states to the
        // destination Pipeline when available; otherwise use the source one.
        // With both endpoints unassigned, keep the integration-wide behavior.
        $pipelineId = $targetState->pipeline_id ?: $previousState?->pipeline_id;
        if ($pipelineId) {
            $pipelineBelongsToIntegration = $integration->pipelines()
                ->whereKey($pipelineId)
                ->exists();
            if (! $pipelineBelongsToIntegration) {
                return [$targetState];
            }
        }

        $prefix = $integration->crmIdPrefix();
        $escapedPrefix = addcslashes($prefix, '\\%_');
        $intermediateStates = CrmState::query()
            ->where('id', 'like', $escapedPrefix.'-%')
            ->where('progress_percentage', '>', $previousProgress)
            ->where('progress_percentage', '<', $targetProgress)
            ->when($pipelineId, fn ($query) => $query->where('pipeline_id', $pipelineId))
            ->orderBy('progress_percentage')
            ->orderBy('id')
            ->get()
            ->filter(fn (CrmState $state) => $this->stateBelongsToIntegration($state, $integration, $customerId))
            ->values();

        return $intermediateStates->push($targetState)->all();
    }

    private function stateBelongsToIntegration(CrmState $state, Integration $integration, int $customerId): bool
    {
        $prefix = $integration->crmIdPrefix();
        if ($prefix === '' || ! str_starts_with((string) $state->id, $prefix.'-')) {
            return false;
        }

        $resolvedIntegration = $this->resolveStateIntegration($state, $customerId);

        return $resolvedIntegration !== null
            && (int) $resolvedIntegration->id === (int) $integration->id;
    }

    private function resolveStateIntegration(CrmState $state, int $customerId): ?Integration
    {
        if (! array_key_exists($customerId, $this->integrationsByCustomer)) {
            $this->integrationsByCustomer[$customerId] = Integration::query()
                ->where('customer_id', $customerId)
                ->get(['id', 'customer_id', 'disable_integration_id_crm_prefix', 'crm_id_prefix']);
        }

        $integrations = $this->integrationsByCustomer[$customerId];

        $matches = $integrations
            ->map(fn (Integration $integration) => [
                'integration' => $integration,
                'prefix' => $integration->crmIdPrefix(),
            ])
            ->filter(fn (array $match) => $match['prefix'] !== '' && str_starts_with((string) $state->id, $match['prefix'].'-'))
            ->sortByDesc(fn (array $match) => strlen($match['prefix']))
            ->values();

        if ($matches->isEmpty()) {
            return null;
        }

        $longestPrefix = strlen($matches[0]['prefix']);
        $bestMatches = $matches->filter(fn (array $match) => strlen($match['prefix']) === $longestPrefix);
        if ($bestMatches->count() !== 1) {
            return null;
        }

        return $bestMatches->first()['integration'];
    }

    private function conversionJobForState(Lead $lead, CrmState $state, ?int $gohighlevelSyncLogId): array
    {
        $source = LeadAdSourceClassifier::classify($lead);

        if ($source['is_meta_ads']) {
            $state->loadMissing('metaEvent', 'whatsappEvent');
            $lead->loadMissing('customer');
            $isWhatsappDataset = (string) $lead->campaign_origin === 'whatsapp'
                && (bool) $lead->customer?->Meta_whatsapp_dataset;

            $hasConversionEvent = $isWhatsappDataset
                ? (! empty($state->whatsapp_event_id) || ! empty($state->meta_event_id))
                : ! empty($state->meta_event_id);

            if (! $hasConversionEvent) {
                return [null, null];
            }

            return [
                new SendLeadToFacebook(
                    (int) $lead->id,
                    (int) $lead->customer_id,
                    null,
                    $gohighlevelSyncLogId,
                    (string) $state->id,
                    false
                ),
                $isWhatsappDataset && ! empty($state->whatsapp_event_id) ? 'whatsapp' : 'meta',
            ];
        }

        if ($source['is_google_ads']) {
            return [
                new SendLeadToGoogleAds((int) $lead->id, (string) $state->id, $gohighlevelSyncLogId, false),
                'google_ads',
            ];
        }

        return [null, null];
    }

    private function markGohighlevelConversionQueue(?int $syncLogId, array $channels): void
    {
        if (! $syncLogId || $channels === []) {
            return;
        }

        $channels = array_values(array_unique($channels));
        $log = GohighlevelOpportunitySyncLog::query()->find($syncLogId);
        if (! $log) {
            return;
        }

        $updates = [
            'conversion_channel' => count($channels) === 1 ? $channels[0] : 'multiple',
            'conversion_requested_at' => now(),
        ];
        if (in_array('meta', $channels, true) || in_array('whatsapp', $channels, true)) {
            $updates['facebook_conversion_status'] = 'pending';
        }
        if (in_array('google_ads', $channels, true)) {
            $updates['google_ads_conversion_status'] = 'pending';
        }

        $log->forceFill($updates)->save();
    }

    private function markGohighlevelDispatchFailure(?int $syncLogId, array $channels, Throwable $exception): void
    {
        if (! $syncLogId) {
            return;
        }

        $updates = ['message' => $exception->getMessage()];
        if (in_array('meta', $channels, true) || in_array('whatsapp', $channels, true)) {
            $updates['facebook_conversion_status'] = 'failed';
        }
        if (in_array('google_ads', $channels, true)) {
            $updates['google_ads_conversion_status'] = 'failed';
        }

        $updates['updated_at'] = now();
        GohighlevelOpportunitySyncLog::query()->whereKey($syncLogId)->update($updates);
    }
}
