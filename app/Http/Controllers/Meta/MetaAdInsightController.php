<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Models\MetaAdInsight;
use App\Http\Services\Meta\MetaInsightsSyncService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MetaAdInsightController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->string('date')->toString();
        if (!$date) {
            $date = now()->subDay()->toDateString();
        }

        $items = MetaAdInsight::query()
            ->with(['ad.adSet.campaign.account.customer'])
            ->when($date, fn ($q) => $q->whereDate('date_stop', $date))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        // ⚠️ En MariaDB "rows" es palabra reservada. Usa alias distintos.
        // Además, muchos campos vienen como string numérico, por eso el CAST en SUM.
        $summary = MetaAdInsight::query()
            ->when($date, fn ($q) => $q->whereDate('date_stop', $date))
            ->selectRaw("
                COUNT(*) as total_rows,
                COALESCE(SUM(CAST(impressions AS UNSIGNED)), 0) as total_impressions,
                COALESCE(SUM(CAST(reach AS UNSIGNED)), 0) as total_reach,
                COALESCE(SUM(CAST(clicks AS UNSIGNED)), 0) as total_clicks,
                COALESCE(SUM(CAST(spend AS DECIMAL(18,2))), 0) as total_spend
            ")
            ->first();

        return view('meta.insights.index', compact('items', 'date', 'summary'));
    }

    /**
     * POST /insights/consult
     * Ejecuta la consulta a Meta para la fecha enviada y guarda/actualiza registros.
     */
    public function consult(Request $request, MetaInsightsSyncService $service): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($data['date'])->toDateString();

        $result = $service->syncForDate($date);

        $msg = "Consulta Meta OK ({$result['date']}): "
            . "{$result['accounts_processed']} cuentas, {$result['rows']} filas. "
            . "Insights: {$result['insights_upserted']} | Ads: {$result['ads_upserted']} | AdSets: {$result['ad_sets_upserted']} | Campaigns: {$result['campaigns_upserted']}.";

        if (!empty($result['errors'])) {
            $msg .= " Errores: " . count($result['errors']) . " (revisa logs).";
        }

        return redirect()
            ->route('meta.insights.index', ['date' => $date])
            ->with('success', $msg)
            ->with('meta_sync_result', $result);
    }

    public function show(MetaAdInsight $insight): View
    {
        $insight->load(['ad.adSet.campaign.account.customer']);
        return view('meta.insights.show', compact('insight'));
    }

    public function destroy(MetaAdInsight $insight): RedirectResponse
    {
        $insight->delete();
        return back()->with('success', 'Insight eliminado');
    }
}
