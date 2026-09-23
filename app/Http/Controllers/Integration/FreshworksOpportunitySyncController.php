<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Http\Services\Integration\FreshworksOpportunityPipelineSyncService;
use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FreshworksOpportunitySyncController extends Controller
{
    public function sync(Request $request, Integration $integration, FreshworksOpportunityPipelineSyncService $service): RedirectResponse
    {
        $pipelineId = (string) $request->validate(['pipeline_id' => ['required', 'string', 'max:255']])['pipeline_id'];

        try {
            $result = $service->syncCrmStates($integration, $pipelineId);
        } catch (\Throwable $exception) {
            Log::warning('FRESHWORKS OPPORTUNITY CRM STATES SYNC FAILED', [
                'integration_id' => $integration->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('integrations.show', $integration)
                ->withErrors(['sync' => $exception->getMessage()]);
        }

        return redirect()->route('integrations.show', $integration)
            ->with('success', "Estados de Freshworks sincronizados. Estados creados: {$result['created']}. Estados actualizados: {$result['updated']}.");
    }
}
