<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadCrmStateController extends Controller
{
    public function update(Request $request, string $public_key)
    {
        // 1) Buscar integration por public_key
        $integration = Integration::query()
            ->where('public_key', $public_key)
            ->first();

        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        // 2) Leer el body tal como llegue (Kommo suele mandar x-www-form-urlencoded)
        $data = $request->all();

        // Aquí vienen los cambios de estado:
        // leads[status][0][id]
        // leads[status][0][status_id]
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

        DB::transaction(function () use ($statuses, $integration, &$updated, &$notFound) {
            foreach ($statuses as $item) {
                if (!is_array($item)) {
                    continue;
                }

                // 3) Extraer IDs desde el webhook
                $kommoLeadId = (string) ($item['id'] ?? '');          // leads[status][0][id]
                $statusId    = (string) ($item['status_id'] ?? '');   // leads[status][0][status_id]

                if ($kommoLeadId === '' || $statusId === '') {
                    continue;
                }

                // 4) Construir el crm_id para ubicar el Lead en tu BD:
                // crm_id = integration_id - kommoLeadId
                $crmIdToFind = $integration->id . '-' . $kommoLeadId;

                $lead = Lead::query()
                    ->where('crm_id', $crmIdToFind)
                    ->first();

                if (!$lead) {
                    $notFound[] = $crmIdToFind;
                    continue;
                }

                // 5) Construir el nuevo crm_state:
                // crm_state = integration_id - statusId
                $newCrmState = $integration->id . '-' . $statusId;

                $lead->crm_state = $newCrmState;
                $lead->save();

                $updated++;
            }
        });

        return response()->json([
            'message' => 'OK',
            'integration_id' => $integration->id,
            'updated' => $updated,
            'not_found' => $notFound, // crm_id que no se encontraron en tu BD
        ]);
    }
}
