<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Jobs\SendLeadToFacebook;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class LeadCrmStateController extends Controller
{
    public function update(Request $request, string $public_key, LeadFunnelHistoryService $historyService)
    {
        $integration = Integration::query()
            ->where('public_key', $public_key)
            ->first();

        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        $data = $request->all();
        $statuses = data_get($data, 'leads.status', []);

        if (!is_array($statuses) || count($statuses) === 0) {
            Log::warning('Webhook sin leads.status', [
                'public_key' => $public_key,
                'content_type' => $request->header('content-type'),
                'data_keys' => array_keys($data),
                'data_sample' => $data,
            ]);

            return response()->json([
                'message' => 'Invalid payload: leads.status not found'
            ], 422);
        }

        $updated = 0;
        $notFound = [];

        foreach ($statuses as $item) {
            if (!is_array($item)) {
                continue;
            }

            $kommoLeadId = (string) ($item['id'] ?? '');
            $statusId    = (string) ($item['status_id'] ?? '');

            if ($kommoLeadId === '' || $statusId === '') {
                continue;
            }

            $crmIdToFind = $integration->id . '-' . $kommoLeadId;

            $lead = Lead::query()
                ->where('crm_id', $crmIdToFind)
                ->first();

            if (!$lead) {
                $notFound[] = $crmIdToFind;
                continue;
            }

            $newCrmState = $integration->id . '-' . $statusId;

            // (Opcional) si no cambió el estado, no hacemos nada
            if ((string) $lead->crm_state === (string) $newCrmState) {
                continue;
            }

            $lead->crm_state = $newCrmState;
            $lead->save();

             // ✅ HISTÓRICO: solo se registra si cambió el funnel
            $historyService->recordIfFunnelChanged($lead);

            $updated++;

            // ✅ MISMA condición que ya venías usando
            if (!in_array($lead->campaign_origin, ['fb', 'meta','ig','wa','mg','th'], true)) {
                continue;
            }

            // ✅ Si crm_state es null/vacío -> no envía nada
            if (empty($lead->crm_state)) {
                continue;
            }

            // ✅ Si el CrmState del lead no tiene meta_event_id -> no envía nada
            $lead->unsetRelation('crmState');
            $lead->load('crmState');

            if (empty($lead->crmState?->meta_event_id)) {
                continue;
            }

            // ✅ Disparar el mismo Job (sin transacción)
            SendLeadToFacebook::dispatch($lead->id, $lead->customer_id);
        }

        return response()->json([
            'message' => 'OK',
            'integration_id' => $integration->id,
            'updated' => $updated,
            'not_found' => $notFound,
        ]);
    }
}

