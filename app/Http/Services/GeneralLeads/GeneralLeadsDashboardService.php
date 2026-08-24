<?php

namespace App\Http\Services\GeneralLeads;

use App\Models\CrmState;
use App\Models\Customer;
use App\Models\Funnel;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Origin;
use App\Models\Platform;
use App\Models\Qualification;
use App\Models\Source;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GeneralLeadsDashboardService
{
    private const LEADS_FUNNEL_ID = 5;

    private const UNQUALIFIED = ['lead no efectivo', 'sin gestionar', 'sin respuesta', 'n/a', 'no efectivo', 'duplicado', 'spam'];

    private const SORTS = [
        'name' => 'name_value',
        'cost' => 'cost_value',
        'impressions' => 'impressions_value',
        'clicks' => 'clicks_value',
        'ctr' => 'ctr_value',
        'cpc' => 'cpc_value',
        'cpm' => 'cpm_value',
        'conversions' => 'conversions_value',
        'roas' => 'roas_value',
        'leads' => 'leads_value',
        'qualified_leads' => 'qualified_value',
        'unqualified_leads' => 'unqualified_value',
        'cpl' => 'cpl_value',
    ];

    public function __construct(
        private readonly GeneralLeadsLeadQuery $leads,
        private ?GeneralLeadsAdsLiveMetricsService $liveMetrics = null,
    ) {}

    public function shell(GeneralLeadsFilters $filters): array
    {
        $customers = Customer::query()->orderBy('name')->get(['id', 'name']);
        $selectedCustomer = $filters->customerId ? $customers->firstWhere('id', $filters->customerId) : null;

        return [
            'header' => [
                'customer' => GeneralLeadsPresentation::title($selectedCustomer?->name, 'Todos Los Clientes'),
                'period' => $filters->from->format('Y-m-d H:i').' A '.$filters->to->format('Y-m-d H:i'),
                'from' => $filters->from->format('Y-m-d\TH:i'),
                'to' => $filters->to->format('Y-m-d\TH:i'),
                'now' => now(config('app.timezone'))->format('Y-m-d\TH:i'),
            ],
            'filters' => [
                'customers' => $this->options($customers, $filters->customerId, 'Todos Los Clientes'),
                'integrations' => $this->options(Integration::query()->orderBy('name')->get(['id', 'name']), $filters->integrationId, 'Todas Las Integraciones'),
                'sources' => $this->sourceOptions($filters),
                'origins' => $this->catalogOptions($filters, 'origin'),
                'types' => $this->catalogOptions($filters, 'type'),
                'crm_states' => $this->options(CrmState::query()->orderBy('name')->get(['id', 'name']), $filters->crmState, 'Todos Los Estados CRM'),
                'qualifications' => $this->options(Qualification::query()->orderBy('name')->get(['id', 'name']), $filters->qualification, 'Todas Las Calificaciones'),
                'languages' => $this->leadValueOptions($filters, 'lenguaje', $filters->language, 'Todos Los Lenguajes'),
                'geos' => $this->leadValueOptions($filters, 'geo', $filters->geo, 'Todos Los Geos'),
                'clear_url' => route('dashboard.general-leads'),
            ],
        ];
    }

    public function build(Request $request, GeneralLeadsFilters $filters, bool $includeAds = true, bool $includeLiveCosts = true): array
    {
        $breakdowns = [
            'source' => $this->sourceBreakdown($filters),
            'origin' => $this->breakdown($filters, 'origin'),
            'type' => $this->breakdown($filters, 'type'),
        ];

        $dashboard = array_merge($this->shell($filters), [
            'summary' => $this->summary($filters),
            'costs' => $includeLiveCosts ? $this->costs($filters) : $this->pendingCosts(),
            'breakdowns' => $breakdowns,
            'funnels' => $this->funnels($filters),
            'charts' => [
                'donuts' => [
                    'source' => $this->donut($breakdowns['source']['rows']),
                    'origin' => $this->donut($breakdowns['origin']['rows']),
                    'type' => $this->donut($breakdowns['type']['rows']),
                ],
                'stacks' => [
                    'source' => $this->stack($breakdowns['source']['rows']),
                    'origin' => $this->stack($breakdowns['origin']['rows']),
                    'type' => $this->stack($breakdowns['type']['rows']),
                ],
            ],
            'notes' => [
                'costs' => 'Los costos se filtran por cliente y fecha. Los filtros propios de leads solo afectan leads atribuidos por IDs verificables, nunca por coincidencia de nombres.',
                'history' => 'El histórico diario filtra movimientos y leads creados dentro del periodo seleccionado.',
            ],
        ]);

        if ($includeAds) {
            $dashboard['ads'] = [
                'meta_campaigns' => $this->adTable($filters, $request, 'meta_campaigns'),
                'meta_ad_sets' => $this->adTable($filters, $request, 'meta_ad_sets'),
                'meta_ads' => $this->adTable($filters, $request, 'meta_ads'),
                'google_campaigns' => $this->adTable($filters, $request, 'google_campaigns'),
                'google_ad_groups' => $this->adTable($filters, $request, 'google_ad_groups'),
                'google_ads' => $this->adTable($filters, $request, 'google_ads'),
            ];
        }

        return $dashboard;
    }

    public function adTable(GeneralLeadsFilters $filters, Request $request, string $section): array
    {
        $payload = $this->adTablePayload($filters, $section);

        return $this->formatAdTableRows($request, $section, $payload['title'], $payload['rows']);
    }

    public function adTablePayload(GeneralLeadsFilters $filters, string $section): array
    {
        return str_starts_with($section, 'meta_')
            ? $this->metaTablePayload($filters, $section)
            : $this->googleTablePayload($filters, $section);
    }

    public function costSummary(GeneralLeadsFilters $filters): array
    {
        return $this->costs($filters);
    }

    public function list(Request $request, GeneralLeadsFilters $filters): array
    {
        $columns = $this->listColumns();
        $leads = $this->listQuery($request, $filters)
            ->orderByDesc('leads.created_at')
            ->paginate(25)
            ->withQueryString();

        $leads->getCollection()->transform(function (Lead $lead) use ($columns) {
            $row = collect($columns)
                ->mapWithKeys(fn (array $column) => [
                    $column['key'] => $this->listColumnValue($lead, $column['key']),
                ])
                ->all();

            $row['integration_statuses_badges'] = $this->leadIntegrationStatusBadges($lead);

            return $row;
        });

        return [
            'title' => $this->listTitle($request),
            'period' => $filters->from->format('Y-m-d H:i').' A '.$filters->to->format('Y-m-d H:i'),
            'back_url' => route('dashboard.general-leads', $filters->query()),
            'export_url' => route('dashboard.general-leads.list.export', $request->except('page')),
            'columns' => $columns,
            'leads' => $leads,
        ];
    }

    public function exportList(Request $request, GeneralLeadsFilters $filters)
    {
        $query = $this->listQuery($request, $filters)
            ->orderByDesc('leads.created_at');
        $columns = $this->listColumns();
        $filename = 'general_leads_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_column($columns, 'label'));

            $query->chunk(500, function ($rows) use ($out, $columns) {
                foreach ($rows as $lead) {
                    fputcsv($out, $this->listExportRow($lead, $columns));
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function listQuery(Request $request, GeneralLeadsFilters $filters)
    {
        $query = $this->leads->base($filters)
            ->with([
                'customer:id,name',
                'integration:id,name',
                'leadIntegrations' => function ($query) {
                    $query->select('id', 'lead_id', 'integration_id', 'status', 'answer_code', 'updated_at')
                        ->with('integration:id,name')
                        ->orderBy('id');
                },
            ])
            ->leftJoin('customers as c_list', 'c_list.id', '=', 'leads.customer_id')
            ->leftJoin('integrations as i_list', 'i_list.id', '=', 'leads.integration_id')
            ->leftJoin('crm_state as cs_list', 'cs_list.id', '=', 'leads.crm_state')
            ->leftJoin('qualification as q_list', 'q_list.id', '=', 'cs_list.qualification')
            ->leftJoin('funnels as f_list', 'f_list.id', '=', 'q_list.funnel_id')
            ->leftJoin('campaign_objectives as co_list', 'co_list.id', '=', 'leads.campaign_objective')
            ->leftJoin('origins as origin_list', 'origin_list.code', '=', 'leads.campaign_origin')
            ->leftJoin('sources as source_list', 'source_list.id', '=', 'origin_list.source_id')
            ->leftJoin('platforms as platform_list', 'platform_list.code', '=', 'leads.plataforma')
            ->select('leads.*')
            ->selectRaw("COALESCE(NULLIF(c_list.name, ''), 'Sin Cliente') as customer_name")
            ->selectRaw("COALESCE(NULLIF(i_list.name, ''), 'Sin Integracion') as integration_name")
            ->selectRaw("COALESCE(NULLIF(cs_list.name, ''), NULLIF(leads.crm_state, ''), 'Sin Estado') as crm_state_name")
            ->selectRaw("COALESCE(NULLIF(q_list.name, ''), 'Sin Calificacion') as qualification_name")
            ->selectRaw("COALESCE(NULLIF(f_list.name, ''), 'Sin Funnel') as funnel_name")
            ->selectRaw("COALESCE(NULLIF(co_list.nombre, ''), 'Sin Campaign Objective') as campaign_objective_name")
            ->selectRaw("COALESCE(NULLIF(source_list.name, ''), 'Sin Source') as source_name")
            ->selectRaw("COALESCE(NULLIF(origin_list.name, ''), NULLIF(leads.campaign_origin, ''), 'Sin Origen') as origin_name")
            ->selectRaw("COALESCE(NULLIF(platform_list.name, ''), NULLIF(leads.plataforma, ''), 'Sin Medio') as type_name");

        $scope = (string) $request->query('scope', 'total');
        if ($scope === 'managed') {
            $query->whereNotNull('leads.crm_state')
                ->where('leads.crm_state', '!=', '')
                ->where('cs_list.unmanaged', false);
        } elseif ($scope === 'unmanaged') {
            $query->where(fn ($q) => $q
                ->whereNull('leads.crm_state')
                ->orWhere('leads.crm_state', '')
                ->orWhere('cs_list.unmanaged', true));
        }

        $groupType = (string) $request->query('group_type', '');
        $groupId = (string) $request->query('group_id', '');

        if ($groupType === 'funnel' && $groupId !== '') {
            $groupId === '__NULL__'
                ? $query->whereNull('f_list.id')
                : $query->where('f_list.id', $groupId);
        }

        if ($groupType === 'funnel_history' && $groupId !== '') {
            $funnelIds = $groupId === '__SALES__' ? $this->funnelIds()['sales'] : [$groupId];
            $isLeadsGroup = $this->isLeadsFunnelGroup($groupId);

            $query->join('lead_funnel_histories as lfh_list', function ($join) use ($filters) {
                $join->on('lfh_list.lead_id', '=', 'leads.id')
                    ->whereBetween('lfh_list.created_at', [$filters->from, $filters->to]);
            })
                ->leftJoin('funnels as f_history_list', 'f_history_list.id', '=', 'lfh_list.funnel_id')
                ->distinct();

            if ($isLeadsGroup) {
                $leadsFunnelId = $this->leadsFunnelId();
                $query->where(function ($q) use ($leadsFunnelId) {
                    $q->where('lfh_list.funnel_id', $leadsFunnelId)
                        ->orWhereNull('lfh_list.funnel_id')
                        ->orWhereNull('f_history_list.id');
                });
            } else {
                empty($funnelIds)
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('lfh_list.funnel_id', $funnelIds);
            }
        }

        $this->applyAdvertisingEntityFilter($query, $request, $filters);

        return $query;
    }

    private function summary(GeneralLeadsFilters $filters): array
    {
        $base = $this->leads->base($filters);
        $funnels = $this->funnelIds();
        $sales = $this->sales($filters, $funnels['sales']);
        $salesGroupId = count($funnels['sales']) > 1 ? '__SALES__' : ($funnels['sales'][0] ?? null);

        return [
            'total' => (int) (clone $base)->distinct('leads.id')->count('leads.id'),
            'managed' => (int) (clone $base)->join('crm_state as cs_m', 'cs_m.id', '=', 'leads.crm_state')->where('cs_m.unmanaged', false)->distinct('leads.id')->count('leads.id'),
            'unmanaged' => (int) (clone $base)->leftJoin('crm_state as cs_u', 'cs_u.id', '=', 'leads.crm_state')->where(fn ($q) => $q->whereNull('leads.crm_state')->orWhere('leads.crm_state', '')->orWhere('cs_u.unmanaged', true))->distinct('leads.id')->count('leads.id'),
            'not_effective' => $this->countFunnels($filters, $funnels['not_effective']),
            'effective' => $this->countFunnels($filters, $funnels['effective']),
            'sales' => $sales,
            'urls' => [
                'total' => $this->listUrl($filters),
                'managed' => $this->listUrl($filters, ['scope' => 'managed']),
                'unmanaged' => $this->listUrl($filters, ['scope' => 'unmanaged']),
                'not_effective' => isset($funnels['not_effective'][0]) ? $this->listUrl($filters, ['group_type' => 'funnel', 'group_id' => $funnels['not_effective'][0]]) : null,
                'effective' => isset($funnels['effective'][0]) ? $this->listUrl($filters, ['group_type' => 'funnel', 'group_id' => $funnels['effective'][0]]) : null,
                'sales' => $salesGroupId ? $this->listUrl($filters, ['group_type' => 'funnel_history', 'group_id' => $salesGroupId]) : null,
            ],
            'missing_funnels' => $funnels['missing'],
        ];
    }

    private function countFunnels(GeneralLeadsFilters $filters, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int) $this->leads->base($filters)
            ->leftJoin('crm_state as cs_f', 'cs_f.id', '=', 'leads.crm_state')
            ->leftJoin('qualification as q_f', 'q_f.id', '=', 'cs_f.qualification')
            ->whereIn('q_f.funnel_id', $ids)
            ->distinct('leads.id')
            ->count('leads.id');
    }

    private function sales(GeneralLeadsFilters $filters, array $ids): array
    {
        if ($ids === []) {
            return ['count' => 0, 'value' => GeneralLeadsPresentation::money(0)];
        }

        $leadIds = $this->historicalLeadIdsByFunnelIds($filters, $ids);

        $row = DB::query()
            ->fromSub($leadIds, 'sales_history_leads')
            ->join('leads', 'leads.id', '=', 'sales_history_leads.lead_id')
            ->selectRaw('COUNT(DISTINCT leads.id) as total, COALESCE(SUM(COALESCE(leads.value, 0)), 0) as value')
            ->first();

        return ['count' => (int) ($row->total ?? 0), 'value' => GeneralLeadsPresentation::money($row->value ?? 0)];
    }

    private function historicalLeadIdsByFunnelIds(GeneralLeadsFilters $filters, array $ids): QueryBuilder
    {
        $query = DB::table('lead_funnel_histories as lfh_sales')
            ->join('leads', 'leads.id', '=', 'lfh_sales.lead_id')
            ->whereBetween('lfh_sales.created_at', [$filters->from, $filters->to])
            ->whereBetween('leads.created_at', [$filters->from, $filters->to])
            ->whereIn('lfh_sales.funnel_id', $ids);

        $this->applyHistoryFilters($query, $filters);

        return $query
            ->select('leads.id as lead_id')
            ->distinct();
    }

    private function breakdown(GeneralLeadsFilters $filters, string $dimension): array
    {
        [$column, $table, $nullValue, $nullLabel, $missingValue, $missingLabel, $filterKey] = $this->dimension($dimension);
        $catalog = 'catalog_'.$dimension;

        $rows = $this->leads->base($filters)
            ->leftJoin("{$table} as {$catalog}", "{$catalog}.code", '=', $column)
            ->leftJoin('crm_state as cs_b', 'cs_b.id', '=', 'leads.crm_state')
            ->leftJoin('qualification as q_b', 'q_b.id', '=', 'cs_b.qualification')
            ->selectRaw("
                CASE WHEN {$column} IS NULL OR {$column} = '' THEN ? WHEN {$catalog}.id IS NULL THEN ? ELSE {$column} END as group_value,
                CASE WHEN {$column} IS NULL OR {$column} = '' THEN ? WHEN {$catalog}.id IS NULL THEN ? ELSE COALESCE(NULLIF({$catalog}.name, ''), {$column}) END as group_label,
                CASE WHEN {$column} IS NOT NULL AND {$column} != '' AND {$catalog}.id IS NULL THEN {$column} ELSE NULL END as missing_catalog_value,
                COUNT(DISTINCT leads.id) as total,
                COUNT(DISTINCT CASE WHEN q_b.name IS NOT NULL AND LOWER(TRIM(q_b.name)) NOT IN ({$this->excludedSql()}) THEN leads.id END) as qualified
            ", [$nullValue, $missingValue, $nullLabel, $missingLabel])
            ->groupByRaw("{$column}, {$catalog}.id, {$catalog}.name")
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($filters, $filterKey) {
                $total = (int) $row->total;
                $qualified = (int) $row->qualified;

                return [
                    'name' => $this->breakdownLabel($row->group_label, $row->missing_catalog_value),
                    'value' => (string) $row->group_value,
                    'total' => $total,
                    'qualified' => $qualified,
                    'unqualified' => max(0, $total - $qualified),
                    'url' => $this->listUrl($filters, [$filterKey => (string) $row->group_value]),
                ];
            })
            ->values()
            ->all();

        return ['rows' => $rows, 'totals' => $this->totals($rows)];
    }

    private function breakdownLabel(mixed $label, mixed $missingValue = null): string
    {
        $display = GeneralLeadsPresentation::title($label);
        $missing = trim((string) ($missingValue ?? ''));

        return $missing === '' ? $display : "{$display}({$missing})";
    }

    private function sourceBreakdown(GeneralLeadsFilters $filters): array
    {
        $valueExpr = "COALESCE(CAST(source_dim.id AS CHAR), '".GeneralLeadsPresentation::NULL_SOURCE."')";
        $labelExpr = "COALESCE(NULLIF(source_dim.name, ''), 'Organico O Sin Source')";

        $rows = $this->leads->base($filters)
            ->leftJoin('origins as origin_source', 'origin_source.code', '=', 'leads.campaign_origin')
            ->leftJoin('sources as source_dim', 'source_dim.id', '=', 'origin_source.source_id')
            ->leftJoin('crm_state as cs_source', 'cs_source.id', '=', 'leads.crm_state')
            ->leftJoin('qualification as q_source', 'q_source.id', '=', 'cs_source.qualification')
            ->selectRaw("
                {$valueExpr} as group_value,
                {$labelExpr} as group_label,
                COUNT(DISTINCT leads.id) as total,
                COUNT(DISTINCT CASE WHEN q_source.name IS NOT NULL AND LOWER(TRIM(q_source.name)) NOT IN ({$this->excludedSql()}) THEN leads.id END) as qualified
            ")
            ->groupByRaw('source_dim.id, source_dim.name')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($filters) {
                $total = (int) $row->total;
                $qualified = (int) $row->qualified;

                return [
                    'name' => GeneralLeadsPresentation::title($row->group_label),
                    'value' => (string) $row->group_value,
                    'total' => $total,
                    'qualified' => $qualified,
                    'unqualified' => max(0, $total - $qualified),
                    'url' => $this->listUrl($filters, ['source' => (string) $row->group_value]),
                ];
            })
            ->values()
            ->all();

        return ['rows' => $rows, 'totals' => $this->totals($rows)];
    }

    private function costs(GeneralLeadsFilters $filters): array
    {
        $meta = (float) $this->liveMetrics()->metaRows($filters, 'meta_campaigns')->sum('cost_value');
        $google = (float) $this->liveMetrics()->googleRows($filters, 'google_campaigns')->sum('cost_value');

        return ['meta' => GeneralLeadsPresentation::money($meta), 'google' => GeneralLeadsPresentation::money($google), 'total' => GeneralLeadsPresentation::money($meta + $google)];
    }

    private function pendingCosts(): array
    {
        return ['meta' => 'Consultando...', 'google' => 'Consultando...', 'total' => 'Consultando...'];
    }

    private function funnels(GeneralLeadsFilters $filters): array
    {
        $leadsFunnelId = $this->leadsFunnelId();

        $current = $this->leads->base($filters)
            ->leftJoin('crm_state as cs_cur', 'cs_cur.id', '=', 'leads.crm_state')
            ->leftJoin('qualification as q_cur', 'q_cur.id', '=', 'cs_cur.qualification')
            ->leftJoin('funnels as f_cur', 'f_cur.id', '=', 'q_cur.funnel_id')
            ->selectRaw("COALESCE(f_cur.id, '__NULL__') as funnel_id, COALESCE(NULLIF(f_cur.name, ''), 'Sin Funnel') as name, COUNT(DISTINCT leads.id) as total")
            ->groupBy('f_cur.id', 'f_cur.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'name' => GeneralLeadsPresentation::title($r->name),
                'total' => (int) $r->total,
                'url' => $this->listUrl($filters, ['group_type' => 'funnel', 'group_id' => (string) $r->funnel_id]),
            ])
            ->all();

        $historyQuery = DB::table('lead_funnel_histories as lfh')
            ->join('leads', 'leads.id', '=', 'lfh.lead_id')
            ->leftJoin('funnels as f_history', 'f_history.id', '=', 'lfh.funnel_id')
            ->whereBetween('lfh.created_at', [$filters->from, $filters->to])
            ->whereBetween('leads.created_at', [$filters->from, $filters->to]);
        $this->applyHistoryFilters($historyQuery, $filters);
        $historyFunnelId = "CASE WHEN f_history.id IS NULL THEN {$leadsFunnelId} ELSE f_history.id END";
        $historyFunnelName = "CASE WHEN f_history.id IS NULL THEN 'Leads' ELSE COALESCE(NULLIF(f_history.name, ''), 'Sin Funnel') END";
        $history = DB::query()
            ->fromSub(
                (clone $historyQuery)->selectRaw("lfh.lead_id, {$historyFunnelId} as funnel_id, {$historyFunnelName} as name"),
                'history_rows'
            )
            ->selectRaw('funnel_id, name, COUNT(DISTINCT lead_id) as total')
            ->groupBy('funnel_id', 'name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'name' => GeneralLeadsPresentation::title($r->name),
                'total' => (int) $r->total,
                'url' => $this->listUrl($filters, ['group_type' => 'funnel_history', 'group_id' => (string) $r->funnel_id]),
            ])
            ->all();

        $dailyQuery = DB::table('lead_funnel_histories as lfh')
            ->join('leads', 'leads.id', '=', 'lfh.lead_id')
            ->leftJoin('funnels as f_day', 'f_day.id', '=', 'lfh.funnel_id')
            ->whereBetween('lfh.created_at', [$filters->from, $filters->to])
            ->whereBetween('leads.created_at', [$filters->from, $filters->to]);
        $this->applyHistoryFilters($dailyQuery, $filters);
        $dailyFunnelId = "CASE WHEN f_day.id IS NULL THEN {$leadsFunnelId} ELSE f_day.id END";
        $dailyFunnelName = "CASE WHEN f_day.id IS NULL THEN 'Leads' ELSE COALESCE(NULLIF(f_day.name, ''), 'Sin Funnel') END";
        $daily = DB::query()
            ->fromSub(
                (clone $dailyQuery)->selectRaw("DATE(lfh.created_at) as day, lfh.lead_id, {$dailyFunnelId} as funnel_id, {$dailyFunnelName} as name"),
                'daily_rows'
            )
            ->selectRaw('day, name, COUNT(DISTINCT lead_id) as total')
            ->groupBy('day', 'funnel_id', 'name')
            ->orderBy('day')
            ->get();
        $days = $daily->pluck('day')->unique()->values();

        return [
            'current' => $current,
            'history' => $history,
            'daily' => [
                'labels' => $days->all(),
                'series' => $daily->groupBy('name')->map(fn ($rows, $name) => [
                    'name' => GeneralLeadsPresentation::title($name),
                    'data' => $days->map(fn ($day) => (int) ($rows->firstWhere('day', $day)?->total ?? 0))->all(),
                ])->values()->all(),
            ],
        ];
    }

    private function metaTable(GeneralLeadsFilters $filters, Request $request, string $section): array
    {
        $payload = $this->metaTablePayload($filters, $section);

        return $this->formatAdTableRows($request, $section, $payload['title'], $payload['rows']);
    }

    private function metaTablePayload(GeneralLeadsFilters $filters, string $section): array
    {
        $title = match ($section) {
            'meta_campaigns' => 'Campañas Meta',
            'meta_ad_sets' => 'Grupos De Anuncios Meta',
            default => 'Anuncios Meta',
        };
        $costs = $this->liveMetrics()->metaRows($filters, $section);

        return ['title' => $title, 'rows' => $this->adRows($filters, $section, $costs, $this->metaLeadRows($filters, $section, $costs))];
    }

    private function metaLeadRows(GeneralLeadsFilters $filters, string $section, Collection $costs): Collection
    {
        $adIdsByEntity = $this->metaAdIdsByEntity($filters, $section, $costs);
        $adIds = collect($adIdsByEntity)->flatten()->filter()->unique()->values();

        if ($adIds->isEmpty()) {
            return new Collection;
        }

        $leadRows = $this->leads->base($filters)
            ->leftJoin('crm_state as cs_a', 'cs_a.id', '=', 'leads.crm_state')
            ->leftJoin('qualification as q_a', 'q_a.id', '=', 'cs_a.qualification')
            ->whereIn('leads.meta_id_ad', $adIds->all())
            ->selectRaw("leads.meta_id_ad as ad_id, COUNT(DISTINCT leads.id) as leads_value, COUNT(DISTINCT CASE WHEN q_a.name IS NOT NULL AND LOWER(TRIM(q_a.name)) NOT IN ({$this->excludedSql()}) THEN leads.id END) as qualified_value")
            ->groupBy('leads.meta_id_ad')
            ->get()
            ->keyBy('ad_id');

        return collect($adIdsByEntity)
            ->map(function (array $entityAdIds, string $entityId) use ($leadRows) {
                $leads = 0;
                $qualified = 0;

                foreach ($entityAdIds as $adId) {
                    $row = $leadRows->get($adId);
                    $leads += (int) ($row?->leads_value ?? 0);
                    $qualified += (int) ($row?->qualified_value ?? 0);
                }

                return (object) [
                    'entity_id' => $entityId,
                    'leads_value' => $leads,
                    'qualified_value' => $qualified,
                ];
            })
            ->keyBy('entity_id');
    }

    private function googleTable(GeneralLeadsFilters $filters, Request $request, string $section): array
    {
        $payload = $this->googleTablePayload($filters, $section);

        return $this->formatAdTableRows($request, $section, $payload['title'], $payload['rows']);
    }

    private function googleTablePayload(GeneralLeadsFilters $filters, string $section): array
    {
        $title = match ($section) {
            'google_campaigns' => 'Campañas Google',
            'google_ad_groups' => 'Grupos De Anuncios Google',
            default => 'Anuncios Google',
        };
        $costs = $this->liveMetrics()->googleRows($filters, $section);

        return ['title' => $title, 'rows' => $this->adRows($filters, $section, $costs, $this->googleLeadRows($filters, $section))];
    }

    private function googleLeadRows(GeneralLeadsFilters $filters, string $section): Collection
    {
        $field = match ($section) {
            'google_campaigns' => 'google_campaign_id',
            'google_ad_groups' => 'google_adgroup_id',
            default => 'google_ad_id',
        };

        return $this->leads->base($filters)
            ->leftJoin('crm_state as cs_g', 'cs_g.id', '=', 'leads.crm_state')
            ->leftJoin('qualification as q_g', 'q_g.id', '=', 'cs_g.qualification')
            ->whereNotNull("leads.{$field}")
            ->where("leads.{$field}", '!=', '')
            ->selectRaw("leads.{$field} as entity_id, COUNT(DISTINCT leads.id) as leads_value, COUNT(DISTINCT CASE WHEN q_g.name IS NOT NULL AND LOWER(TRIM(q_g.name)) NOT IN ({$this->excludedSql()}) THEN leads.id END) as qualified_value")
            ->groupByRaw("leads.{$field}")
            ->get()
            ->keyBy('entity_id');
    }

    private function metaAdIdsByEntity(GeneralLeadsFilters $filters, string $section, Collection $costs): array
    {
        if ($section === 'meta_ads') {
            return $costs
                ->mapWithKeys(fn ($row, $entityId) => [(string) $entityId => [(string) $entityId]])
                ->all();
        }

        $groupKey = $section === 'meta_campaigns' ? 'campaign_id' : 'adset_id';

        return $this->liveMetrics()
            ->metaAdMap($filters)
            ->groupBy(fn ($row) => (string) ($row->{$groupKey} ?? ''))
            ->filter(fn ($rows, $entityId) => $entityId !== '' && $costs->has($entityId))
            ->map(fn ($rows) => $rows->pluck('entity_value')->filter()->unique()->values()->all())
            ->all();
    }

    private function formatAds(Request $request, GeneralLeadsFilters $filters, string $section, string $title, Collection $costs, Collection $leads): array
    {
        return $this->formatAdTableRows($request, $section, $title, $this->adRows($filters, $section, $costs, $leads));
    }

    private function adRows(GeneralLeadsFilters $filters, string $section, Collection $costs, Collection $leads): array
    {
        $rows = [];
        foreach ($costs as $id => $cost) {
            $name = (string) $cost->name_value;
            $lead = $leads->get($id);
            $count = (int) ($lead?->leads_value ?? 0);
            $qualified = (int) ($lead?->qualified_value ?? 0);
            $costValue = (float) ($cost->cost_value ?? 0);
            $rows[] = [
                'entity_value' => (string) $id,
                'name_value' => $name,
                'cost_value' => $costValue,
                'impressions_value' => (int) ($cost->impressions_value ?? 0),
                'clicks_value' => (int) ($cost->clicks_value ?? 0),
                'ctr_value' => (float) ($cost->ctr_value ?? 0),
                'cpc_value' => (float) ($cost->cpc_value ?? 0),
                'cpm_value' => (float) ($cost->cpm_value ?? 0),
                'conversions_value' => (float) ($cost->conversions_value ?? 0),
                'roas_value' => $cost->roas_value !== null ? (float) $cost->roas_value : null,
                'leads_value' => $count,
                'qualified_value' => $qualified,
                'unqualified_value' => max(0, $count - $qualified),
                'cpl_value' => $count > 0 ? $costValue / $count : 0,
                'url' => $this->adListUrl($filters, $section, (string) $id, $name),
            ];
        }

        return $rows;
    }

    public function formatAdTableRows(Request $request, string $section, string $title, array $rows): array
    {
        $sort = $request->input("sort.{$section}", 'cost');
        $dir = $request->input("dir.{$section}", 'desc') === 'asc' ? 'asc' : 'desc';
        $sortKey = self::SORTS[$sort] ?? 'cost_value';
        usort($rows, fn ($a, $b) => $this->compareAdRows($a, $b, $sortKey, $dir));
        $rows = array_slice($rows, 0, 100);
        $validRoas = array_values(array_filter(array_column($rows, 'roas_value'), fn ($v) => $v !== null));
        $totalLeads = array_sum(array_column($rows, 'leads_value'));
        $totalCost = array_sum(array_column($rows, 'cost_value'));
        $totalImpressions = array_sum(array_column($rows, 'impressions_value'));
        $totalClicks = array_sum(array_column($rows, 'clicks_value'));

        return [
            'title' => $title,
            'section' => $section,
            'sort' => $sort,
            'dir' => $dir,
            'rows' => array_map(fn ($r) => $this->adRow($r), $rows),
            'totals' => $this->adRow([
                'name_value' => 'Totales',
                'cost_value' => $totalCost,
                'impressions_value' => $totalImpressions,
                'clicks_value' => $totalClicks,
                'ctr_value' => $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0,
                'cpc_value' => $totalClicks > 0 ? $totalCost / $totalClicks : 0,
                'cpm_value' => $totalImpressions > 0 ? ($totalCost / $totalImpressions) * 1000 : 0,
                'conversions_value' => array_sum(array_column($rows, 'conversions_value')),
                'roas_value' => $validRoas === [] ? null : array_sum($validRoas) / count($validRoas),
                'leads_value' => $totalLeads,
                'qualified_value' => array_sum(array_column($rows, 'qualified_value')),
                'unqualified_value' => array_sum(array_column($rows, 'unqualified_value')),
                'cpl_value' => $totalLeads > 0 ? $totalCost / $totalLeads : 0,
            ]),
        ];
    }

    private function compareAdRows(array $a, array $b, string $sortKey, string $dir): int
    {
        $primary = $sortKey === 'name_value'
            ? strcasecmp((string) $a[$sortKey], (string) $b[$sortKey])
            : ($a[$sortKey] <=> $b[$sortKey]);

        if ($primary !== 0) {
            return ($dir === 'asc' ? 1 : -1) * $primary;
        }

        $name = strcasecmp((string) $a['name_value'], (string) $b['name_value']);
        if ($name !== 0) {
            return $name;
        }

        return strcasecmp((string) $a['entity_value'], (string) $b['entity_value']);
    }

    private function adRow(array $row): array
    {
        $formatted = [
            'entity_value' => (string) ($row['entity_value'] ?? ''),
            'name' => GeneralLeadsPresentation::title($row['name_value']),
            'cost' => GeneralLeadsPresentation::money($row['cost_value']),
            'impressions' => number_format((float) $row['impressions_value'], 0, ',', '.'),
            'clicks' => number_format((float) $row['clicks_value'], 0, ',', '.'),
            'ctr' => number_format((float) $row['ctr_value'], 2, ',', '.').'%',
            'cpc' => GeneralLeadsPresentation::money($row['cpc_value']),
            'cpm' => GeneralLeadsPresentation::money($row['cpm_value']),
            'conversions' => number_format((float) $row['conversions_value'], 2, ',', '.'),
            'roas' => $row['roas_value'] === null ? 'Sin Dato' : number_format((float) $row['roas_value'], 2, ',', '.'),
            'leads' => number_format((float) $row['leads_value'], 0, ',', '.'),
            'qualified_leads' => number_format((float) $row['qualified_value'], 0, ',', '.'),
            'unqualified_leads' => number_format((float) $row['unqualified_value'], 0, ',', '.'),
            'cpl' => GeneralLeadsPresentation::money($row['cpl_value']),
        ];

        if (! empty($row['url'])) {
            $formatted['url'] = $row['url'];
        }

        return $formatted;
    }

    private function catalogOptions(GeneralLeadsFilters $filters, string $dimension): array
    {
        [$column, $table, $nullValue, $nullLabel, $missingValue, $missingLabel, $filterKey] = $this->dimension($dimension);
        $catalog = match ($dimension) {
            'origin' => Origin::query(),
            default => Platform::query(),
        };
        $options = $catalog->orderBy('name')->get(['code', 'name'])->map(fn ($row) => [
            'value' => (string) $row->code,
            'label' => GeneralLeadsPresentation::title($row->name),
            'selected' => $this->selectedDimension($filters, $dimension) === (string) $row->code,
        ])->all();
        $base = $this->leads->base($filters);
        $this->leads->apply($base, $filters, [$filterKey]);
        if ((clone $base)->where(fn ($q) => $q->whereNull($column)->orWhere($column, ''))->exists()) {
            $options[] = ['value' => $nullValue, 'label' => $nullLabel, 'selected' => $this->selectedDimension($filters, $dimension) === $nullValue];
        }
        if ((clone $base)->whereNotNull($column)->where($column, '!=', '')->whereNotIn($column, fn ($q) => $q->from($table)->select('code')->whereNotNull('code'))->exists()) {
            $options[] = ['value' => $missingValue, 'label' => $missingLabel, 'selected' => $this->selectedDimension($filters, $dimension) === $missingValue];
        }
        array_unshift($options, ['value' => '', 'label' => $dimension === 'origin' ? 'Todos Los Orígenes' : 'Todos Los Medios', 'selected' => $this->selectedDimension($filters, $dimension) === null]);

        return $options;
    }

    private function sourceOptions(GeneralLeadsFilters $filters): array
    {
        $options = Source::query()->orderBy('name')->get(['id', 'name'])->map(fn ($row) => [
            'value' => (string) $row->id,
            'label' => GeneralLeadsPresentation::title($row->name),
            'selected' => $filters->source === (string) $row->id,
        ])->all();

        $base = $this->leads->base($filters);
        $this->leads->apply($base, $filters, ['source']);
        if ((clone $base)->where(fn ($q) => $q->whereNull('leads.campaign_origin')->orWhere('leads.campaign_origin', '')->orWhereNotIn('leads.campaign_origin', fn ($sub) => $sub->from('origins')->select('code')->whereNotNull('source_id')))->exists()) {
            $options[] = ['value' => GeneralLeadsPresentation::NULL_SOURCE, 'label' => 'Organico O Sin Source', 'selected' => $filters->source === GeneralLeadsPresentation::NULL_SOURCE];
        }
        array_unshift($options, ['value' => '', 'label' => 'Todos Los Source', 'selected' => $filters->source === null]);

        return $options;
    }

    private function options(Collection $rows, mixed $selected, string $all): array
    {
        return collect([['value' => '', 'label' => $all, 'selected' => $selected === null]])
            ->merge($rows->map(fn ($row) => ['value' => (string) $row->id, 'label' => GeneralLeadsPresentation::title($row->name), 'selected' => (string) $selected === (string) $row->id]))
            ->values()
            ->all();
    }

    private function leadValueOptions(GeneralLeadsFilters $filters, string $column, ?string $selected, string $all): array
    {
        $values = $this->leads->base($filters)->whereNotNull("leads.{$column}")->where("leads.{$column}", '!=', '')->distinct()->orderBy("leads.{$column}")->limit(200)->pluck("leads.{$column}");

        return collect([['value' => '', 'label' => $all, 'selected' => $selected === null]])
            ->merge($values->map(fn ($value) => ['value' => (string) $value, 'label' => GeneralLeadsPresentation::title($value), 'selected' => $selected === (string) $value]))
            ->values()
            ->all();
    }

    private function funnelIds(): array
    {
        $funnels = Funnel::query()->get(['id', 'name']);
        $find = fn (array $names) => $funnels->filter(fn ($f) => in_array(mb_strtolower(trim((string) $f->name)), $names, true))->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $notEffective = $find(['lead no efectivo', 'leads no efectivos']);
        $effective = $find(['oportunidades', 'respondidos']);
        $sales = $find(['ventas', 'venta']);

        return [
            'not_effective' => $notEffective,
            'effective' => $effective,
            'sales' => $sales,
            'missing' => array_values(array_filter([$notEffective === [] ? 'Lead No Efectivo' : null, $effective === [] ? 'Oportunidades O Respondidos' : null, $sales === [] ? 'Ventas' : null])),
        ];
    }

    private function leadsFunnelId(): int
    {
        $id = Funnel::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', ['leads'])
            ->value('id');

        $id ??= Funnel::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', ['lead'])
            ->value('id');

        return (int) ($id ?: self::LEADS_FUNNEL_ID);
    }

    private function isLeadsFunnelGroup(string $groupId): bool
    {
        return $groupId === '__LEADS__' || $groupId === (string) $this->leadsFunnelId();
    }

    private function applyHistoryFilters(QueryBuilder $query, GeneralLeadsFilters $filters): void
    {
        if ($filters->customerId) {
            $query->where('leads.customer_id', $filters->customerId);
        }
        if ($filters->integrationId) {
            $query->where('leads.integration_id', $filters->integrationId);
        }
        if ($filters->source) {
            if (str_starts_with($filters->source, '__NULL_')) {
                $query->where(fn ($q) => $q->whereNull('leads.campaign_origin')->orWhere('leads.campaign_origin', '')->orWhereNotIn('leads.campaign_origin', fn ($sub) => $sub->from('origins')->select('code')->whereNotNull('source_id')));
            } elseif (str_starts_with($filters->source, '__MISSING_')) {
                $query->whereNotNull('leads.campaign_origin')->where('leads.campaign_origin', '!=', '')->whereNotIn('leads.campaign_origin', fn ($q) => $q->from('origins')->select('code')->whereNotNull('source_id'));
            } else {
                $query->whereIn('leads.campaign_origin', fn ($q) => $q->from('origins')->select('code')->where('source_id', $filters->source));
            }
        }

        foreach ([['leads.campaign_origin', $filters->campaignOrigin, 'origins'], ['leads.plataforma', $filters->platform, 'platforms']] as [$column, $value, $table]) {
            if (! $value) {
                continue;
            }
            if (str_starts_with($value, '__NULL_')) {
                $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, ''));
            } elseif (str_starts_with($value, '__MISSING_')) {
                $query->whereNotNull($column)->where($column, '!=', '')->whereNotIn($column, fn ($q) => $q->from($table)->select('code')->whereNotNull('code'));
            } else {
                $query->where($column, $value);
            }
        }
        if ($filters->crmState) {
            $query->where('leads.crm_state', $filters->crmState);
        }
        if ($filters->qualification) {
            $query->whereIn('leads.crm_state', fn ($q) => $q->from('crm_state')->select('id')->where('qualification', $filters->qualification));
        }
        if ($filters->language) {
            $query->where('leads.lenguaje', $filters->language);
        }
        if ($filters->geo) {
            $query->where('leads.geo', $filters->geo);
        }
    }

    private function dimension(string $dimension): array
    {
        return match ($dimension) {
            'source' => ['leads.gad_source', 'sources', GeneralLeadsPresentation::NULL_SOURCE, 'Orgánico O Sin Source', GeneralLeadsPresentation::MISSING_SOURCE, 'Source No Creado', 'source'],
            'origin' => ['leads.campaign_origin', 'origins', GeneralLeadsPresentation::NULL_ORIGIN, 'Orgánico O Sin Origen', GeneralLeadsPresentation::MISSING_ORIGIN, 'Origen No Creado', 'campaign_origin'],
            default => ['leads.plataforma', 'platforms', GeneralLeadsPresentation::NULL_TYPE, 'Orgánico O Sin Medio', GeneralLeadsPresentation::MISSING_TYPE, 'Medio No Creado', 'plataforma'],
        };
    }

    private function selectedDimension(GeneralLeadsFilters $filters, string $dimension): ?string
    {
        return match ($dimension) {
            'source' => $filters->source,
            'origin' => $filters->campaignOrigin,
            default => $filters->platform,
        };
    }

    private function totals(array $rows): array
    {
        return ['total' => array_sum(array_column($rows, 'total')), 'qualified' => array_sum(array_column($rows, 'qualified')), 'unqualified' => array_sum(array_column($rows, 'unqualified'))];
    }

    private function donut(array $rows): array
    {
        return array_map(fn ($row) => ['name' => $row['name'], 'value' => $row['total']], $rows);
    }

    private function stack(array $rows): array
    {
        return ['labels' => array_column($rows, 'name'), 'qualified' => array_column($rows, 'qualified'), 'unqualified' => array_column($rows, 'unqualified')];
    }

    private function excludedSql(): string
    {
        return "'".implode("','", array_map(fn ($value) => str_replace("'", "''", $value), self::UNQUALIFIED))."'";
    }

    private function listUrl(GeneralLeadsFilters $filters, array $overrides = []): string
    {
        return route('dashboard.general-leads.list', $filters->query($overrides));
    }

    private function adListUrl(GeneralLeadsFilters $filters, string $section, string $entityId, string $entityName): string
    {
        return $this->listUrl($filters, [
            'ad_section' => $section,
            'ad_entity_id' => $entityId,
            'ad_entity_name' => mb_substr($entityName, 0, 120),
        ]);
    }

    private function applyAdvertisingEntityFilter($query, Request $request, GeneralLeadsFilters $filters): void
    {
        $section = (string) $request->query('ad_section', '');
        $entityId = (string) $request->query('ad_entity_id', '');

        if ($section === '' || $entityId === '') {
            return;
        }

        $googleField = match ($section) {
            'google_campaigns' => 'google_campaign_id',
            'google_ad_groups' => 'google_adgroup_id',
            'google_ads' => 'google_ad_id',
            default => null,
        };

        if ($googleField) {
            $query->where("leads.{$googleField}", $entityId);

            return;
        }

        if ($section === 'meta_ads') {
            $query->where('leads.meta_id_ad', $entityId);

            return;
        }

        if (! in_array($section, ['meta_campaigns', 'meta_ad_sets'], true)) {
            return;
        }

        $groupKey = $section === 'meta_campaigns' ? 'campaign_id' : 'adset_id';
        $adIds = $this->liveMetrics()
            ->metaAdMap($filters)
            ->filter(fn ($row) => (string) ($row->{$groupKey} ?? '') === $entityId)
            ->pluck('entity_value')
            ->filter()
            ->unique()
            ->values();

        $adIds->isEmpty()
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('leads.meta_id_ad', $adIds->all());
    }

    private function listTitle(Request $request): string
    {
        $adSection = (string) $request->query('ad_section', '');
        $adEntityName = trim((string) $request->query('ad_entity_name', ''));

        if ($adSection !== '') {
            $label = match ($adSection) {
                'meta_campaigns' => 'Campaña Meta',
                'meta_ad_sets' => 'Grupo De Anuncios Meta',
                'meta_ads' => 'Anuncio Meta',
                'google_campaigns' => 'Campaña Google',
                'google_ad_groups' => 'Grupo De Anuncios Google',
                'google_ads' => 'Anuncio Google',
                default => 'Pauta',
            };

            return $adEntityName !== ''
                ? "Leads en LQ Por {$label}: {$adEntityName}"
                : "Leads en LQ Por {$label}";
        }

        $scope = (string) $request->query('scope', 'total');
        if ($scope === 'managed') {
            return 'Leads en LQ Gestionados';
        }
        if ($scope === 'unmanaged') {
            return 'Leads en LQ No Gestionados';
        }
        if ($request->query('group_type') === 'funnel_history') {
            return 'Histórico Leads en LQ En El Funnel';
        }
        if ($request->query('group_type') === 'funnel') {
            return 'Leads en LQ Por Funnel';
        }

        return 'Leads en LQ En El Periodo';
    }

    private function liveMetrics(): GeneralLeadsAdsLiveMetricsService
    {
        return $this->liveMetrics ??= app(GeneralLeadsAdsLiveMetricsService::class);
    }

    private function listColumns(): array
    {
        return [
            ['key' => 'created_at', 'label' => 'Created At'],
            ['key' => 'customer_id', 'label' => 'Customer Id'],
            ['key' => 'customer_name', 'label' => 'Cliente'],
            ['key' => 'origin_name', 'label' => 'Origen Nombre'],
            ['key' => 'id', 'label' => 'Id'],
            ['key' => 'crm_id', 'label' => 'Crm Id'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'last_name', 'label' => 'Last Name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'crm_state_name', 'label' => 'Crm State/Estado CRM'],
            ['key' => 'integration_statuses_text', 'label' => 'Integracion'],
            ['key' => 'qualification_name', 'label' => 'Calificacion'],
            ['key' => 'funnel_name', 'label' => 'Funnel'],
            ['key' => 'campaign_objective_name', 'label' => 'Campaign Objective'],
            ['key' => 'source_name', 'label' => 'Source Nombre'],
            ['key' => 'type_name', 'label' => 'Medio Nombre'],
            ['key' => 'position', 'label' => 'Position'],
            ['key' => 'city', 'label' => 'City'],
            ['key' => 'company', 'label' => 'Company'],
            ['key' => 'country', 'label' => 'Country'],
            ['key' => 'service_city', 'label' => 'Service City'],
            ['key' => 'children', 'label' => 'Children'],
            ['key' => 'opening_hours', 'label' => 'Opening Hours'],
            ['key' => 'effective_lead', 'label' => 'Effective Lead'],
            ['key' => 'reference', 'label' => 'Reference'],
            ['key' => 'service', 'label' => 'Service'],
            ['key' => 'remote_ip', 'label' => 'Remote Ip'],
            ['key' => 'page', 'label' => 'Page'],
            ['key' => 'page_url', 'label' => 'Page Url'],
            ['key' => 'site_url', 'label' => 'Site Url'],
            ['key' => 'campaign_origin', 'label' => 'Campaign Origin'],
            ['key' => 'campaign_objective', 'label' => 'Campaign Objective'],
            ['key' => 'meta_id_ad', 'label' => 'Meta Id Ad'],
            ['key' => 'g_clid', 'label' => 'G Clid'],
            ['key' => 'gclid', 'label' => 'Gclid'],
            ['key' => 'gbraid', 'label' => 'Gbraid'],
            ['key' => 'wbraid', 'label' => 'Wbraid'],
            ['key' => 'gad_source', 'label' => 'Gad Source'],
            ['key' => 'gad_campaignid', 'label' => 'Gad Campaignid'],
            ['key' => 'google_ad_id', 'label' => 'Google Ad Id'],
            ['key' => 'google_adgroup_id', 'label' => 'Google Adgroup Id'],
            ['key' => 'google_campaign_id', 'label' => 'Google Campaign Id'],
            ['key' => 'matchtype', 'label' => 'Matchtype'],
            ['key' => 'device', 'label' => 'Device'],
            ['key' => 'g_ad', 'label' => 'G Ad'],
            ['key' => 'meta_lead_id', 'label' => 'Meta Lead Id'],
            ['key' => 'meta_page_id', 'label' => 'Meta Page Id'],
            ['key' => 'meta_form_id', 'label' => 'Meta Form Id'],
            ['key' => 'meta_created_time', 'label' => 'Meta Created Time'],
            ['key' => 'meta_payload', 'label' => 'Meta Payload'],
            ['key' => 'value', 'label' => 'Value'],
            ['key' => 'fbp', 'label' => 'Fbp'],
            ['key' => 'fbc', 'label' => 'Fbc'],
            ['key' => 'updated_at', 'label' => 'Updated At'],
            ['key' => 'plataforma', 'label' => 'Plataforma'],
            ['key' => 'lenguaje', 'label' => 'Lenguaje'],
            ['key' => 'geo', 'label' => 'Geo'],
            ['key' => 'number_workers', 'label' => 'Number Workers'],
            ['key' => 'number_locations', 'label' => 'Number Locations'],
            ['key' => 'campo_numero_1', 'label' => 'Campo Numero 1'],
            ['key' => 'campo_numero_2', 'label' => 'Campo Numero 2'],
            ['key' => 'campo_numero_3', 'label' => 'Campo Numero 3'],
            ['key' => 'campo_numero_4', 'label' => 'Campo Numero 4'],
            ['key' => 'campo_numero_5', 'label' => 'Campo Numero 5'],
            ['key' => 'campo_text_1', 'label' => 'Campo Text 1'],
            ['key' => 'campo_text_2', 'label' => 'Campo Text 2'],
            ['key' => 'campo_text_3', 'label' => 'Campo Text 3'],
            ['key' => 'campo_text_4', 'label' => 'Campo Text 4'],
            ['key' => 'campo_text_5', 'label' => 'Campo Text 5'],
            ['key' => 'campo_text_6', 'label' => 'Campo Text 6'],
            ['key' => 'campo_text_7', 'label' => 'Campo Text 7'],
            ['key' => 'campo_text_8', 'label' => 'Campo Text 8'],
            ['key' => 'campo_text_9', 'label' => 'Campo Text 9'],
            ['key' => 'campo_text_10', 'label' => 'Campo Text 10'],
            ['key' => 'campo_text_11', 'label' => 'Campo Text 11'],
            ['key' => 'campo_text_12', 'label' => 'Campo Text 12'],
            ['key' => 'campo_text_13', 'label' => 'Campo Text 13'],
            ['key' => 'campo_text_14', 'label' => 'Campo Text 14'],
            ['key' => 'campo_text_15', 'label' => 'Campo Text 15'],
        ];
    }

    private function listExportRow(Lead $lead, array $columns): array
    {
        return array_map(
            fn (array $column) => $this->listColumnValue($lead, $column['key']),
            $columns
        );
    }

    private function listColumnValue(Lead $lead, string $key): string
    {
        $value = match ($key) {
            'created_at', 'updated_at', 'meta_created_time' => optional($lead->{$key})->format('Y-m-d H:i:s'),
            'name' => $this->firstFilledFieldValue($lead, ['nombre', 'first_name', 'name', 'nombres']),
            'last_name' => $this->firstFilledFieldValue($lead, ['apellido', 'last_name', 'lastname', 'apellidos']),
            'phone' => $this->firstFilledFieldValue($lead, ['telefono', 'phone', 'phone_number', 'celular', 'movil']),
            'integration_statuses_text' => $this->leadIntegrationStatusesText($lead),
            'meta_payload' => $this->stringifyListValue($lead->meta_payload ?? null),
            default => data_get($lead, $key),
        };

        return $this->stringifyListValue($value);
    }

    private function stringifyListValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Si' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($value === null || $value === '') {
            return '';
        }

        return (string) $value;
    }

    private function leadIntegrationStatusesText(Lead $lead): string
    {
        $statuses = $this->formatLeadIntegrationStatuses($lead);

        if (empty($statuses)) {
            return 'Sin integraciones';
        }

        return collect($statuses)
            ->map(function (array $status) {
                $answerCode = $status['answer_code'] ? ' ('.$status['answer_code'].')' : '';

                return $status['integration'].': '.$status['status_label'].$answerCode;
            })
            ->implode(' | ');
    }

    private function formatLeadIntegrationStatuses(Lead $lead): array
    {
        $leadIntegrations = $lead->relationLoaded('leadIntegrations')
            ? $lead->leadIntegrations
            : $lead->leadIntegrations()->with('integration:id,name')->orderBy('id')->get();

        return $leadIntegrations
            ->map(function ($leadIntegration) {
                $status = strtolower(trim((string) ($leadIntegration->status ?? '')));

                return [
                    'integration' => $leadIntegration->integration?->name
                        ?: ($leadIntegration->integration_id ? 'Integracion #'.$leadIntegration->integration_id : 'Integracion'),
                    'status' => $status ?: 'unknown',
                    'status_label' => $this->leadIntegrationStatusLabel($status),
                    'answer_code' => $leadIntegration->answer_code,
                ];
            })
            ->values()
            ->all();
    }

    private function leadIntegrationStatusBadges(Lead $lead): array
    {
        return collect($this->formatLeadIntegrationStatuses($lead))
            ->map(function (array $status) {
                $answerCode = $status['answer_code'] ? ' ('.$status['answer_code'].')' : '';
                $rawAnswerCode = trim((string) ($status['answer_code'] ?? ''));

                return [
                    'text' => $status['integration'].': '.$status['status_label'].$answerCode,
                    'is_success' => $rawAnswerCode !== '' && is_numeric($rawAnswerCode) && (int) $rawAnswerCode === 200,
                ];
            })
            ->values()
            ->all();
    }

    private function leadIntegrationStatusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Completada',
            'failed' => 'Fallida',
            'pending' => 'Pendiente',
            default => $status !== '' ? Str::headline($status) : 'Sin estado',
        };
    }

    private function firstFilledFieldValue($obj, array $fields): ?string
    {
        foreach ($fields as $field) {
            $value = data_get($obj, $field);

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
