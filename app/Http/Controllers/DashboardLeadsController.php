<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadFunnelHistory;
use App\Models\MetaAdAccount;
use App\Models\MetaAdInsight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Controlador para manejar las vistas y endpoints relacionados con los leads en el dashboard.
 */
class DashboardLeadsController extends Controller
{
    private const DONUT_MAX_ITEMS = 7;

    private const DASHBOARD_FILTER_KEYS = [
        'campaign_origin',
        'plataforma',
        'lenguaje',
        'geo',
        'crm_state',
        'qualification',
    ];

    private const LIST_FILTER_KEYS = [
        'campaign_origin',
        'plataforma',
        'lenguaje',
        'geo',
    ];

    private const ALLOWED_GROUP_TYPES = [
        'crm_state',
        'qualification',
        'campaign_origin',
        'plataforma',
        'funnel',
        'funnel_history',
    ];

    /**
     * Muestra la vista principal del dashboard de leads, con métricas y filtros.
     */
    public function leads(Request $request)
    {
        [$customerId, $integrationId] = $this->resolveRequestScope($request);
        $filters = $this->extractDashboardFilters($request);
        [$from, $to, $nowMax] = $this->resolveRequestedDateRange($request);
        [$customers, $selectedCustomer] = $this->resolveCustomerSelection($customerId);

        $metric = $this->buildDashboardMetrics(
            $customerId,
            $integrationId,
            (string) $request->session()->getId(),
            $filters,
            $from,
            $to
        );

        $ui = $this->buildDashboardViewData(
            $request,
            $customerId,
            $integrationId,
            $selectedCustomer,
            $customers,
            $metric,
            $from,
            $to,
            $nowMax
        );

        return view('dashboard.leads', compact(
            'customers',
            'customerId',
            'integrationId',
            'selectedCustomer',
            'metric',
            'from',
            'to',
            'nowMax',
            'ui'
        ));
    }

    /*
    * Export CSV (Excel) del listado de leads por grupo (filtro aplicado)
     * Nota: el export se basa en el mismo query que el listado paginado, para mantener consistencia entre ambos.
    */
    public function leadsList(Request $request)
    {
        [$customerId, $integrationId] = $this->resolveRequestScope($request);
        [$groupType, $groupId] = $this->resolveRequestedGroup($request);
        $filters = $this->extractListFilters($request);
        [$from, $to, $nowMax] = $this->resolveRequestedDateRange($request);
        [$customers, $selectedCustomer] = $this->resolveCustomerSelection($customerId);

        $groupLabel = $this->resolveGroupDisplayLabel($groupType, $groupId);

        $totalValueFormatted = $this->formatGroupedLeadsValue(
            $customerId,
            $integrationId,
            $filters,
            $groupType,
            $groupId,
            $from,
            $to
        );

        $leads = $this->paginateGroupedLeads(
            $customerId,
            $integrationId,
            $filters,
            $groupType,
            $groupId,
            20,
            $from,
            $to
        )->withQueryString();

        $leads = $this->transformLeadListRows($leads);

        $backUrl = route('dashboard.leads', Arr::except($request->query(), [
            'group_type', 'group_id', 'page',
        ]));

        $exportUrl = route('dashboard.leads.list.export', Arr::except($request->query(), ['page']));
        $periodLabel = $from->format('Y-m-d H:i').' -> '.$to->format('Y-m-d H:i');

        return view('dashboard.leads_list', compact(
            'customers',
            'customerId',
            'integrationId',
            'selectedCustomer',
            'totalValueFormatted',
            'groupType',
            'groupId',
            'groupLabel',
            'leads',
            'backUrl',
            'exportUrl',
            'periodLabel',
            'from',
            'to',
            'nowMax'
        ));
    }

    /**
     * Export CSV (Excel) del listado de leads por grupo (filtro aplicado)
     */
    public function leadsListExport(Request $request)
    {
        [$customerId, $integrationId] = $this->resolveRequestScope($request);
        [$groupType, $groupId] = $this->resolveRequestedGroup($request);
        $filters = $this->extractListFilters($request);
        [$from, $to] = $this->resolveRequestedDateRange($request);

        $groupLabel = $this->resolveGroupDisplayLabel($groupType, $groupId);
        $safeLabel = Str::slug($groupLabel ?: 'leads', '_');
        $filename = "leads_{$safeLabel}_".now()->format('Ymd_His').'.csv';

        $query = $this->buildGroupedLeadsQuery(
            $customerId,
            $integrationId,
            $filters,
            $groupType,
            $groupId,
            $from,
            $to
        );

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel

            fputcsv($out, ['Fecha', 'ID', 'Teléfono', 'Nombre', 'Apellido', 'Fuente', 'Medio', 'Campaign Objective', 'Estado', 'Cualificación', 'Valor', 'page_url']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $lead) {
                    $phone = $lead->telefono ?? $lead->phone ?? $lead->phone_number ?? $lead->celular ?? $lead->movil ?? null;
                    $first = $lead->nombre ?? $lead->first_name ?? $lead->name ?? $lead->nombres ?? null;
                    $last = $lead->apellido ?? $lead->last_name ?? $lead->lastname ?? $lead->apellidos ?? null;

                    $fuente = $lead->campaign_origin;
                    $medio = $lead->plataforma;

                    $fuenteLabel = ($fuente === null || $fuente === '') ? 'Sin Fuente' : $fuente;
                    $medioLabel = ($medio === null || $medio === '') ? 'Sin Medio' : $medio;

                    $value = is_numeric($lead->value ?? null) ? (float) $lead->value : 0.0;
                    $pageUrl = $lead->page_url ?? '';

                    fputcsv($out, [
                        optional($lead->created_at)->format('Y-m-d H:i'),
                        $lead->id,
                        $phone ?? '',
                        $first ?? '',
                        $last ?? '',
                        $fuenteLabel,
                        $medioLabel,
                        $lead->campaign_objective_name ?? 'Sin Campaign Objective',
                        $lead->crm_state_name ?? 'Sin Estado',
                        $lead->qualification_name ?? 'Sin Cualificación',
                        $value,
                        $pageUrl,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Resuelve el alcance del request, extrayendo y validando los parámetros relevantes.
     */
    private function resolveRequestScope(Request $request): array
    {
        return [
            $request->integer('customer_id') ?: null,
            $request->integer('integration_id') ?: null,
        ];
    }

    /**
     * Resuelve la selección de clientes para los filtros del dashboard, obteniendo la lista de clientes y el cliente seleccionado (si aplica).
     */
    private function resolveCustomerSelection(?int $customerId): array
    {
        $customers = Customer::query()->orderBy('name')->get(['id', 'name']);
        $selectedCustomer = $customerId ? $customers->firstWhere('id', $customerId) : null;

        return [$customers, $selectedCustomer];
    }

    private function extractDashboardFilters(Request $request): array
    {
        return $request->only(self::DASHBOARD_FILTER_KEYS);
    }

    /**
     * Resuelve la etiqueta legible para el grupo solicitado, para mostrar en el encabezado del listado de leads.
     */
    private function extractListFilters(Request $request): array
    {
        return $request->only(self::LIST_FILTER_KEYS);
    }

    /**
     *      Resuelve la etiqueta legible para el grupo solicitado, para mostrar en el encabezado del listado de leads.
     */
    private function resolveRequestedGroup(Request $request): array
    {
        $groupType = (string) $request->get('group_type', '');
        $groupId = (string) $request->get('group_id', '');

        if (! in_array($groupType, self::ALLOWED_GROUP_TYPES, true) || $groupId === '') {
            abort(404);
        }

        return [$groupType, $groupId];
    }

    /**
     * Formatea el valor total de los leads agrupados para mostrar en el dashboard.
     */
    private function formatGroupedLeadsValue(
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        string $groupType,
        string $groupId,
        Carbon $from,
        Carbon $to
    ): string {
        $leadTable = (new Lead)->getTable();

        if (! Schema::hasColumn($leadTable, 'value')) {
            return '$ 0';
        }

        $groupedLeadsQuery = $this->buildGroupedLeadsQuery(
            $customerId,
            $integrationId,
            $filters,
            $groupType,
            $groupId,
            $from,
            $to
        );

        if ($groupType === 'funnel_history') {
            $leadIdsSubquery = (clone $groupedLeadsQuery)->reorder()->select("{$leadTable}.id")->distinct();
            $totalValue = (float) Lead::query()->whereIn("{$leadTable}.id", $leadIdsSubquery)->sum("{$leadTable}.value");
        } else {
            $totalValue = (float) (clone $groupedLeadsQuery)->sum("{$leadTable}.value");
        }

        return '$ '.number_format($totalValue, 0, ',', '.');
    }

    /**
     * Resuelve el rango de fechas solicitado, aplicando validaciones y normalizaciones.
     */
    private function resolveRequestedDateRange(Request $request): array
    {
        $tz = config('app.timezone') ?: 'UTC';
        $now = now($tz);
        $nowMax = $now->format('Y-m-d\\TH:i');

        $fromRaw = trim((string) $request->query('from', ''));
        $toRaw = trim((string) $request->query('to', ''));

        $parse = function (string $raw) use ($tz): ?Carbon {
            if ($raw === '') {
                return null;
            }

            // datetime-local suele venir como: 2026-02-17T12:30
            try {
                return Carbon::createFromFormat('Y-m-d\\TH:i', $raw, $tz);
            } catch (\Throwable $e) {
                try {
                    return Carbon::parse($raw, $tz);
                } catch (\Throwable $e2) {
                    return null;
                }
            }
        };

        $to = $parse($toRaw) ?? $now->copy();
        $from = $parse($fromRaw) ?? $to->copy()->subDays(7);

        // No permitir futuro (pasado + presente)
        if ($to->greaterThan($now)) {
            $to = $now->copy();
        }
        if ($from->greaterThan($now)) {
            $from = $now->copy();
        }

        // Si vienen invertidas, las intercambiamos
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        // Normaliza precisiÃ³n a minuto (por si llega con segundos)
        $from = $from->copy()->seconds(0);
        $to = $to->copy()->seconds(0);

        return [$from, $to, $nowMax];
    }

    /**
     * Construye las métricas del dashboard de leads, aplicando los filtros y agrupamientos necesarios.
     * Implementa caching para mejorar rendimiento en consultas repetidas.
     * Nota: el caching se hace a nivel de usuario + sesion + filtros, para evitar interferencia entre usuarios y mantener consistencia en la experiencia.
     * El cache tiene una expiraciÃ³n corta (60 segundos) para balancear frescura de datos y rendimiento.
     */
    private function buildDashboardMetrics(
        ?int $customerId,
        ?int $integrationId,
        string $sessionId,
        array $filters,
        Carbon $from,
        Carbon $to
    ): array {
        $filters = $this->sanitizeDashboardFilters($filters);

        // âœ… Cache per usuario + sesiÃ³n (evita interferencia entre usuarios)
        $userKey = (string) (auth()->id() ?? 'guest');
        $key = $this->makeMetricsCacheKey($customerId, $integrationId, $userKey, $sessionId, $filters, $from, $to);

        $insightsTable = (new MetaAdInsight)->getTable();

        return Cache::remember($key, now()->addSeconds(60), function () use ($customerId, $integrationId, $filters, $from, $to) {
            $leadTable = (new Lead)->getTable();

            $base = Lead::query()->whereBetween("{$leadTable}.created_at", [$from, $to]);
            $base = $this->applyLeadScopeFilters($base, $customerId, $integrationId, $filters, $leadTable);

            // 1) Totales (aplicando todos los filtros)
            $qTotals = clone $base;
            $qTotals = $this->applyLeadDimensionFilters($qTotals, $filters, $leadTable, true, true, true, true);

            $totalCount = (int) (clone $qTotals)->count();

            $managedCount = (int) (clone $qTotals)
                ->leftJoin('crm_state as csm', 'csm.id', '=', "{$leadTable}.crm_state")
                ->whereNotNull("{$leadTable}.crm_state")
                ->where("{$leadTable}.crm_state", '!=', '')
                ->where(function ($qq) {
                    $qq->whereNull('csm.unmanaged')
                        ->orWhere('csm.unmanaged', false);
                })
                ->count();

            $pendingCount = (int) (clone $qTotals)
                ->leftJoin('crm_state as csp', 'csp.id', '=', "{$leadTable}.crm_state")
                ->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.crm_state")
                        ->orWhere("{$leadTable}.crm_state", '')
                        ->orWhere('csp.unmanaged', true);
                })
                ->count();

            // 2) Fuente (NO aplica campaign_origin)
            $qChannels = clone $base;
            $qChannels = $this->applyLeadDimensionFilters($qChannels, $filters, $leadTable, false, true, true, true);

            $channels = (clone $qChannels)
                ->selectRaw("\n                    COALESCE(NULLIF(MIN({$leadTable}.campaign_origin), ''), '__NULL__') as campaign_origin,\n                    COUNT(*) as total\n                ")
                ->groupByRaw("COALESCE(NULLIF({$leadTable}.campaign_origin, ''), '__NULL__')")
                ->orderByDesc('total')
                ->limit(10)
                ->pluck('total', 'campaign_origin')
                ->toArray();

            $nullChannelsCount = (int) (clone $qChannels)
                ->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.campaign_origin")
                        ->orWhere("{$leadTable}.campaign_origin", '');
                })
                ->count();

            if ($nullChannelsCount > 0) {
                $channels['__NULL__'] = $nullChannelsCount;
            }

            // 3) Medio (NO aplica plataforma)
            $qPlatforms = clone $base;
            $qPlatforms = $this->applyLeadDimensionFilters($qPlatforms, $filters, $leadTable, true, false, true, true);

            $platforms = (clone $qPlatforms)
                ->selectRaw("\n                    COALESCE(NULLIF(MIN({$leadTable}.plataforma), ''), '__NULL__') as plataforma,\n                    COUNT(*) as total\n                ")
                ->groupByRaw("COALESCE(NULLIF({$leadTable}.plataforma, ''), '__NULL__')")
                ->orderByDesc('total')
                ->limit(10)
                ->pluck('total', 'plataforma')
                ->toArray();

            $nullPlatformsCount = (int) (clone $qPlatforms)
                ->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.plataforma")
                        ->orWhere("{$leadTable}.plataforma", '');
                })
                ->count();

            if ($nullPlatformsCount > 0) {
                $platforms['__NULL__'] = $nullPlatformsCount;
            }

            // 4) CRM States (NO aplica crm_state)
            $qCrmStates = clone $base;
            $qCrmStates = $this->applyLeadDimensionFilters($qCrmStates, $filters, $leadTable, true, true, false, true);

            $crmStates = (clone $qCrmStates)
                ->whereNotNull("{$leadTable}.crm_state")
                ->where("{$leadTable}.crm_state", '!=', '')
                ->leftJoin('crm_state as cs', 'cs.id', '=', "{$leadTable}.crm_state")
                ->selectRaw("\n                    {$leadTable}.crm_state as crm_state_id,\n                    COALESCE(cs.name, {$leadTable}.crm_state) as name,\n                    COUNT(*) as total\n                ")
                ->groupBy("{$leadTable}.crm_state", 'cs.name')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($r) => [
                    'id' => (string) $r->crm_state_id,
                    'name' => (string) $r->name,
                    'count' => (int) $r->total,
                ])
                ->toArray();

            // 5) Qualifications (NO aplica qualification)
            $qQualifications = clone $base;
            $qQualifications = $this->applyLeadDimensionFilters($qQualifications, $filters, $leadTable, true, true, true, false);

            $qualifications = (clone $qQualifications)
                ->whereNotNull("{$leadTable}.crm_state")
                ->where("{$leadTable}.crm_state", '!=', '')
                ->leftJoin('crm_state as csq', 'csq.id', '=', "{$leadTable}.crm_state")
                ->leftJoin('qualification as ql', 'ql.id', '=', 'csq.qualification')
                ->selectRaw("\n                    ql.id as id,\n                    COALESCE(ql.name, 'Sin cualificación') as name,\n                    COUNT(*) as total\n                ")
                ->groupBy('ql.id', 'ql.name')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id !== null ? (int) $r->id : null,
                    'name' => (string) $r->name,
                    'count' => (int) $r->total,
                ])
                ->toArray();

            // âœ… Indicador: "Leads NO Efectivos" (por CUALIFICACIÃ“N)
            // RelaciÃ³n: qualification -> crm_state -> leads
            // Nota: NO aplica el filtro 'qualification' del dashboard (usa $qQualifications)
            $notEffectiveFunnelId = null;
            $notEffectiveCount = 0;

            // âœ… FUNNELS + CALIFICADOS + VENTAS
            [$funnelTable, $qualFunnelFk] = $this->resolveFunnelJoinInfo();

            $funnels = [];
            $noFunnelCount = 0;

            $qualifiedCount = 0;
            $salesCount = 0;
            $salesValueSum = 0.0;
            $qualifiedFunnelId = null;
            $salesFunnelId = null;
            $respondedFunnelId = null;

            if ($funnelTable && $qualFunnelFk) {
                $qFunnels = clone $base;
                $qFunnels = $this->applyLeadDimensionFilters($qFunnels, $filters, $leadTable, true, true, true, true);

                $funnelRowsQuery = (clone $qFunnels)
                    ->leftJoin('crm_state as csf', 'csf.id', '=', "{$leadTable}.crm_state")
                    ->leftJoin('qualification as qlf', 'qlf.id', '=', 'csf.qualification')
                    ->leftJoin("{$funnelTable} as fn", 'fn.id', '=', "qlf.{$qualFunnelFk}")
                    ->selectRaw("
                        CASE
                            WHEN {$leadTable}.crm_state IS NULL OR {$leadTable}.crm_state = '' THEN '__LEADS__'
                            WHEN fn.id IS NULL THEN '__NULL__'
                            ELSE CAST(fn.id AS CHAR)
                        END as funnel_id,
                        CASE
                            WHEN {$leadTable}.crm_state IS NULL OR {$leadTable}.crm_state = '' THEN 'Leads'
                            WHEN fn.id IS NULL THEN 'Sin Funnel'
                            ELSE fn.name
                        END as name
                    ");

                $rows = DB::query()
                    ->fromSub($funnelRowsQuery, 'funnel_rows')
                    ->selectRaw('funnel_id, name, COUNT(*) as total')
                    ->groupBy('funnel_id', 'name')
                    ->orderByDesc('total')
                    ->get();

                foreach ($rows as $r) {
                    if ($r->funnel_id === '__NULL__') {
                        $noFunnelCount = (int) $r->total;
                    }
                    $funnels[] = [
                        'id' => (string) $r->funnel_id,
                        'name' => (string) $r->name,
                        'count' => (int) $r->total,
                    ];
                }

                $notEffectiveFunnelId = $this->findFunnelIdByName($funnelTable, 'Lead NO Efectivo');
                $qualifiedFunnelId = $this->findFunnelIdByName($funnelTable, 'Oportunidades');
                $respondedFunnelId = $this->findFunnelIdByName($funnelTable, 'Respondidos');
                $salesFunnelId = $this->findFunnelIdByName($funnelTable, 'Ventas');

                if ($notEffectiveFunnelId !== null) {
                    $notEffectiveCount = $this->countLeadsByFunnelIds($base, $filters, $leadTable, $funnelTable, $qualFunnelFk, [$notEffectiveFunnelId]);
                }

                $qualifiedFunnelIds = array_values(array_filter([$qualifiedFunnelId, $respondedFunnelId], fn ($id) => $id !== null));
                if (! empty($qualifiedFunnelIds)) {
                    $qualifiedCount = $this->countLeadsByFunnelIds($base, $filters, $leadTable, $funnelTable, $qualFunnelFk, $qualifiedFunnelIds);
                }
                if ($salesFunnelId !== null) {
                    $salesCount = $this->countLeadsByFunnelIds($base, $filters, $leadTable, $funnelTable, $qualFunnelFk, [$salesFunnelId]);
                    $salesValueSum = $this->sumLeadsValueByFunnelIds($base, $filters, $leadTable, $funnelTable, $qualFunnelFk, [$salesFunnelId]);
                }
            }

            // âœ… HISTÃ“RICO: Leads por Funnel (usando LeadFunnelHistory)
            $funnelsHistory = [];
            $historyTable = (new LeadFunnelHistory)->getTable();
            $funnelTableForHistory = Schema::hasTable('funnels') ? 'funnels' : $funnelTable;

            if ($historyTable && Schema::hasTable($historyTable) && $funnelTableForHistory) {
                // Base de leads: mismos filtros + ventana (leads.created_at)
                $leadIdsSub = (clone $qTotals)->select("{$leadTable}.id");

                $rowsH = DB::table("{$historyTable} as lfh")
                    ->join("{$funnelTableForHistory} as fnh", 'fnh.id', '=', 'lfh.funnel_id')
                    ->whereBetween('lfh.created_at', [$from, $to])
                    ->whereIn('lfh.lead_id', $leadIdsSub)
                    ->selectRaw("lfh.funnel_id as funnel_id, COALESCE(fnh.name, '') as name, COUNT(DISTINCT lfh.lead_id) as total")
                    ->groupBy('lfh.funnel_id', 'fnh.name')
                    ->orderByDesc('total')
                    ->get();

                foreach ($rowsH as $r) {
                    $funnelsHistory[] = [
                        'id' => (string) $r->funnel_id,
                        'name' => (string) $r->name,
                        'count' => (int) $r->total,
                    ];
                }
            }

            $metaSpend = $this->getMetaSpend($customerId, $integrationId, $filters, $from, $to);

            return [
                'count' => $totalCount,
                'managed_count' => $managedCount,
                'pending_count' => $pendingCount,
                'spend' => $metaSpend,
                'channels' => $channels,
                'platforms' => $platforms,
                'crm_states' => $crmStates,
                'qualifications' => $qualifications,

                'funnels' => $funnels,
                'funnels_history' => $funnelsHistory,
                'no_funnel_count' => $noFunnelCount,
                'qualified_count' => $qualifiedCount,
                'qualified_funnel_id' => $qualifiedFunnelId,
                'sales_count' => $salesCount,
                'sales_funnel_id' => $salesFunnelId,
                'sales_value_sum' => (float) $salesValueSum,

                'not_effective_count' => (int) $notEffectiveCount,
                'not_effective_funnel_id' => $notEffectiveFunnelId,

                'calculated_at' => now()->toISOString(),
                'window_from' => $from->toISOString(),
                'window_to' => $to->toISOString(),
                'window_days' => (int) $from->diffInDays($to),
                'filters' => $filters,

            ];
        });
    }

    /**
     * Realiza la paginación del listado de leads agrupados por el grupo solicitado, aplicando los filtros correspondientes.
     */
    private function paginateGroupedLeads(
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        string $groupType,
        string $groupId,
        int $perPage,
        Carbon $from,
        Carbon $to
    ) {
        return $this->buildGroupedLeadsQuery($customerId, $integrationId, $filters, $groupType, $groupId, $from, $to)
            ->paginate($perPage);
    }

    /**
     * Construye el query base para obtener los leads agrupados por el grupo solicitado, aplicando los filtros correspondientes.
     * Este método es utilizado tanto para la visualización paginada como para la exportación, garantizando consistencia entre ambos.
     * Nota: se recomienda revisar y optimizar este método si se presentan problemas de rendimiento, ya que es el núcleo de las consultas del dashboard.
     * Se han aplicado optimizaciones como selección de columnas necesarias, uso de índices (si existen), y limitación de joins según el tipo de grupo.
     * En caso de necesitar más optimizaciones, se podrían considerar técnicas avanzadas como materialized views o tablas de resumen pre-calculadas, dependiendo del volumen de datos y frecuencia de actualización requerida.
     */
    private function buildGroupedLeadsQuery(
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        string $groupType,
        string $groupId,
        Carbon $from,
        Carbon $to
    ): Builder {
        $filters = $this->sanitizeDashboardFilters($filters);

        $leadTable = (new Lead)->getTable();

        // Base: ventana por created_at del lead (mantiene consistencia con el total del dashboard)
        $q = Lead::query()->whereBetween("{$leadTable}.created_at", [$from, $to]);
        $q = $this->applyLeadScopeFilters($q, $customerId, $integrationId, $filters, $leadTable);

        // Aplica filtros del dashboard excepto la dimensiÃ³n del grupo
        $applyChannel = $groupType !== 'campaign_origin';
        $applyPlatform = $groupType !== 'plataforma';
        $applyCrm = $groupType !== 'crm_state';
        $applyQual = $groupType !== 'qualification';

        $q = $this->applyLeadDimensionFilters($q, $filters, $leadTable, $applyChannel, $applyPlatform, $applyCrm, $applyQual);

        // Filtro por grupo (excepto funnel y funnel_history, que se filtran con joins)
        if ($groupType === 'campaign_origin') {
            if ($groupId === '__NULL__') {
                $q->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.campaign_origin")
                        ->orWhere("{$leadTable}.campaign_origin", '');
                });
            } else {
                $q->where("{$leadTable}.campaign_origin", $groupId);
            }
        }

        if ($groupType === 'plataforma') {
            if ($groupId === '__NULL__') {
                $q->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.plataforma")
                        ->orWhere("{$leadTable}.plataforma", '');
                });
            } else {
                $q->where("{$leadTable}.plataforma", $groupId);
            }
        }

        if ($groupType === 'crm_state') {
            if ($groupId === '__NULL__') {
                $q->leftJoin('crm_state as csg', 'csg.id', '=', "{$leadTable}.crm_state");
                $q->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.crm_state")
                        ->orWhere("{$leadTable}.crm_state", '')
                        ->orWhere('csg.unmanaged', true);
                });
            } else {
                $q->where("{$leadTable}.crm_state", $groupId);
            }
        }

        if ($groupType === 'qualification') {
            $q->whereNotNull("{$leadTable}.crm_state")
                ->where("{$leadTable}.crm_state", '!=', '');

            if ($groupId === '__NULL__') {
                $q->whereIn("{$leadTable}.crm_state", function ($sub) {
                    $sub->from('crm_state')->select('id')->whereNull('qualification');
                });
            } else {
                $q->whereIn("{$leadTable}.crm_state", function ($sub) use ($groupId) {
                    $sub->from('crm_state')->select('id')->where('qualification', (int) $groupId);
                });
            }
        }

        // joins para mostrar nombres
        $q->leftJoin('crm_state as cs', 'cs.id', '=', "{$leadTable}.crm_state")
            ->leftJoin('qualification as ql', 'ql.id', '=', 'cs.qualification')
            ->leftJoin('campaign_objectives as co', 'co.id', '=', "{$leadTable}.campaign_objective");

        // join funnel solo si groupType=funnel
        if ($groupType === 'funnel') {
            [$funnelTable, $qualFunnelFk] = $this->resolveFunnelJoinInfo();
            if ($funnelTable && $qualFunnelFk) {
                $q->leftJoin("{$funnelTable} as fn", 'fn.id', '=', "ql.{$qualFunnelFk}");

                if ($groupId === '__LEADS__') {
                    $q->where(function ($qq) use ($leadTable) {
                        $qq->whereNull("{$leadTable}.crm_state")
                            ->orWhere("{$leadTable}.crm_state", '');
                    });
                } elseif ($groupId === '__NULL__') {
                    $q->whereNotNull("{$leadTable}.crm_state")
                        ->where("{$leadTable}.crm_state", '!=', '')
                        ->whereNull('fn.id');
                } else {
                    $q->where('fn.id', $groupId);
                }
            } else {
                $q->whereRaw('1=0');
            }
        }

        // âœ… join lead_funnel_histories solo si groupType=funnel_history
        $isHistory = false;
        $historyOrderIds = null;
        if ($groupType === 'funnel_history') {
            $historyTable = (new LeadFunnelHistory)->getTable();

            if ($historyTable && Schema::hasTable($historyTable)) {
                $q->join("{$historyTable} as lfh", function ($j) use ($leadTable, $from, $to) {
                    $j->on('lfh.lead_id', '=', "{$leadTable}.id");
                    $j->whereBetween('lfh.created_at', [$from, $to]);
                });

                // âœ… "Leads" histÃ³rico = (Lead + Lead NO Efectivo)
                if ($groupId === '__LEADS__') {
                    [$funnelTable] = $this->resolveFunnelJoinInfo();
                    $funnelTable = Schema::hasTable('funnels') ? 'funnels' : $funnelTable;

                    $leadId = $funnelTable ? $this->findFunnelIdByName($funnelTable, 'Lead') : null;
                    $noEffId = $funnelTable ? $this->findFunnelIdByName($funnelTable, 'Lead NO Efectivo') : null;
                    $ids = array_values(array_filter([$leadId, $noEffId]));

                    // Fallback por variaciones comunes (Lead/Leads)
                    if (empty($ids) && $funnelTable) {
                        $ids = DB::table($funnelTable)
                            ->whereRaw('LOWER(TRIM(name)) IN (?,?,?)', ['lead', 'leads', 'lead no efectivo'])
                            ->pluck('id')
                            ->map(fn ($v) => (string) $v)
                            ->all();
                    }

                    if (empty($ids)) {
                        $q->whereRaw('1=0');
                    } else {
                        $q->whereIn('lfh.funnel_id', $ids);
                        $historyOrderIds = $ids;
                    }
                } else {
                    $q->where('lfh.funnel_id', $groupId);
                    $historyOrderIds = [$groupId];
                }

                $q->distinct();
                $isHistory = true;
            } else {
                $q->whereRaw('1=0');
            }
        }

        // Select final
        $q->select("{$leadTable}.*")
            ->selectRaw("COALESCE(NULLIF(cs.name,''), NULLIF({$leadTable}.crm_state,''), 'Sin Estado') as crm_state_name")
            ->selectRaw("COALESCE(NULLIF(co.nombre,''), 'Sin Campaign Objective') as campaign_objective_name")
            ->selectRaw("COALESCE(NULLIF(ql.name,''), 'Sin Cualificación') as qualification_name");

        // Orden: para histÃ³rico prioriza el Ãºltimo paso por ese funnel dentro de la ventana
        if ($isHistory) {
            $historyTable = (new LeadFunnelHistory)->getTable();

            $orderIds = $historyOrderIds ?: [$groupId];

            $q->addSelect([
                'lfh_last_at' => DB::table("{$historyTable} as lfh2")
                    ->selectRaw('MAX(lfh2.created_at)')
                    ->whereColumn('lfh2.lead_id', "{$leadTable}.id")
                    ->whereIn('lfh2.funnel_id', $orderIds)
                    ->whereBetween('lfh2.created_at', [$from, $to]),
            ]);

            $q->orderByDesc('lfh_last_at');
        }

        $q->orderByDesc("{$leadTable}.created_at");

        return $q;
    }

    /*
    * Resuelve la etiqueta legible para el grupo solicitado, para mostrar en el encabezado del listado de leads.
    * Nota: se han manejado casos especiales para ciertos grupos (ej: "__NULL__ para fuentes/medios, "__LEADS__" para funnels), y se han aplicado consultas a las tablas relacionadas para obtener los nombres legibles cuando es posible.
    */
    private function resolveGroupDisplayLabel(string $groupType, string $groupId): string
    {
        if ($groupType === 'crm_state') {
            if ($groupId === '__NULL__') {
                return 'Sin Estado';
            }
            $name = DB::table('crm_state')->where('id', $groupId)->value('name');

            return $name ? (string) $name : $groupId;
        }

        if ($groupType === 'qualification') {
            if ($groupId === '__NULL__') {
                return 'Sin Cualificación';
            }
            $name = DB::table('qualification')->where('id', (int) $groupId)->value('name');

            return $name ? (string) $name : $groupId;
        }

        if ($groupType === 'campaign_origin') {
            return $groupId === '__NULL__' ? 'Sin Fuente' : $groupId;
        }

        if ($groupType === 'plataforma') {
            return $groupId === '__NULL__' ? 'Sin Medio' : $groupId;
        }

        if ($groupType === 'funnel_history') {
            if ($groupId === '__LEADS__') {
                return 'Leads';
            }
            [$funnelTable] = $this->resolveFunnelJoinInfo();
            $funnelTable = Schema::hasTable('funnels') ? 'funnels' : $funnelTable;

            if (! $funnelTable) {
                return $groupId;
            }

            $name = DB::table($funnelTable)->where('id', $groupId)->value('name');

            return $name ? (string) $name : $groupId;
        }

        if ($groupType === 'funnel') {
            if ($groupId === '__LEADS__') {
                return 'Leads';
            }
            if ($groupId === '__NULL__') {
                return 'Sin Funnel';
            }

            [$funnelTable] = $this->resolveFunnelJoinInfo();
            if (! $funnelTable) {
                return $groupId;
            }

            $name = DB::table($funnelTable)->where('id', $groupId)->value('name');

            return $name ? (string) $name : $groupId;
        }

        return 'Leads';
    }

    // =====================================================
    // âœ… Helpers / filtros
    // =====================================================
    private function resolveFunnelJoinInfo(): array
    {
        $funnelTable = null;
        if (Schema::hasTable('funnels')) {
            $funnelTable = 'funnels';
        } elseif (Schema::hasTable('funnel')) {
            $funnelTable = 'funnel';
        }

        if (! $funnelTable) {
            return [null, null];
        }

        $fk = null;
        foreach (['funnel_id', 'funnel'] as $candidate) {
            if (Schema::hasColumn('qualification', $candidate)) {
                $fk = $candidate;
                break;
            }
        }

        return [$funnelTable, $fk];
    }

    private function findFunnelIdByName(string $funnelTable, string $name): ?string
    {
        $id = DB::table($funnelTable)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->value('id');

        return $id !== null ? (string) $id : null;
    }

    private function countLeadsByFunnelId(
        Builder $base,
        array $filters,
        string $leadTable,
        string $funnelTable,
        string $qualFunnelFk,
        string $funnelId
    ): int {
        $q = clone $base;
        $q = $this->applyLeadDimensionFilters($q, $filters, $leadTable, true, true, true, true);

        return (int) $q
            ->leftJoin('crm_state as csf2', 'csf2.id', '=', "{$leadTable}.crm_state")
            ->leftJoin('qualification as qlf2', 'qlf2.id', '=', 'csf2.qualification')
            ->leftJoin("{$funnelTable} as fn2", 'fn2.id', '=', "qlf2.{$qualFunnelFk}")
            ->where('fn2.id', $funnelId)
            ->count();
    }

    private function countLeadsByFunnelIds(
        Builder $base,
        array $filters,
        string $leadTable,
        string $funnelTable,
        string $qualFunnelFk,
        array $funnelIds
    ): int {
        $q = clone $base;
        $q = $this->applyLeadDimensionFilters($q, $filters, $leadTable, true, true, true, true);

        return (int) $q
            ->leftJoin('crm_state as csf2', 'csf2.id', '=', "{$leadTable}.crm_state")
            ->leftJoin('qualification as qlf2', 'qlf2.id', '=', 'csf2.qualification')
            ->leftJoin("{$funnelTable} as fn2", 'fn2.id', '=', "qlf2.{$qualFunnelFk}")
            ->whereIn('fn2.id', $funnelIds)
            ->count();
    }

    private function sumLeadsValueByFunnelId(
        Builder $base,
        array $filters,
        string $leadTable,
        string $funnelTable,
        string $qualFunnelFk,
        string $funnelId
    ): float {
        if (! Schema::hasColumn($leadTable, 'value')) {
            return 0.0;
        }

        $q = clone $base;
        $q = $this->applyLeadDimensionFilters($q, $filters, $leadTable, true, true, true, true);

        return (float) $q
            ->leftJoin('crm_state as csf3', 'csf3.id', '=', "{$leadTable}.crm_state")
            ->leftJoin('qualification as qlf3', 'qlf3.id', '=', 'csf3.qualification')
            ->leftJoin("{$funnelTable} as fn3", 'fn3.id', '=', "qlf3.{$qualFunnelFk}")
            ->where('fn3.id', $funnelId)
            ->sum("{$leadTable}.value");
    }

    private function sumLeadsValueByFunnelIds(
        Builder $base,
        array $filters,
        string $leadTable,
        string $funnelTable,
        string $qualFunnelFk,
        array $funnelIds
    ): float {
        if (! Schema::hasColumn($leadTable, 'value')) {
            return 0.0;
        }

        $q = clone $base;
        $q = $this->applyLeadDimensionFilters($q, $filters, $leadTable, true, true, true, true);

        return (float) $q
            ->leftJoin('crm_state as csf3', 'csf3.id', '=', "{$leadTable}.crm_state")
            ->leftJoin('qualification as qlf3', 'qlf3.id', '=', 'csf3.qualification')
            ->leftJoin("{$funnelTable} as fn3", 'fn3.id', '=', "qlf3.{$qualFunnelFk}")
            ->whereIn('fn3.id', $funnelIds)
            ->sum("{$leadTable}.value");
    }

    private function applyLeadScopeFilters(
        Builder $q,
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        string $leadTable
    ): Builder {
        if ($customerId) {
            $q->where("{$leadTable}.customer_id", $customerId);
        }
        if ($integrationId) {
            $q->where("{$leadTable}.integration_id", $integrationId);
        }
        if (! empty($filters['lenguaje'])) {
            $q->where("{$leadTable}.lenguaje", $filters['lenguaje']);
        }
        if (! empty($filters['geo'])) {
            $q->where("{$leadTable}.geo", $filters['geo']);
        }

        return $q;
    }

    private function applyLeadDimensionFilters(
        Builder $q,
        array $filters,
        string $leadTable,
        bool $applyChannel,
        bool $applyPlatform,
        bool $applyCrmState,
        bool $applyQualification
    ): Builder {
        // Fuente
        if ($applyChannel && ! empty($filters['campaign_origin'])) {
            $values = array_values((array) $filters['campaign_origin']);
            $wantNull = in_array('__NULL__', $values, true);
            $real = array_values(array_filter($values, fn ($v) => $v !== '__NULL__' && $v !== null && $v !== ''));

            $q->where(function ($qq) use ($leadTable, $wantNull, $real) {
                if ($wantNull) {
                    $qq->orWhereNull("{$leadTable}.campaign_origin")
                        ->orWhere("{$leadTable}.campaign_origin", '');
                }
                if (! empty($real)) {
                    $qq->orWhereIn("{$leadTable}.campaign_origin", $real);
                }
            });
        }

        // Medio
        if ($applyPlatform && ! empty($filters['plataforma'])) {
            if ($filters['plataforma'] === '__NULL__') {
                $q->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.plataforma")
                        ->orWhere("{$leadTable}.plataforma", '');
                });
            } else {
                $q->where("{$leadTable}.plataforma", $filters['plataforma']);
            }
        }

        // CRM State
        if ($applyCrmState && ! empty($filters['crm_state'])) {
            if ($filters['crm_state'] === '__NULL__') {
                $q->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.crm_state")
                        ->orWhere("{$leadTable}.crm_state", '');
                });
            } else {
                $q->where("{$leadTable}.crm_state", $filters['crm_state']);
            }
        }

        // Qualification
        if ($applyQualification && ! empty($filters['qualification'])) {
            $q->whereIn("{$leadTable}.crm_state", function ($sub) use ($filters) {
                $sub->from('crm_state')
                    ->select('id')
                    ->where('qualification', (int) $filters['qualification']);
            });
        }

        return $q;
    }

    // =========================
    // âœ… Spend (MetaAdInsight)
    // =========================
    private function getMetaSpend(
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        Carbon $from,
        Carbon $to
    ): float {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $q = MetaAdInsight::query()->whereBetween('date_start', [$fromDate, $toDate]);

        // Filtrado por cliente e integraciÃ³n
        if ($customerId !== null || $integrationId !== null) {
            $q->whereHas('ad.adSet.campaign.account', function ($qq) use ($customerId, $integrationId) {
                if ($customerId !== null) {
                    $qq->where('customer_id', $customerId);
                }
                if ($integrationId !== null && Schema::hasColumn($qq->getModel()->getTable(), 'integration_id')) {
                    $qq->where('integration_id', $integrationId);
                }
            });
        }

        $leadTable = (new Lead)->getTable();
        [$leadCampaignCol, $metaCampaignCol] = $this->resolveLeadToMetaCampaignMapping($leadTable);

        if ($leadCampaignCol && $metaCampaignCol) {
            // Subquery para obtener los leads correspondientes a las Campañas de Meta
            $sub = Lead::query()->whereBetween("{$leadTable}.created_at", [$from, $to]);
            $sub = $this->applyLeadScopeFilters($sub, $customerId, $integrationId, $filters, $leadTable);
            $sub = $this->applyLeadDimensionFilters($sub, $filters, $leadTable, true, true, true, true);

            $sub->whereNotNull("{$leadTable}.{$leadCampaignCol}")
                ->where("{$leadTable}.{$leadCampaignCol}", '!=', '');  // Asegura que se filtren los leads con las Campañas correctas

            // RelaciÃ³n entre los leads y las Campañas de Meta
            $q->whereIn($metaCampaignCol, $sub->select("{$leadTable}.{$leadCampaignCol}")->distinct());
        } else {
            // Si no se encuentra la relaciÃ³n, revisamos si los filtros podrÃ­an incluir el gasto de Meta
            if (! $this->filtersCouldIncludeMetaSpend($filters)) {
                return 0.0;
            }
        }

        // Finalmente, suma el gasto total de las Campañas de Meta que cumplen con los filtros
        return (float) $q->sum('spend');
    }

    /*
    * Dado que no hay una relación directa garantizada entre los leads y las campañas de Meta (ya que cada cliente puede tener su propia estructura de datos), este método intenta resolver dinámicamente cómo se relacionan los leads con las campañas de Meta para poder filtrar el gasto correctamente.
    * Se buscan columnas comunes que podrían indicar esta relación (ej: meta_campaign_id, campaign_id
    en los leads, y se asume que podrían corresponder con campaign_id o campaign_name en MetaAdInsight). Si no se encuentra una relación clara, se hace un análisis de los filtros aplicados para determinar si es posible que el gasto de Meta sea relevante para la consulta.

    */
    private function resolveLeadToMetaCampaignMapping(string $leadTable): array
    {
        $idCandidates = ['meta_campaign_id', 'campaign_id', 'utm_campaign_id', 'campaignid'];

        foreach ($idCandidates as $col) {
            if (Schema::hasColumn($leadTable, $col)) {
                return [$col, 'campaign_id'];
            }
        }

        $nameCandidates = ['campaign_name', 'utm_campaign', 'utm_campaign_name', 'campaign'];

        foreach ($nameCandidates as $col) {
            if (Schema::hasColumn($leadTable, $col)) {
                return [$col, 'campaign_name'];
            }
        }

        return [null, null];
    }

    /**
     * Analiza los filtros aplicados en el dashboard para determinar si podrían incluir campañas de Meta, lo que indicaría que el gasto de Meta es relevante para la consulta aunque no se haya podido resolver una relación directa entre leads y campañas de Meta.
     * Se buscan tokens relacionados con Meta (ej: "meta", "facebook", "instagram", "fb", "ig", "whatsapp", "messenger") en los filtros de plataforma y campaña, considerando también casos donde se seleccione "Sin Medio" o "Sin Fuente".
     * Si los filtros excluyen explícitamente cualquier término relacionado con Meta, entonces se puede concluir que el gasto de Meta no es relevante y retornar 0 sin hacer la consulta a MetaAdInsight.
     */
    private function filtersCouldIncludeMetaSpend(array $filters): bool
    {
        $tokens = ['meta', 'facebook', 'fb', 'instagram', 'ig', 'whatsapp', 'messenger'];

        $hasMetaToken = function ($value) use ($tokens): bool {
            if ($value === null) {
                return false;
            }
            $v = mb_strtolower((string) $value);
            foreach ($tokens as $t) {
                if (str_contains($v, $t)) {
                    return true;
                }
            }

            return false;
        };

        if (! empty($filters['plataforma'])) {
            if ($filters['plataforma'] === '__NULL__') {
                return false;
            }
            if (! $hasMetaToken($filters['plataforma'])) {
                return false;
            }
        }

        if (! empty($filters['campaign_origin'])) {
            $values = is_array($filters['campaign_origin']) ? $filters['campaign_origin'] : [$filters['campaign_origin']];
            $values = array_values(array_filter($values, fn ($v) => $v !== null && $v !== ''));

            if (empty($values)) {
                return true;
            }

            foreach ($values as $v) {
                if ($v === '__NULL__') {
                    continue;
                }
                if ($hasMetaToken($v)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    private function makeMetricsCacheKey(
        ?int $customerId,
        ?int $integrationId,
        string $userKey,
        string $sessionId,
        array $filters,
        Carbon $from,
        Carbon $to
    ): string {
        $hash = hash('sha256', json_encode([
            'customer_id' => $customerId,
            'integration_id' => $integrationId,
            'user' => $userKey,
            'session_id' => $sessionId,
            'filters' => $filters,
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
        ]));

        return "dash:leads:range:{$hash}";
    }

    /**
     * Limpia y filtra los filtros del dashboard para asegurar que solo se incluyan parámetros válidos.
     */
    private function sanitizeDashboardFilters(array $filters): array
    {
        $allowed = Arr::only($filters, [
            'campaign_origin',
            'plataforma',
            'lenguaje',
            'geo',
            'crm_state',
            'qualification',
        ]);
        /**
         * Para 'campaign_origin', se permite un array de valores, pero se filtran los valores vacíos o nulos, y si después de eso no queda ningún valor, se elimina el filtro por completo. Esto asegura que no se apliquen filtros inválidos que podrían afectar los resultados de las métricas.
         */

        if (array_key_exists('campaign_origin', $allowed)) {
            $arr = array_values((array) $allowed['campaign_origin']);
            $arr = array_filter($arr, fn ($v) => $v !== null && $v !== '');
            sort($arr);

            if (empty($arr)) {
                unset($allowed['campaign_origin']);
            } else {
                $allowed['campaign_origin'] = $arr;
            }
        }

        foreach (['plataforma', 'crm_state', 'qualification', 'lenguaje', 'geo'] as $k) {
            if (isset($allowed[$k]) && ($allowed[$k] === null || $allowed[$k] === '')) {
                unset($allowed[$k]);
            }
        }

        ksort($allowed);

        return $allowed;
    }

 /**
  * Construye la estructura de datos necesaria para renderizar la vista del dashboard, a partir de las métricas calculadas y los parámetros de la consulta.
  * Este método se encarga de transformar los datos crudos de las métricas en la información específica que se muestra en cada sección del dashboard, como los cards de canales/plataformas,
  */
    private function buildDashboardViewData(
        Request $request,
        ?int $customerId,
        ?int $integrationId,
        $selectedCustomer,
        $customers,
        array $metric,
        Carbon $from,
        Carbon $to,
        string $nowMax
    ): array {
        $fromValue = $from->format('Y-m-d\TH:i');
        $toValue = $to->format('Y-m-d\TH:i');
        $periodLabel = $from->format('Y-m-d H:i').' -> '.$to->format('Y-m-d H:i');

        $channels = $metric['channels'] ?? [];
        $platforms = $metric['platforms'] ?? [];

        $totalLeads = (int) ($metric['count'] ?? 0);

        // Cards (ya con porcentajes + URLs)
        $baseCardsQuery = Arr::except($request->query(), ['crm_state', 'qualification', 'group_type', 'group_id', 'page']);

        $funnelsFromMetric = $metric['funnels'] ?? [];

        // âœ… Unificar "Sin Funnel" + "Lead NO Efectivo" => "Leads"
        $funnelByName = [];
        foreach ($funnelsFromMetric as $f) {
            $k = mb_strtolower(trim((string) ($f['name'] ?? '')));
            if ($k === '') {
                continue;
            }

            if (! isset($funnelByName[$k])) {
                $funnelByName[$k] = $f;
                continue;
            }

            $funnelByName[$k]['count'] = (int) ($funnelByName[$k]['count'] ?? 0) + (int) ($f['count'] ?? 0);
        }


        // âœ… Orden requerido: Leads, Respondidos, Oportunidades, Ventas
        $funnelRaw = [];

        foreach (['Leads', 'Respondidos', 'Lead NO Efectivo', 'Oportunidades', 'Ventas'] as $wanted) {
            $k = mb_strtolower($wanted);
            if (isset($funnelByName[$k])) {
                $funnelRaw[] = [
                    'id' => (string) ($funnelByName[$k]['id'] ?? ''),
                    'name' => (string) ($funnelByName[$k]['name'] ?? $wanted),
                    'count' => (int) ($funnelByName[$k]['count'] ?? 0),
                ];
                unset($funnelByName[$k]);
            }
        }

        // Resto (si existe) al final, respetando orden original
        foreach ($funnelsFromMetric as $f) {
            $k = mb_strtolower(trim((string) ($f['name'] ?? '')));
            if ($k !== '' && isset($funnelByName[$k])) {
                $funnelRaw[] = [
                    'id' => (string) ($f['id'] ?? ''),
                    'name' => (string) ($f['name'] ?? ''),
                    'count' => (int) ($f['count'] ?? 0),
                ];
                unset($funnelByName[$k]);
            }
        }
        $crmRaw = array_merge([[
            'id' => '__NULL__',
            'name' => 'Sin Estado',
            'count' => (int) ($metric['pending_count'] ?? 0),
        ]], $metric['crm_states'] ?? []);

        $qualRaw = array_map(function ($q) {
            return [
                'id' => ($q['id'] ?? null) === null ? '__NULL__' : $q['id'],
                'name' => $q['name'] ?? 'Sin Cualificación',
                'count' => (int) ($q['count'] ?? 0),
            ];
        }, $metric['qualifications'] ?? []);

        $mkCards = function (array $raw, string $groupType) use ($totalLeads, $baseCardsQuery) {
            return array_map(function ($card) use ($totalLeads, $groupType, $baseCardsQuery) {
                $count = (int) ($card['count'] ?? 0);
                $pct = $totalLeads > 0 ? (int) round(($count / $totalLeads) * 100) : 0;
                $url = route('dashboard.leads.list', array_merge($baseCardsQuery, [
                    'group_type' => $groupType,
                    'group_id' => $card['id'],
                ]));

                return [
                    'id' => $card['id'],
                    'name' => $card['name'] ?? '-',
                    'count' => $count,
                    'pct' => $pct,
                    'url' => $url,
                ];
            }, $raw);
        };

        $cardsFunnels = $mkCards($funnelRaw, 'funnel');
        $cardsQual = $mkCards($qualRaw, 'qualification');
        $cardsCrm = $mkCards($crmRaw, 'crm_state');

        // âœ… HistÃ³rico Leads en el Funnel (desde LeadFunnelHistory)
        $funnelHistoryFromMetric = $metric['funnels_history'] ?? [];

        // âœ… Unificar "Lead" + "Lead NO Efectivo" => "Leads" (sin perder el click a listado)
        $historyLeadCount = 0;
        $historyNoEfectivoCount = 0;
        $historyRemaining = [];

        foreach ($funnelHistoryFromMetric as $f) {
            $n = mb_strtolower(trim((string) ($f['name'] ?? '')));
            if ($n === 'lead' || $n === 'leads') {
                $historyLeadCount += (int) ($f['count'] ?? 0);

                continue;
            }
            if ($n === mb_strtolower('Lead NO Efectivo')) {
                $historyNoEfectivoCount += (int) ($f['count'] ?? 0);

                continue;
            }
            $historyRemaining[] = $f;
        }

        $historyLeadsCount = $historyLeadCount + $historyNoEfectivoCount;

        // Orden sugerido: Leads, Respondidos, Oportunidades, Ventas, resto
        $historyByName = [];
        foreach ($historyRemaining as $f) {
            $k = mb_strtolower(trim((string) ($f['name'] ?? '')));
            if ($k !== '') {
                $historyByName[$k] = $f;
            }
        }

        $funnelHistoryRaw = [];

        // 1) Leads (combinado)
        $funnelHistoryRaw[] = [
            'id' => '__LEADS__',
            'name' => 'Leads',
            'count' => $historyLeadsCount,
        ];

        foreach (['Respondidos', 'Oportunidades', 'Ventas'] as $wanted) {
            $k = mb_strtolower($wanted);
            if (isset($historyByName[$k])) {
                $funnelHistoryRaw[] = [
                    'id' => (string) ($historyByName[$k]['id'] ?? ''),
                    'name' => (string) ($historyByName[$k]['name'] ?? $wanted),
                    'count' => (int) ($historyByName[$k]['count'] ?? 0),
                ];
                unset($historyByName[$k]);
            }
        }

        foreach ($historyRemaining as $f) {
            $k = mb_strtolower(trim((string) ($f['name'] ?? '')));
            if ($k !== '' && isset($historyByName[$k])) {
                $funnelHistoryRaw[] = [
                    'id' => (string) ($f['id'] ?? ''),
                    'name' => (string) ($f['name'] ?? ''),
                    'count' => (int) ($f['count'] ?? 0),
                ];
                unset($historyByName[$k]);
            }
        }

        $cardsFunnelsHistory = $mkCards($funnelHistoryRaw, 'funnel_history');

        // Calificados / Ventas
        $baseClick = Arr::except($request->query(), ['crm_state', 'qualification', 'group_type', 'group_id', 'page']);
        $pendingUrl = route('dashboard.leads.list', array_merge($baseClick, [
            'group_type' => 'crm_state',
            'group_id' => '__NULL__',
        ]));
        $qualifiedFunnelId = $metric['qualified_funnel_id'] ?? null;
        $salesFunnelId = $metric['sales_funnel_id'] ?? null;

        $qualifiedUrl = $qualifiedFunnelId
            ? route('dashboard.leads.list', array_merge($baseClick, ['group_type' => 'funnel', 'group_id' => $qualifiedFunnelId]))
            : null;

        $salesUrl = $salesFunnelId
            ? route('dashboard.leads.list', array_merge($baseClick, ['group_type' => 'funnel', 'group_id' => $salesFunnelId]))
            : null;

        // âœ… Leads NO Efectivos (por Qualification)
        $notEffectiveFunnelId = $metric['not_effective_funnel_id'] ?? null;
        $notEffectiveUrl = $notEffectiveFunnelId
            ? route('dashboard.leads.list', array_merge($baseClick, ['group_type' => 'funnel', 'group_id' => $notEffectiveFunnelId]))
            : null;

        // âœ… Valor total ventas (suma de leads.value para funnel Ventas)
        $salesValueSum = (float) ($metric['sales_value_sum'] ?? 0);
        $salesValueFormatted = '$ '.number_format($salesValueSum, 0, ',', '.');

        // Donuts (datos + baseUrl para JS)
        $channelsDonut = $this->buildDonutChartData($channels, 'campaign_origin');
        $platformsDonut = $this->buildDonutChartData($platforms, 'plataforma');

        $baseForChannels = route('dashboard.leads.list', Arr::except($request->query(), [
            'campaign_origin', 'crm_state', 'qualification', 'group_type', 'group_id', 'page',
        ]));

        $baseForPlatforms = route('dashboard.leads.list', Arr::except($request->query(), [
            'plataforma', 'crm_state', 'qualification', 'group_type', 'group_id', 'page',
        ]));

        // Options selects (labels ya listos)
        $selectedChannel = (string) $request->query('campaign_origin', '');
        $selectedPlatform = (string) $request->query('plataforma', '');

        $channelOptions = [];
        foreach (array_keys($channels) as $k) {
            $channelOptions[] = [
                'value' => $k,
                'label' => $k === '__NULL__' ? 'Sin Fuente' : $k,
                'selected' => ((string) $k === $selectedChannel),
            ];
        }

        $platformOptions = [];
        foreach (array_keys($platforms) as $k) {
            $platformOptions[] = [
                'value' => $k,
                'label' => $k === '__NULL__' ? 'Sin Medio' : $k,
                'selected' => ((string) $k === $selectedPlatform),
            ];
        }

        $customerOptions = [];
        foreach ($customers as $c) {
            $customerOptions[] = [
                'value' => $c->id,
                'label' => $c->name." (ID: {$c->id})",
                'selected' => ((string) $customerId === (string) $c->id),
            ];
        }

        $spendValue = (float) ($metric['spend'] ?? 0);
        $spendFormatted = '$ '.number_format($spendValue, 2, ',', '.');

        $metaCampaigns = $this->buildMetaCampaignsTableData(
            $customerId,
            $integrationId,
            $metric['filters'] ?? [],
            $from,
            $to,
            (string) $request->session()->getId()
        );

        $metaAdSets = $this->buildMetaAdSetsTableData(
            $customerId,
            $integrationId,
            $metric['filters'] ?? [],
            $from,
            $to,
            (string) $request->session()->getId()
        );

        $metaAds = $this->buildMetaAdsTableData(
            $customerId,
            $integrationId,
            $metric['filters'] ?? [],
            $from,
            $to,
            (string) $request->session()->getId()
        );

        $summaryCards = [
            [
                'title' => 'Leads no Efectivos',
                'value' => (int) ($metric['not_effective_count'] ?? 0),
                'url' => $notEffectiveUrl,
                'missing' => 'No existe un funnel llamado "Lead NO Efectivo".',
                'cta' => 'Ver lista',
            ],
            [
                'title' => 'Leads calificados',
                'value' => (int) ($metric['qualified_count'] ?? 0),
                'url' => $qualifiedUrl,
                'missing' => 'No existe un funnel llamado "Oportunidades" o "Respondidos".',
                'cta' => 'Ver lista',
            ],
            [
                'title' => 'Leads con ventas',
                'value' => (int) ($metric['sales_count'] ?? 0),
                'url' => $salesUrl,
                'missing' => 'No existe un funnel llamado "Ventas".',
                'cta' => 'Ver lista',
                'extra_label' => 'Valor de ventas',
                'extra_value' => $salesValueFormatted,
            ],
        ];

        $metaSections = [
            [
                'title' => 'Campaña de Meta',
                'empty_note' => 'Sin datos de campañas en el periodo.',
                'footnote' => 'Mostrando hasta 200 campañas ordenadas por costo.',
                'table' => $metaCampaigns,
            ],
            [
                'title' => 'Grupo de anuncios de Meta',
                'empty_note' => 'Sin datos de grupos de anuncios en el periodo.',
                'footnote' => 'Mostrando hasta 200 grupos ordenados por costo.',
                'table' => $metaAdSets,
            ],
            [
                'title' => 'Anuncio de Meta',
                'empty_note' => 'Sin datos de anuncios en el periodo.',
                'footnote' => 'Mostrando hasta 200 anuncios ordenados por costo.',
                'table' => $metaAds,
            ],
        ];

        return [
            'header' => [
                'selected_customer_name' => $selectedCustomer?->name ?? 'Todos los clientes',
                'selected_customer_id' => $selectedCustomer?->id,
            ],
            'filters' => [
                'action' => route('dashboard.leads'),
                'integration_id' => $integrationId,
                'customer_id' => $customerId,
                'from_value' => $fromValue,
                'to_value' => $toValue,
                'now_max' => $nowMax,
                'customer_options' => $customerOptions,
                'channel_options' => $channelOptions,
                'platform_options' => $platformOptions,
            ],
            'summary' => [
                'count' => (int) ($metric['count'] ?? 0),
                'managed' => (int) ($metric['managed_count'] ?? 0),
                'pending' => (int) ($metric['pending_count'] ?? 0),
                'pending_url' => $pendingUrl,
                'qualified' => (int) ($metric['qualified_count'] ?? 0),
                'not_effective' => (int) ($metric['not_effective_count'] ?? 0),
                'sales' => (int) ($metric['sales_count'] ?? 0),
                'sales_value_formatted' => $salesValueFormatted,
                'period_label' => $periodLabel,
                'calculated_at' => $metric['calculated_at'] ?? '-',
                'spend_formatted' => $spendFormatted,
            ],
            'summary_cards' => $summaryCards,
            'totals' => [
                'total_leads' => $totalLeads,
            ],
            'special' => [
                'not_effective_url' => $notEffectiveUrl,
                'qualified_url' => $qualifiedUrl,
                'sales_url' => $salesUrl,
                'not_effective_missing' => 'No existe un funnel llamado "Lead NO Efectivo".',
                'qualified_missing' => 'No existe un funnel llamado "Oportunidades" o "Respondidos".',
                'sales_missing' => 'No existe un funnel llamado "Ventas".',
            ],
            'donuts' => [
                'channels' => array_merge($channelsDonut, [
                    'base_url' => $baseForChannels,
                    'group_type' => 'campaign_origin',
                ]),
                'platforms' => array_merge($platformsDonut, [
                    'base_url' => $baseForPlatforms,
                    'group_type' => 'plataforma',
                ]),
            ],
            'cards' => [
                'funnels' => $cardsFunnels,
                'funnels_history' => $cardsFunnelsHistory,
                'qualifications' => $cardsQual,
                'crm_states' => $cardsCrm,
            ],
            'tables' => [
                'meta_campaigns' => $metaCampaigns,
                'meta_ad_sets' => $metaAdSets,
                'meta_ads' => $metaAds,
            ],
            'meta_sections' => $metaSections,
        ];
    }

    private function buildDonutChartData(array $data, string $dimension): array
    {
        if (empty($data)) {
            return ['keys' => [], 'labels' => [], 'values' => [], 'pairs' => [], 'total' => 0];
        }

        arsort($data);
        $topCount = max(self::DONUT_MAX_ITEMS - 1, 1);
        $top = array_slice($data, 0, $topCount, true);
        $rest = array_slice($data, $topCount, null, true);

        if (! empty($rest)) {
            $top['__OTHER__'] = array_sum($rest);
        }

        $keys = array_keys($top);
        $values = array_values($top);

        $labels = array_map(function ($k) use ($dimension) {
            if ($k === '__OTHER__') {
                return 'Otros';
            }
            if ($k === '__NULL__') {
                return $dimension === 'campaign_origin' ? 'Sin Fuente' : 'Sin Medio';
            }

            return (string) $k;
        }, $keys);

        $pairs = [];
        foreach ($keys as $i => $k) {
            $pairs[] = ['key' => $k, 'label' => $labels[$i], 'count' => (int) $values[$i]];
        }

        return [
            'keys' => $keys,
            'labels' => $labels,
            'values' => $values,
            'pairs' => $pairs,
            'total' => array_sum($values),
        ];
    }

    // ================================
    // âœ… Tabla: Campañas Meta (Insights)
    // ================================

    private function buildMetaCampaignsTableData(
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        Carbon $from,
        Carbon $to,
        string $sessionId
    ): array {
        try {
            $insightsTable = (new MetaAdInsight)->getTable();

            if (! Schema::hasTable($insightsTable)) {
                return [
                    'enabled' => false,
                    'note' => 'No existe la tabla meta_ad_insights.',
                    'columns' => [],
                    'rows' => [],
                ];
            }

            $userKey = (string) (auth()->id() ?? 'guest');
            $key = 'dash_meta_campaigns:'.sha1(json_encode([
                'customer_id' => $customerId,
                'integration_id' => $integrationId,
                'user' => $userKey,
                'session' => $sessionId,
                'filters' => $filters,
                'from' => $from->format('Y-m-d H:i'),
                'to' => $to->format('Y-m-d H:i'),
            ]));

            return Cache::remember($key, now()->addSeconds(60), function () use ($customerId, $integrationId, $filters, $from, $to) {
                $fromDate = $from->toDateString();
                $toDate = $to->toDateString();

                // -------------------------
                // 1) INSIGHTS (resumen por Campaña)
                // -------------------------
                $q = MetaAdInsight::query()
                    ->whereBetween('date_start', [$fromDate, $toDate]);

                // Filtrado por cliente / integraciÃ³n a travÃ©s de la jerarquÃ­a: Insight -> Ad -> AdSet -> Campaign -> Account
                if ($customerId !== null || $integrationId !== null) {
                    $q->whereHas('ad.adSet.campaign.account', function ($qq) use ($customerId, $integrationId) {
                        if ($customerId !== null) {
                            $qq->where('customer_id', $customerId);
                        }
                        if ($integrationId !== null && Schema::hasColumn($qq->getModel()->getTable(), 'integration_id')) {
                            $qq->where('integration_id', $integrationId);
                        }
                    });
                }

                // Solo lo que necesitamos para la tabla (y en el orden solicitado)
                $rows = (clone $q)
                    ->selectRaw('
                    account_name,
                    account_id,
                    campaign_id,
                    campaign_name,
                    SUM(spend) as spend,
                    MAX(status) as status
                ')
                    ->groupBy('account_id', 'account_name', 'campaign_id', 'campaign_name')
                    ->orderByDesc('spend')
                    ->limit(200)
                    ->get();

                // -------------------------
                // 2) LEADS ENTRANTES y LEADS CALIFICADOS (por Campaña)
                // RelaciÃ³n: Lead.meta_id_ad -> MetaAd.meta_ad_id -> MetaAdSet -> MetaCampaign
                // -------------------------
                $leadTable = (new Lead)->getTable();

                $leadCountsIncoming = [];
                $leadCountsQualified = [];
                $leadNote = null;

                if (! Schema::hasColumn($leadTable, 'meta_id_ad')) {
                    $leadNote = 'âš ï¸ Falta la columna leads.meta_id_ad. No se puede calcular Leads entrantes / calificados.';
                } else {
                    $metaAdsTable = (new \App\Models\MetaAd)->getTable();
                    $metaAdSetsTable = (new \App\Models\MetaAdSet)->getTable();
                    $metaCampaignsTable = (new \App\Models\MetaCampaign)->getTable();

                    // Base de leads: mismos filtros y misma ventana del dashboard (leads.created_at)
                    $sub = Lead::query()->whereBetween("{$leadTable}.created_at", [$from, $to]);
                    $sub = $this->applyLeadScopeFilters($sub, $customerId, $integrationId, $filters, $leadTable);
                    $sub = $this->applyLeadDimensionFilters($sub, $filters, $leadTable, true, true, true, true);

                    // Leads entrantes por Campaña
                    $leadCountsIncoming = [];
                    $leadCountsQualified = [];

                    // ValidaciÃ³n mÃ­nima de tablas/columnas para joins
                    if (! Schema::hasTable($metaAdsTable) || ! Schema::hasTable($metaAdSetsTable) || ! Schema::hasTable($metaCampaignsTable)) {
                        $leadNote = 'âš ï¸ No existen las tablas meta_ads / meta_ad_sets / meta_campaigns. No se puede calcular Leads entrantes / calificados.';
                    } elseif (! Schema::hasColumn($metaAdsTable, 'meta_ad_id') || ! Schema::hasColumn($metaAdsTable, 'meta_ad_set_id')) {
                        $leadNote = 'âš ï¸ Faltan columnas en meta_ads (meta_ad_id / meta_ad_set_id). No se puede calcular Leads entrantes / calificados.';
                    } elseif (! Schema::hasColumn($metaAdSetsTable, 'meta_campaign_id')) {
                        $leadNote = 'âš ï¸ Falta la columna meta_ad_sets.meta_campaign_id. No se puede calcular Leads entrantes / calificados.';
                    } else {
                        $hasMasExternal = Schema::hasColumn($metaAdSetsTable, 'meta_ad_set_id');         // externo (string)
                        $hasMcExternal = Schema::hasColumn($metaCampaignsTable, 'meta_campaign_id');     // externo (string)

                        // Si existe meta_campaign_id, lo usamos para empatar con MetaAdInsight.campaign_id
                        $campaignIdExpr = $hasMcExternal ? 'mc.meta_campaign_id' : 'mc.id';

                        $joinMas = function ($join) use ($hasMasExternal) {
                            // Soporta ambos esquemas:
                            // 1) ma.meta_ad_set_id -> mas.id (FK interno)
                            // 2) ma.meta_ad_set_id -> mas.meta_ad_set_id (ID externo)
                            $join->on('mas.id', '=', 'ma.meta_ad_set_id');
                            if ($hasMasExternal) {
                                $join->orOn('mas.meta_ad_set_id', '=', 'ma.meta_ad_set_id');
                            }
                        };

                        $joinMc = function ($join) use ($hasMcExternal) {
                            // Soporta ambos esquemas:
                            // 1) mas.meta_campaign_id -> mc.id (FK interno)
                            // 2) mas.meta_campaign_id -> mc.meta_campaign_id (ID externo)
                            $join->on('mc.id', '=', 'mas.meta_campaign_id');
                            if ($hasMcExternal) {
                                $join->orOn('mc.meta_campaign_id', '=', 'mas.meta_campaign_id');
                            }
                        };

                        // Leads entrantes (COUNT DISTINCT para evitar duplicados por joins OR)
                        $leadCountsIncoming = (clone $sub)
                            ->whereNotNull("{$leadTable}.meta_id_ad")
                            ->where("{$leadTable}.meta_id_ad", '!=', '')
                            ->join("{$metaAdsTable} as ma", 'ma.meta_ad_id', '=', "{$leadTable}.meta_id_ad")
                            ->join("{$metaAdSetsTable} as mas", $joinMas)
                            ->join("{$metaCampaignsTable} as mc", $joinMc)
                            ->whereRaw("{$campaignIdExpr} IS NOT NULL")
                            ->whereRaw("{$campaignIdExpr} != ''")
                            ->selectRaw("{$campaignIdExpr} as campaign_id, COUNT(DISTINCT {$leadTable}.id) as leads")
                            ->groupBy(DB::raw($campaignIdExpr))
                            ->pluck('leads', 'campaign_id')
                            ->toArray();

                        // Leads calificados: excluye Qualifications especÃ­ficas
                        $excluded = [mb_strtolower('Lead NO Efectivo'), mb_strtolower('Sin Gestionar'), mb_strtolower('N/A')];

                        $qualifiedCampaignExpr = $hasMcExternal ? 'mcq.meta_campaign_id' : 'mcq.id';

                        $leadCountsQualified = (clone $sub)
                            ->whereNotNull("{$leadTable}.meta_id_ad")
                            ->where("{$leadTable}.meta_id_ad", '!=', '')
                            ->join("{$metaAdsTable} as maq", 'maq.meta_ad_id', '=', "{$leadTable}.meta_id_ad")
                            ->join("{$metaAdSetsTable} as masq", function ($join) use ($hasMasExternal) {
                                $join->on('masq.id', '=', 'maq.meta_ad_set_id');
                                if ($hasMasExternal) {
                                    $join->orOn('masq.meta_ad_set_id', '=', 'maq.meta_ad_set_id');
                                }
                            })
                            ->join("{$metaCampaignsTable} as mcq", function ($join) use ($hasMcExternal) {
                                $join->on('mcq.id', '=', 'masq.meta_campaign_id');
                                if ($hasMcExternal) {
                                    $join->orOn('mcq.meta_campaign_id', '=', 'masq.meta_campaign_id');
                                }
                            })
                            ->join('crm_state as csq', 'csq.id', '=', "{$leadTable}.crm_state")
                            ->join('qualification as qlq', 'qlq.id', '=', 'csq.qualification')
                            ->whereRaw("{$qualifiedCampaignExpr} IS NOT NULL")
                            ->whereRaw("{$qualifiedCampaignExpr} != ''")
                            ->whereNotNull('qlq.name')
                            ->whereRaw('LOWER(TRIM(qlq.name)) NOT IN (?,?,?)', $excluded)
                            ->selectRaw("{$qualifiedCampaignExpr} as campaign_id, COUNT(DISTINCT {$leadTable}.id) as leads")
                            ->groupBy(DB::raw($qualifiedCampaignExpr))
                            ->pluck('leads', 'campaign_id')
                            ->toArray();

                        $leadNote = $leadNote ?: ' Leads entrantes/calificados calculados desde leads.meta_id_ad - meta_ads(meta_ad_id) - meta_ad_sets(meta_ad_set_id/id) - meta_campaigns(meta_campaign_id/id).';
                    }
                }

                $fmtInt = fn ($n) => number_format((int) $n, 0, ',', '.');
                $fmtMoney = fn ($n) => '$ '.number_format((float) $n, 2, ',', '.');

                // âœ… Campos solicitados
                $columns = [
                    ['key' => 'nombre',              'label' => 'Nombre Campaña'],
                    ['key' => 'costo',               'label' => 'Costo Campaña'],
                    ['key' => 'leads',               'label' => 'Leads Campaña'],
                    ['key' => 'leads_calificados',   'label' => 'Leads calificados Campaña'],
                    ['key' => 'leads_no_calificados', 'label' => 'Leads no calificados Campaña'],
                    ['key' => 'roas',                'label' => 'ROAS Campaña'],
                ];

                $outRows = [];
                foreach ($rows as $r) {
                    $campaignId = (string) ($r->campaign_id ?? '');
                    $incoming = isset($leadCountsIncoming[$campaignId]) ? (int) $leadCountsIncoming[$campaignId] : 0;
                    $qualified = isset($leadCountsQualified[$campaignId]) ? (int) $leadCountsQualified[$campaignId] : 0;

                    $noCal = max(0, $incoming - $qualified);
                    $spend = (float) ($r->spend ?? 0);
                    $roas = $incoming > 0 ? round($spend / $incoming, 2) : null;

                    $outRows[] = [
                        'nombre' => (string) ($r->campaign_name ?? '-'),
                        'costo' => $fmtMoney($spend),
                        'leads' => $fmtInt($incoming),
                        'leads_calificados' => $fmtInt($qualified),
                        'leads_no_calificados' => $fmtInt($noCal),
                        'roas' => $roas === null ? '-' : $fmtMoney($roas),
                    ];
                }

                return [
                    'enabled' => count($outRows) > 0,
                    'note' => $leadNote,
                    'columns' => $columns,
                    'rows' => $outRows,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'enabled' => false,
                'note' => 'Error cargando Campañas: '.$e->getMessage(),
                'columns' => [],
                'rows' => [],
            ];
        }
    }

    // ================================
    // âœ… Tablas Meta: Grupos de anuncios (AdSets) y Anuncios (Ads)
    // (mismas fechas/filtros del dashboard; sin cambiar la lÃ³gica existente)
    // ================================

    private function buildMetaAdSetsTableData(
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        Carbon $from,
        Carbon $to,
        string $sessionId
    ): array {
        try {
            $insightsTable = (new MetaAdInsight)->getTable();
            $leadTable = (new Lead)->getTable();

            $metaAdsTable = (new \App\Models\MetaAd)->getTable();
            $metaAdSetsTable = (new \App\Models\MetaAdSet)->getTable();

            if (! Schema::hasTable($insightsTable) || ! Schema::hasTable($metaAdsTable) || ! Schema::hasTable($metaAdSetsTable)) {
                return ['enabled' => false, 'note' => 'No existen tablas necesarias (meta_ad_insights / meta_ads / meta_ad_sets).', 'columns' => [], 'rows' => []];
            }

            if (! Schema::hasColumn($insightsTable, 'spend') || ! Schema::hasColumn($insightsTable, 'date_start') || ! Schema::hasColumn($insightsTable, 'meta_ad_id')) {
                return ['enabled' => false, 'note' => 'Faltan columnas en meta_ad_insights (spend / date_start / meta_ad_id).', 'columns' => [], 'rows' => []];
            }

            if (! Schema::hasColumn($leadTable, 'meta_id_ad') || ! Schema::hasColumn($metaAdsTable, 'meta_ad_id') || ! Schema::hasColumn($metaAdsTable, 'meta_ad_set_id')) {
                return ['enabled' => false, 'note' => 'Faltan columnas para calcular leads por grupo (leads.meta_id_ad / meta_ads.meta_ad_id / meta_ads.meta_ad_set_id).', 'columns' => [], 'rows' => []];
            }

            $hasExternalId = Schema::hasColumn($metaAdSetsTable, 'meta_ad_set_id');
            $hasName = Schema::hasColumn($metaAdSetsTable, 'name');

            $userKey = (string) (auth()->id() ?? 'guest');
            $cacheKey = 'dash_meta_ad_sets_v3:'.sha1(json_encode([
                'customer_id' => $customerId,
                'integration_id' => $integrationId,
                'user' => $userKey,
                'session' => $sessionId,
                'filters' => $filters,
                'from' => $from->format('Y-m-d H:i'),
                'to' => $to->format('Y-m-d H:i'),
            ]));

            return Cache::remember($cacheKey, now()->addSeconds(60), function () use (
                $customerId, $integrationId, $filters, $from, $to,
                $insightsTable, $leadTable, $metaAdsTable, $metaAdSetsTable,
                $hasExternalId, $hasName
            ) {
                $fromDate = $from->toDateString();
                $toDate = $to->toDateString();

                $idExpr = $hasExternalId ? 'mas.meta_ad_set_id' : 'mas.id';
                $nameExpr = $hasName ? 'mas.name' : 'mas.id';

                // -------------------------
                // 1) Spend por AdSet (desde insights)
                // -------------------------
                $spendQ = DB::table("{$insightsTable} as i")
                    ->whereBetween('i.date_start', [$fromDate, $toDate])
                    ->join("{$metaAdsTable} as ma", 'ma.id', '=', 'i.meta_ad_id')
                    ->join("{$metaAdSetsTable} as mas", function ($join) use ($hasExternalId) {
                        $join->on('mas.id', '=', 'ma.meta_ad_set_id');
                        if ($hasExternalId) {
                            $join->orOn('mas.meta_ad_set_id', '=', 'ma.meta_ad_set_id');
                        }
                    });

                // âœ… Filtrado por cliente (Customer -> MetaAdAccount -> MetaAdInsight)
                if ($customerId !== null || $integrationId !== null) {
                    $accTable = (new MetaAdAccount)->getTable();
                    $accQ = MetaAdAccount::query();

                    if ($customerId !== null) {
                        $accQ->where('customer_id', $customerId);
                    }
                    if ($integrationId !== null && Schema::hasColumn($accTable, 'integration_id')) {
                        $accQ->where('integration_id', $integrationId);
                    }

                    $accIds = (clone $accQ)->pluck('id')->map(fn ($v) => (string) $v)->all();
                    $accMetaIds = Schema::hasColumn($accTable, 'meta_account_id')
                        ? (clone $accQ)->pluck('meta_account_id')->filter()->map(fn ($v) => (string) $v)->all()
                        : [];
                    $allowed = array_values(array_unique(array_merge($accIds, $accMetaIds)));

                    if (! empty($allowed) && Schema::hasColumn($insightsTable, 'account_id')) {
                        $spendQ->whereIn('i.account_id', $allowed);
                    } else {
                        $spendQ->whereRaw('1=0');
                    }
                }

                $spendRows = $spendQ
                    ->selectRaw("
                    mas.id as pk,
                    {$idExpr} as meta_id,
                    {$nameExpr} as nombre,
                    SUM(i.spend) as costo_anuncio
                ")
                    ->groupBy('pk', DB::raw($idExpr), DB::raw($nameExpr))
                    ->orderByDesc('costo_anuncio')
                    ->limit(200)
                    ->get();

                $byPk = [];
                foreach ($spendRows as $r) {
                    $pk = (int) $r->pk;
                    $byPk[$pk] = [
                        'pk' => $pk,
                        'meta_id' => (string) ($r->meta_id ?? $pk),
                        'nombre' => (string) ($r->nombre ?? $pk),
                        'costo_anuncio' => (float) ($r->costo_anuncio ?? 0),
                    ];
                }

                // -------------------------
                // 2) Leads por AdSet (desde leads)
                // -------------------------
                $excluded = ['lead no efectivo', 'sin gestionar', 'n/a'];
                $excludedSql = "'".implode("','", array_map(fn ($s) => str_replace("'", "''", $s), $excluded))."'";

                $base = Lead::query()->whereBetween("{$leadTable}.created_at", [$from, $to]);
                $base = $this->applyLeadScopeFilters($base, $customerId, $integrationId, $filters, $leadTable);
                $base = $this->applyLeadDimensionFilters($base, $filters, $leadTable, true, true, true, true);

                $leadAgg = (clone $base)
                    ->whereNotNull("{$leadTable}.meta_id_ad")
                    ->where("{$leadTable}.meta_id_ad", '!=', '')
                    ->join("{$metaAdsTable} as maL", 'maL.meta_ad_id', '=', "{$leadTable}.meta_id_ad")
                    ->join("{$metaAdSetsTable} as masL", function ($join) use ($hasExternalId) {
                        $join->on('masL.id', '=', 'maL.meta_ad_set_id');
                        if ($hasExternalId) {
                            $join->orOn('masL.meta_ad_set_id', '=', 'maL.meta_ad_set_id');
                        }
                    })
                    ->leftJoin('crm_state as cs', 'cs.id', '=', "{$leadTable}.crm_state")
                    ->leftJoin('qualification as q', 'q.id', '=', 'cs.qualification')
                    ->selectRaw("
                    masL.id as pk,
                    COUNT(DISTINCT {$leadTable}.id) as total_leads,
                    COUNT(DISTINCT IF(
                        q.name IS NOT NULL AND LOWER(TRIM(q.name)) NOT IN ({$excludedSql}),
                        {$leadTable}.id,
                        NULL
                    )) as leads_calificados
                ")
                    ->groupBy('pk')
                    ->get();

                $totals = [];
                $qualified = [];
                foreach ($leadAgg as $r) {
                    $pk = (int) $r->pk;
                    $totals[$pk] = (int) ($r->total_leads ?? 0);
                    $qualified[$pk] = (int) ($r->leads_calificados ?? 0);
                }

                // Completa nombres para adsets con leads pero sin spend
                $missingPks = array_diff(array_keys($totals), array_keys($byPk));
                if (! empty($missingPks)) {
                    $cols = [
                        'id as pk',
                        DB::raw(($hasExternalId ? 'meta_ad_set_id' : 'id').' as meta_id'),
                        DB::raw(($hasName ? 'name' : 'id').' as nombre'),
                    ];

                    $extra = DB::table($metaAdSetsTable)->whereIn('id', $missingPks)->select($cols)->get();
                    foreach ($extra as $r) {
                        $pk = (int) $r->pk;
                        $byPk[$pk] = [
                            'pk' => $pk,
                            'meta_id' => (string) ($r->meta_id ?? $pk),
                            'nombre' => (string) ($r->nombre ?? $pk),
                            'costo_anuncio' => 0.0,
                        ];
                    }
                }

                // Orden: spend desc, luego leads desc
                uasort($byPk, function ($a, $b) use ($totals) {
                    $as = (float) ($a['costo_anuncio'] ?? 0);
                    $bs = (float) ($b['costo_anuncio'] ?? 0);
                    if ($as === $bs) {
                        $al = (int) ($totals[$a['pk']] ?? 0);
                        $bl = (int) ($totals[$b['pk']] ?? 0);

                        return $bl <=> $al;
                    }

                    return $bs <=> $as;
                });

                $fmtInt = fn ($n) => number_format((int) $n, 0, ',', '.');
                $fmtMoney = fn ($n) => '$ '.number_format((float) $n, 2, ',', '.');

                $columns = [
                    ['key' => 'nombre',               'label' => 'Nombre Grupo de anuncios'],
                    ['key' => 'costo_anuncio',        'label' => 'Costo anuncio Grupo de anuncios'],
                    ['key' => 'leads_anuncio',        'label' => 'Leads anuncio Grupo de anuncios'],
                    ['key' => 'leads_calificados',    'label' => 'Leads calificados Grupo de anuncios'],
                    ['key' => 'leads_no_calificados', 'label' => 'Leads no calificados Grupo de anuncios'],
                    ['key' => 'roas',                 'label' => 'ROAS Grupo de anuncios'],
                ];

                $rows = [];
                foreach ($byPk as $pk => $d) {
                    $total = (int) ($totals[$pk] ?? 0);
                    $cal = (int) ($qualified[$pk] ?? 0);
                    $noCal = max(0, $total - $cal);

                    $spend = (float) ($d['costo_anuncio'] ?? 0);
                    $roas = $total > 0 ? round($spend / $total, 2) : null;

                    if ($spend <= 0 && $total <= 0) {
                        continue;
                    }

                    $rows[] = [
                        'nombre' => $d['nombre'] ?: '-',
                        'costo_anuncio' => $fmtMoney($spend),
                        'leads_anuncio' => $fmtInt($total),
                        'leads_calificados' => $fmtInt($cal),
                        'leads_no_calificados' => $fmtInt($noCal),
                        'roas' => $roas === null ? '-' : $fmtMoney($roas),
                    ];
                }

                $rows = array_slice($rows, 0, 200);

                return [
                    'enabled' => count($rows) > 0,
                    'note' => ' Calculo por Grupo (AdSet): spend desde insights (filtrado por cuenta) + leads desde leads.meta_id_ad - meta_ads - meta_ad_sets.',
                    'columns' => $columns,
                    'rows' => $rows,
                ];
            });
        } catch (\Throwable $e) {
            return ['enabled' => false, 'note' => 'Error cargando grupos: '.$e->getMessage(), 'columns' => [], 'rows' => []];
        }
    }

    private function buildMetaAdsTableData(
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        Carbon $from,
        Carbon $to,
        string $sessionId
    ): array {
        try {
            $insightsTable = (new MetaAdInsight)->getTable();
            $leadTable = (new Lead)->getTable();

            $metaAdsTable = (new \App\Models\MetaAd)->getTable();

            if (! Schema::hasTable($insightsTable) || ! Schema::hasTable($metaAdsTable)) {
                return ['enabled' => false, 'note' => 'No existen tablas necesarias (meta_ad_insights / meta_ads).', 'columns' => [], 'rows' => []];
            }

            if (! Schema::hasColumn($insightsTable, 'spend') || ! Schema::hasColumn($insightsTable, 'date_start') || ! Schema::hasColumn($insightsTable, 'meta_ad_id')) {
                return ['enabled' => false, 'note' => 'Faltan columnas en meta_ad_insights (spend / date_start / meta_ad_id).', 'columns' => [], 'rows' => []];
            }

            if (! Schema::hasColumn($leadTable, 'meta_id_ad') || ! Schema::hasColumn($metaAdsTable, 'meta_ad_id')) {
                return ['enabled' => false, 'note' => 'Faltan columnas para calcular leads por anuncio (leads.meta_id_ad / meta_ads.meta_ad_id).', 'columns' => [], 'rows' => []];
            }

            $hasExternalId = Schema::hasColumn($metaAdsTable, 'meta_ad_id');
            $hasName = Schema::hasColumn($metaAdsTable, 'name');

            $userKey = (string) (auth()->id() ?? 'guest');
            $cacheKey = 'dash_meta_ads_v3:'.sha1(json_encode([
                'customer_id' => $customerId,
                'integration_id' => $integrationId,
                'user' => $userKey,
                'session' => $sessionId,
                'filters' => $filters,
                'from' => $from->format('Y-m-d H:i'),
                'to' => $to->format('Y-m-d H:i'),
            ]));

            return Cache::remember($cacheKey, now()->addSeconds(60), function () use (
                $customerId, $integrationId, $filters, $from, $to,
                $insightsTable, $leadTable, $metaAdsTable,
                $hasExternalId, $hasName
            ) {
                $fromDate = $from->toDateString();
                $toDate = $to->toDateString();

                $idExpr = $hasExternalId ? 'ma.meta_ad_id' : 'ma.id';
                $nameExpr = $hasName ? 'ma.name' : 'ma.id';

                // -------------------------
                // 1) Spend por Anuncio (desde insights)
                // -------------------------
                $spendQ = DB::table("{$insightsTable} as i")
                    ->whereBetween('i.date_start', [$fromDate, $toDate])
                    ->join("{$metaAdsTable} as ma", 'ma.id', '=', 'i.meta_ad_id');

                // âœ… Filtrado por cliente (Customer -> MetaAdAccount -> MetaAdInsight)
                if ($customerId !== null || $integrationId !== null) {
                    $accTable = (new MetaAdAccount)->getTable();
                    $accQ = MetaAdAccount::query();

                    if ($customerId !== null) {
                        $accQ->where('customer_id', $customerId);
                    }
                    if ($integrationId !== null && Schema::hasColumn($accTable, 'integration_id')) {
                        $accQ->where('integration_id', $integrationId);
                    }

                    $accIds = (clone $accQ)->pluck('id')->map(fn ($v) => (string) $v)->all();
                    $accMetaIds = Schema::hasColumn($accTable, 'meta_account_id')
                        ? (clone $accQ)->pluck('meta_account_id')->filter()->map(fn ($v) => (string) $v)->all()
                        : [];
                    $allowed = array_values(array_unique(array_merge($accIds, $accMetaIds)));

                    if (! empty($allowed) && Schema::hasColumn($insightsTable, 'account_id')) {
                        $spendQ->whereIn('i.account_id', $allowed);
                    } else {
                        $spendQ->whereRaw('1=0');
                    }
                }

                $spendRows = $spendQ
                    ->selectRaw("
                    ma.id as pk,
                    {$idExpr} as meta_id,
                    {$nameExpr} as nombre,
                    SUM(i.spend) as costo_anuncio
                ")
                    ->groupBy('pk', DB::raw($idExpr), DB::raw($nameExpr))
                    ->orderByDesc('costo_anuncio')
                    ->limit(200)
                    ->get();

                $byPk = [];
                foreach ($spendRows as $r) {
                    $pk = (int) $r->pk;
                    $byPk[$pk] = [
                        'pk' => $pk,
                        'meta_id' => (string) ($r->meta_id ?? $pk),
                        'nombre' => (string) ($r->nombre ?? $pk),
                        'costo_anuncio' => (float) ($r->costo_anuncio ?? 0),
                    ];
                }

                // -------------------------
                // 2) Leads por Anuncio (desde leads)
                // -------------------------
                $excluded = ['lead no efectivo', 'sin gestionar', 'n/a'];
                $excludedSql = "'".implode("','", array_map(fn ($s) => str_replace("'", "''", $s), $excluded))."'";

                $base = Lead::query()->whereBetween("{$leadTable}.created_at", [$from, $to]);
                $base = $this->applyLeadScopeFilters($base, $customerId, $integrationId, $filters, $leadTable);
                $base = $this->applyLeadDimensionFilters($base, $filters, $leadTable, true, true, true, true);

                $leadAgg = (clone $base)
                    ->whereNotNull("{$leadTable}.meta_id_ad")
                    ->where("{$leadTable}.meta_id_ad", '!=', '')
                    ->join("{$metaAdsTable} as maL", 'maL.meta_ad_id', '=', "{$leadTable}.meta_id_ad")
                    ->leftJoin('crm_state as cs', 'cs.id', '=', "{$leadTable}.crm_state")
                    ->leftJoin('qualification as q', 'q.id', '=', 'cs.qualification')
                    ->selectRaw("
                    maL.id as pk,
                    COUNT(DISTINCT {$leadTable}.id) as total_leads,
                    COUNT(DISTINCT IF(
                        q.name IS NOT NULL AND LOWER(TRIM(q.name)) NOT IN ({$excludedSql}),
                        {$leadTable}.id,
                        NULL
                    )) as leads_calificados
                ")
                    ->groupBy('pk')
                    ->get();

                $totals = [];
                $qualified = [];
                foreach ($leadAgg as $r) {
                    $pk = (int) $r->pk;
                    $totals[$pk] = (int) ($r->total_leads ?? 0);
                    $qualified[$pk] = (int) ($r->leads_calificados ?? 0);
                }

                // Completa nombres para anuncios con leads pero sin spend
                $missingPks = array_diff(array_keys($totals), array_keys($byPk));
                if (! empty($missingPks)) {
                    $cols = [
                        'id as pk',
                        DB::raw(($hasExternalId ? 'meta_ad_id' : 'id').' as meta_id'),
                        DB::raw(($hasName ? 'name' : 'id').' as nombre'),
                    ];

                    $extra = DB::table($metaAdsTable)->whereIn('id', $missingPks)->select($cols)->get();
                    foreach ($extra as $r) {
                        $pk = (int) $r->pk;
                        $byPk[$pk] = [
                            'pk' => $pk,
                            'meta_id' => (string) ($r->meta_id ?? $pk),
                            'nombre' => (string) ($r->nombre ?? $pk),
                            'costo_anuncio' => 0.0,
                        ];
                    }
                }

                // Orden: spend desc, luego leads desc
                uasort($byPk, function ($a, $b) use ($totals) {
                    $as = (float) ($a['costo_anuncio'] ?? 0);
                    $bs = (float) ($b['costo_anuncio'] ?? 0);
                    if ($as === $bs) {
                        $al = (int) ($totals[$a['pk']] ?? 0);
                        $bl = (int) ($totals[$b['pk']] ?? 0);

                        return $bl <=> $al;
                    }

                    return $bs <=> $as;
                });

                $fmtInt = fn ($n) => number_format((int) $n, 0, ',', '.');
                $fmtMoney = fn ($n) => '$ '.number_format((float) $n, 2, ',', '.');

                $columns = [
                    ['key' => 'nombre',               'label' => 'Nombre Anuncio'],
                    ['key' => 'costo_anuncio',        'label' => 'Costo anuncio'],
                    ['key' => 'leads_anuncio',        'label' => 'Leads anuncio'],
                    ['key' => 'leads_calificados',    'label' => 'Leads calificados Anuncio'],
                    ['key' => 'leads_no_calificados', 'label' => 'Leads no calificados Anuncio'],
                    ['key' => 'roas',                 'label' => 'ROAS Anuncio'],
                ];

                $rows = [];
                foreach ($byPk as $pk => $d) {
                    $total = (int) ($totals[$pk] ?? 0);
                    $cal = (int) ($qualified[$pk] ?? 0);
                    $noCal = max(0, $total - $cal);

                    $spend = (float) ($d['costo_anuncio'] ?? 0);
                    $roas = $total > 0 ? round($spend / $total, 2) : null;

                    if ($spend <= 0 && $total <= 0) {
                        continue;
                    }

                    $rows[] = [
                        'nombre' => $d['nombre'] ?: '-',
                        'costo_anuncio' => $fmtMoney($spend),
                        'leads_anuncio' => $fmtInt($total),
                        'leads_calificados' => $fmtInt($cal),
                        'leads_no_calificados' => $fmtInt($noCal),
                        'roas' => $roas === null ? '-' : $fmtMoney($roas),
                    ];
                }

                $rows = array_slice($rows, 0, 200);

                return [
                    'enabled' => count($rows) > 0,
                    'note' => 'Calculo por Anuncio: spend desde insights (filtrado por cuenta) + leads desde leads.meta_id_ad emparejado con meta_ads.meta_ad_id.',
                    'columns' => $columns,
                    'rows' => $rows,
                ];
            });
        } catch (\Throwable $e) {
            return ['enabled' => false, 'note' => 'Error cargando anuncios: '.$e->getMessage(), 'columns' => [], 'rows' => []];
        }
    }

    /**
     * Transforma el paginator de leads a filas listas para renderizar sin lÃ³gica en el Blade.
     */
    private function transformLeadListRows($paginator)
    {
        $paginator->getCollection()->transform(function ($lead) {
            $phone = $this->firstFilledFieldValue($lead, ['telefono', 'phone', 'phone_number', 'celular', 'movil']);
            $first = $this->firstFilledFieldValue($lead, ['nombre', 'first_name', 'name', 'nombres']);
            $last = $this->firstFilledFieldValue($lead, ['apellido', 'last_name', 'lastname', 'apellidos']);

            $fuente = $lead->campaign_origin;
            $medio = $lead->plataforma;

            $value = is_numeric($lead->value ?? null) ? (float) $lead->value : 0.0;
            $valueFormatted = '$ '.number_format($value, 0, ',', '.');

            $pageUrl = $lead->page_url ?? '';
            $pageUrlLabel = ($pageUrl === null || $pageUrl === '') ? '-' : $pageUrl;

            return [
                'created_at' => optional($lead->created_at)->format('Y-m-d H:i'),
                'id' => $lead->id,
                'phone' => $phone ?: '-',
                'first_name' => $first ?: '-',
                'last_name' => $last ?: '-',
                'fuente' => ($fuente === null || $fuente === '') ? 'Sin Fuente' : $fuente,
                'medio' => ($medio === null || $medio === '') ? 'Sin Medio' : $medio,
                'campaign_objective' => $lead->campaign_objective_name ?? 'Sin Campaign Objective',
                'crm_state' => $lead->crm_state_name ?? 'Sin Estado',
                'qualification' => $lead->qualification_name ?? 'Sin Cualificación',
                'value' => $value,
                'value_formatted' => $valueFormatted,
                'page_url' => $pageUrlLabel,
            ];
        });

        return $paginator;
    }

    private function firstFilledFieldValue($obj, array $fields): ?string
    {
        foreach ($fields as $f) {
            $v = data_get($obj, $f);
            if ($v !== null && $v !== '') {
                return (string) $v;
            }
        }

        return null;
    }
}
