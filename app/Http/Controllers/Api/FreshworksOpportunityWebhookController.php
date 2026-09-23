<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Models\CrmState;
use App\Models\FreshworksOpportunityStage;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FreshworksOpportunityWebhookController extends Controller
{
    public function updateForIntegration(Request $request, Integration $integration, LeadFunnelHistoryService $history): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'deal_id' => ['required', 'string', 'max:255'],
            'deal_pipeline_id' => ['required', 'string', 'max:255'],
            'deal_stage_id' => ['required', 'string', 'max:255'],
            'deal_amount' => ['nullable'],
        ]);

        $prefix = $integration->crmIdPrefix();
        $stageId = trim((string) $data['deal_stage_id']);
        $pipelineId = trim((string) $data['deal_pipeline_id']);
        $stateId = $prefix.'-'.$stageId;
        $opportunityId = $prefix.'-'.trim((string) $data['deal_id']);
        $stage = FreshworksOpportunityStage::query()
            ->where('integration_id', $integration->id)
            ->where('pipeline_id', $pipelineId)
            ->where('stage_id', $stageId)
            ->first();
        if (! $stage || (string) $stage->crm_state_id !== $stateId || ! CrmState::query()->whereKey($stateId)->exists()) {
            Log::warning('Freshworks-Oportunidad webhook con stage no sincronizado', [
                'integration_id' => $integration->id,
                'deal_pipeline_id' => $data['deal_pipeline_id'],
                'deal_stage_id' => $data['deal_stage_id'],
            ]);

            return response()->json(['message' => 'OK', 'updated' => 0, 'value_updated' => 0, 'not_found' => [$stateId]]);
        }

        $updated = 0;
        $valueUpdated = 0;
        $leads = Lead::query()->where('crm_id_oportunidad', $opportunityId)->get();
        $stateUpdater = app(LeadCrmStateController::class);
        foreach ($leads as $lead) {
            $value = $this->numericValue($data['deal_amount'] ?? null);
            if ($value !== null && (string) $lead->value !== $value) {
                $lead->value = $value;
                $lead->save();
                $valueUpdated++;
            }

            if ($stateUpdater->changeLeadStateForLead($lead, $stateId, $history)) {
                $updated++;
            }
        }

        return response()->json([
            'message' => 'OK',
            'integration_id' => $integration->id,
            'updated' => $updated,
            'value_updated' => $valueUpdated,
            'not_found' => $leads->isEmpty() ? [$opportunityId] : [],
        ]);
    }

    private function numericValue(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = str_ireplace(['$', 'cop', 'usd', ' '], '', trim((string) $value));
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.') ? str_replace('.', '', $value) : str_replace(',', '', $value);
        }
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }
}
