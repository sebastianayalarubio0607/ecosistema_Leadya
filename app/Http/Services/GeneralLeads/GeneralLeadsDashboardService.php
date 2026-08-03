<?php

namespace App\Http\Services\GeneralLeads;

use App\Models\CrmState;
use App\Models\Customer;
use App\Models\Funnel;
use App\Models\GoogleAdsAd;
use App\Models\GoogleAdsAdGroup;
use App\Models\GoogleAdsCampaign;
use App\Models\Integration;
use App\Models\MetaAdInsight;
use App\Models\Origin;
use App\Models\Platform;
use App\Models\Qualification;
use App\Models\Source;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeneralLeadsDashboardService
{
    private const UNQUALIFIED = ['lead no efectivo', 'sin gestionar', 'sin respuesta', 'n/a', 'no efectivo', 'duplicado', 'spam'];
    private const SORTS = [
        'name' => 'name_value',
        'cost' => 'cost_value',
        'impressions' => 'impressions_value',
        'conversions' => 'conversions_value',
        'roas' => 'roas_value',
        'leads' => 'leads_value',
        'qualified_leads' => 'qualified_value',
        'unqualified_leads' => 'unqualified_value',
        'cpl' => 'cpl_value',
    ];

    public function __construct(private readonly GeneralLeadsLeadQuery $leads)
    {
    }

    public function build(Request $request, GeneralLeadsFilters $filters): array
    {
        $customers = Customer::query()->orderBy('name')->get(['id', 'name']);
        $selectedCustomer = $filters->customerId ? $customers->firstWhere('id', $filters->customerId) : null;
        $breakdowns = [
            'source' => $this->sourceBreakdown($filters),
            'origin' => $this->breakdown($filters, 'origin'),
            'type' => $this->breakdown($filters, 'type'),
        ];

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
            'summary' => $this->summary($filters),
            'costs' => $this->costs($filters),
            'breakdowns' => $breakdowns,
            'funnels' => $this->funnels($filters),
            'ads' => [
                'meta_campaigns' => $this->metaTable($filters, $request, 'meta_campaigns'),
                'meta_ad_sets' => $this->metaTable($filters, $request, 'meta_ad_sets'),
                'meta_ads' => $this->metaTable($filters, $request, 'meta_ads'),
                'google_campaigns' => $this->googleTable($filters, $request, GoogleAdsCampaign::class, 'google_campaigns', 'google_campaign_id', 'campaign_name'),
                'google_ad_groups' => $this->googleTable($filters, $request, GoogleAdsAdGroup::class, 'google_ad_groups', 'google_ad_group_id', 'ad_group_name'),
                'google_ads' => $this->googleTable($filters, $request, GoogleAdsAd::class, 'google_ads', 'google_ad_id', 'google_ad_id'),
            ],
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
        ];
    }

    public function list(Request $request, GeneralLeadsFilters $filters): array
    {
        $query = $this->leads->base($filters)
            ->with(['customer:id,name', 'integration:id,name'])
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
            $query->join('lead_funnel_histories as lfh_list', function ($join) use ($filters) {
                $join->on('lfh_list.lead_id', '=', 'leads.id')
                    ->whereBetween('lfh_list.created_at', [$filters->from, $filters->to]);
            })
                ->where('lfh_list.funnel_id', $groupId)
                ->distinct();
        }

        return [
            'title' => $this->listTitle($request),
            'period' => $filters->from->format('Y-m-d H:i').' A '.$filters->to->format('Y-m-d H:i'),
            'back_url' => route('dashboard.general-leads', $filters->query()),
            'relation_columns' => $this->relationColumns(),
            'lead_columns' => $this->leadColumns(),
            'leads' => $query->orderByDesc('leads.created_at')->paginate(25)->withQueryString(),
        ];
    }

    private function summary(GeneralLeadsFilters $filters): array
    {
        $base = $this->leads->base($filters);
        $funnels = $this->funnelIds();
        $sales = $this->sales($filters, $funnels['sales']);

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
                'sales' => isset($funnels['sales'][0]) ? $this->listUrl($filters, ['group_type' => 'funnel', 'group_id' => $funnels['sales'][0]]) : null,
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

        $row = $this->leads->base($filters)
            ->leftJoin('crm_state as cs_s', 'cs_s.id', '=', 'leads.crm_state')
            ->leftJoin('qualification as q_s', 'q_s.id', '=', 'cs_s.qualification')
            ->whereIn('q_s.funnel_id', $ids)
            ->selectRaw('COUNT(DISTINCT leads.id) as total, COALESCE(SUM(COALESCE(leads.value, 0)), 0) as value')
            ->first();

        return ['count' => (int) ($row->total ?? 0), 'value' => GeneralLeadsPresentation::money($row->value ?? 0)];
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
                    'name' => GeneralLeadsPresentation::title($row->group_label),
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
        $meta = (float) MetaAdInsight::query()
            ->join('meta_ads as ma_c', 'ma_c.id', '=', 'meta_ad_insights.meta_ad_id')
            ->join('meta_ad_sets as mas_c', 'mas_c.id', '=', 'ma_c.meta_ad_set_id')
            ->join('meta_campaigns as mc_c', 'mc_c.id', '=', 'mas_c.meta_campaign_id')
            ->join('meta_ad_accounts as maa_c', 'maa_c.id', '=', 'mc_c.meta_ad_account_id')
            ->whereBetween('meta_ad_insights.date_start', [$filters->from->toDateString(), $filters->to->toDateString()])
            ->when($filters->customerId, fn ($q) => $q->where('maa_c.customer_id', $filters->customerId))
            ->sum('meta_ad_insights.spend');

        $google = (float) GoogleAdsCampaign::query()
            ->whereBetween('report_date', [$filters->from->toDateString(), $filters->to->toDateString()])
            ->when($filters->customerId, fn ($q) => $q->where('customer_id', $filters->customerId))
            ->sum('cost');

        return ['meta' => GeneralLeadsPresentation::money($meta), 'google' => GeneralLeadsPresentation::money($google), 'total' => GeneralLeadsPresentation::money($meta + $google)];
    }

    private function funnels(GeneralLeadsFilters $filters): array
    {
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
            ->join('funnels as f_history', 'f_history.id', '=', 'lfh.funnel_id')
            ->whereBetween('lfh.created_at', [$filters->from, $filters->to])
            ->whereBetween('leads.created_at', [$filters->from, $filters->to]);
        $this->applyHistoryFilters($historyQuery, $filters);
        $history = $historyQuery
            ->selectRaw("f_history.id as funnel_id, COALESCE(NULLIF(f_history.name, ''), 'Sin Funnel') as name, COUNT(DISTINCT lfh.lead_id) as total")
            ->groupBy('f_history.id', 'f_history.name')
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
            ->join('funnels as f_day', 'f_day.id', '=', 'lfh.funnel_id')
            ->whereBetween('lfh.created_at', [$filters->from, $filters->to])
            ->whereBetween('leads.created_at', [$filters->from, $filters->to]);
        $this->applyHistoryFilters($dailyQuery, $filters);
        $daily = $dailyQuery
            ->selectRaw('DATE(lfh.created_at) as day, f_day.name as name, COUNT(DISTINCT lfh.lead_id) as total')
            ->groupByRaw('DATE(lfh.created_at), f_day.id, f_day.name')
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
        $config = match ($section) {
            'meta_campaigns' => ['title' => 'Campañas Meta', 'id' => 'mc.id', 'name' => 'mc.name'],
            'meta_ad_sets' => ['title' => 'Grupos De Anuncios Meta', 'id' => 'mas.id', 'name' => 'mas.name'],
            default => ['title' => 'Anuncios Meta', 'id' => 'ma.id', 'name' => 'ma.name'],
        };
        $costs = MetaAdInsight::query()
            ->join('meta_ads as ma', 'ma.id', '=', 'meta_ad_insights.meta_ad_id')
            ->join('meta_ad_sets as mas', 'mas.id', '=', 'ma.meta_ad_set_id')
            ->join('meta_campaigns as mc', 'mc.id', '=', 'mas.meta_campaign_id')
            ->join('meta_ad_accounts as maa', 'maa.id', '=', 'mc.meta_ad_account_id')
            ->whereBetween('meta_ad_insights.date_start', [$filters->from->toDateString(), $filters->to->toDateString()])
            ->when($filters->customerId, fn ($q) => $q->where('maa.customer_id', $filters->customerId))
            ->selectRaw("{$config['id']} as entity_id, COALESCE(NULLIF({$config['name']}, ''), 'Sin Nombre') as name_value, SUM(COALESCE(meta_ad_insights.spend, 0)) as cost_value, SUM(COALESCE(meta_ad_insights.impressions, 0)) as impressions_value, 0 as conversions_value, AVG(meta_ad_insights.purchase_roas) as roas_value")
            ->groupByRaw("{$config['id']}, {$config['name']}")
            ->get()
            ->keyBy('entity_id');

        return $this->formatAds($request, $section, $config['title'], $costs, $this->metaLeadRows($filters, $section));
    }

    private function metaLeadRows(GeneralLeadsFilters $filters, string $section): Collection
    {
        $id = match ($section) {
            'meta_campaigns' => 'mc_l.id',
            'meta_ad_sets' => 'mas_l.id',
            default => 'ma_l.id',
        };

        return $this->leads->base($filters)
            ->join('meta_ads as ma_l', 'ma_l.meta_ad_id', '=', 'leads.meta_id_ad')
            ->join('meta_ad_sets as mas_l', 'mas_l.id', '=', 'ma_l.meta_ad_set_id')
            ->join('meta_campaigns as mc_l', 'mc_l.id', '=', 'mas_l.meta_campaign_id')
            ->leftJoin('crm_state as cs_a', 'cs_a.id', '=', 'leads.crm_state')
            ->leftJoin('qualification as q_a', 'q_a.id', '=', 'cs_a.qualification')
            ->selectRaw("{$id} as entity_id, COUNT(DISTINCT leads.id) as leads_value, COUNT(DISTINCT CASE WHEN q_a.name IS NOT NULL AND LOWER(TRIM(q_a.name)) NOT IN ({$this->excludedSql()}) THEN leads.id END) as qualified_value")
            ->groupByRaw($id)
            ->get()
            ->keyBy('entity_id');
    }

    private function googleTable(GeneralLeadsFilters $filters, Request $request, string $model, string $section, string $idColumn, string $nameColumn): array
    {
        $table = (new $model())->getTable();
        $title = match ($section) {
            'google_campaigns' => 'Campañas Google',
            'google_ad_groups' => 'Grupos De Anuncios Google',
            default => 'Anuncios Google',
        };
        $costs = $model::query()
            ->whereBetween("{$table}.report_date", [$filters->from->toDateString(), $filters->to->toDateString()])
            ->when($filters->customerId, fn ($q) => $q->where("{$table}.customer_id", $filters->customerId))
            ->selectRaw("{$table}.{$idColumn} as entity_id, COALESCE(NULLIF({$table}.{$nameColumn}, ''), 'Sin Nombre') as name_value, SUM(COALESCE({$table}.cost, 0)) as cost_value, SUM(COALESCE({$table}.impressions, 0)) as impressions_value, SUM(COALESCE({$table}.conversions, 0)) as conversions_value, AVG({$table}.roas) as roas_value")
            ->groupByRaw("{$table}.{$idColumn}, {$table}.{$nameColumn}")
            ->get()
            ->keyBy('entity_id');

        return $this->formatAds($request, $section, $title, $costs, $this->googleLeadRows($filters, $section));
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

    private function formatAds(Request $request, string $section, string $title, Collection $costs, Collection $leads): array
    {
        $rows = [];
        foreach ($costs as $id => $cost) {
            $lead = $leads->get($id);
            $count = (int) ($lead?->leads_value ?? 0);
            $qualified = (int) ($lead?->qualified_value ?? 0);
            $costValue = (float) ($cost->cost_value ?? 0);
            $rows[] = [
                'name_value' => (string) $cost->name_value,
                'cost_value' => $costValue,
                'impressions_value' => (int) ($cost->impressions_value ?? 0),
                'conversions_value' => (float) ($cost->conversions_value ?? 0),
                'roas_value' => $cost->roas_value !== null ? (float) $cost->roas_value : null,
                'leads_value' => $count,
                'qualified_value' => $qualified,
                'unqualified_value' => max(0, $count - $qualified),
                'cpl_value' => $count > 0 ? $costValue / $count : 0,
            ];
        }
        $sort = $request->input("sort.{$section}", 'cost');
        $dir = $request->input("dir.{$section}", 'desc') === 'asc' ? 'asc' : 'desc';
        $sortKey = self::SORTS[$sort] ?? 'cost_value';
        usort($rows, fn ($a, $b) => ($dir === 'asc' ? 1 : -1) * ($sortKey === 'name_value' ? strcasecmp($a[$sortKey], $b[$sortKey]) : ($a[$sortKey] <=> $b[$sortKey])));
        $rows = array_slice($rows, 0, 100);
        $validRoas = array_values(array_filter(array_column($rows, 'roas_value'), fn ($v) => $v !== null));
        $totalLeads = array_sum(array_column($rows, 'leads_value'));
        $totalCost = array_sum(array_column($rows, 'cost_value'));

        return [
            'title' => $title,
            'section' => $section,
            'sort' => $sort,
            'dir' => $dir,
            'rows' => array_map(fn ($r) => $this->adRow($r), $rows),
            'totals' => $this->adRow([
                'name_value' => 'Totales',
                'cost_value' => $totalCost,
                'impressions_value' => array_sum(array_column($rows, 'impressions_value')),
                'conversions_value' => array_sum(array_column($rows, 'conversions_value')),
                'roas_value' => $validRoas === [] ? null : array_sum($validRoas) / count($validRoas),
                'leads_value' => $totalLeads,
                'qualified_value' => array_sum(array_column($rows, 'qualified_value')),
                'unqualified_value' => array_sum(array_column($rows, 'unqualified_value')),
                'cpl_value' => $totalLeads > 0 ? $totalCost / $totalLeads : 0,
            ]),
        ];
    }

    private function adRow(array $row): array
    {
        return [
            'name' => GeneralLeadsPresentation::title($row['name_value']),
            'cost' => GeneralLeadsPresentation::money($row['cost_value']),
            'impressions' => number_format((float) $row['impressions_value'], 0, ',', '.'),
            'conversions' => number_format((float) $row['conversions_value'], 2, ',', '.'),
            'roas' => $row['roas_value'] === null ? 'Sin Dato' : number_format((float) $row['roas_value'], 2, ',', '.'),
            'leads' => number_format((float) $row['leads_value'], 0, ',', '.'),
            'qualified_leads' => number_format((float) $row['qualified_value'], 0, ',', '.'),
            'unqualified_leads' => number_format((float) $row['unqualified_value'], 0, ',', '.'),
            'cpl' => GeneralLeadsPresentation::money($row['cpl_value']),
        ];
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

    private function listTitle(Request $request): string
    {
        $scope = (string) $request->query('scope', 'total');
        if ($scope === 'managed') {
            return 'Leads Gestionados';
        }
        if ($scope === 'unmanaged') {
            return 'Leads No Gestionados';
        }
        if ($request->query('group_type') === 'funnel_history') {
            return 'Histórico Leads En El Funnel';
        }
        if ($request->query('group_type') === 'funnel') {
            return 'Leads Por Funnel';
        }

        return 'Leads En El Periodo';
    }

    private function relationColumns(): array
    {
        return [
            'customer_name' => 'Cliente',
            'integration_name' => 'Integracion',
            'crm_state_name' => 'Estado CRM',
            'qualification_name' => 'Calificacion',
            'funnel_name' => 'Funnel',
            'campaign_objective_name' => 'Campaign Objective',
            'source_name' => 'Source Nombre',
            'origin_name' => 'Origen Nombre',
            'type_name' => 'Medio Nombre',
        ];
    }

    private function leadColumns(): array
    {
        return collect(Schema::getColumnListing('leads'))
            ->mapWithKeys(fn (string $column) => [$column => GeneralLeadsPresentation::title(str_replace('_', ' ', $column))])
            ->all();
    }
}
