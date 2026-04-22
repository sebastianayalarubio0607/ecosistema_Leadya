<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Http\Services\Meta\MetaInsightsSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador para sincronizar insights de Meta
 */
class MetaSyncController extends Controller
{
    public function syncInsightsYesterday(Request $request, MetaInsightsSyncService $service): RedirectResponse
    {
        try {
            $result = $service->syncYesterday(); // SIEMPRE ayer según timezone app

            $msg = "Sync OK (ayer {$result['date']}): "
                . "{$result['accounts_processed']} cuentas, "
                . "{$result['rows']} filas, "
                . "Insights upsert: {$result['insights_upserted']}, "
                . "Ads: {$result['ads_upserted']}, "
                . "AdSets: {$result['ad_sets_upserted']}, "
                . "Campaigns: {$result['campaigns_upserted']}, "
                . "Accounts: {$result['ad_accounts_upserted']}.";

            if (!empty($result['errors'])) {
                $msg .= " Errores: " . count($result['errors']) . " (revisa storage/logs/laravel.log).";
            }

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'sync' => 'Error ejecutando sync: ' . $e->getMessage(),
            ]);
        }
    }
}
