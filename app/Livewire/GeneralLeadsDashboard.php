<?php

namespace App\Livewire;

use App\Http\Services\GeneralLeads\GeneralLeadsDashboardService;
use App\Http\Services\GeneralLeads\GeneralLeadsFilters;
use Illuminate\Http\Request;
use Livewire\Attributes\Url;
use Livewire\Component;

class GeneralLeadsDashboard extends Component
{
    private const FILTER_KEYS = [
        'customer_id',
        'integration_id',
        'from',
        'to',
        'source',
        'campaign_origin',
        'plataforma',
        'crm_state',
        'qualification',
        'lenguaje',
        'geo',
    ];

    private const TABLE_SECTIONS = [
        'meta_campaigns',
        'meta_ad_sets',
        'meta_ads',
        'google_campaigns',
        'google_ad_groups',
        'google_ads',
    ];

    private const SORTABLE_COLUMNS = [
        'name',
        'cost',
        'impressions',
        'clicks',
        'ctr',
        'cpc',
        'cpm',
        'conversions',
        'roas',
        'leads',
        'qualified_leads',
        'unqualified_leads',
        'cpl',
    ];

    #[Url(as: 'customer_id', except: '')]
    public string $customerId = '';

    #[Url(as: 'integration_id', except: '')]
    public string $integrationId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(as: 'campaign_origin', except: '')]
    public string $campaignOrigin = '';

    #[Url(except: '')]
    public string $plataforma = '';

    #[Url(as: 'crm_state', except: '')]
    public string $crmState = '';

    #[Url(except: '')]
    public string $qualification = '';

    #[Url(except: '')]
    public string $lenguaje = '';

    #[Url(except: '')]
    public string $geo = '';

    #[Url(except: [])]
    public array $sort = [];

    #[Url(except: [])]
    public array $dir = [];

    public bool $filtersApplied = false;

    public bool $filtersDirty = false;

    public array $appliedQuery = [];

    public function mount(): void
    {
        $this->filtersApplied = request()->hasAny(self::FILTER_KEYS);
        $this->appliedQuery = $this->filtersApplied ? $this->query() : [];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['sort', 'dir'], true)) {
            return;
        }

        $this->filtersDirty = true;
    }

    public function applyFilters(): void
    {
        $this->appliedQuery = $this->query();
        $this->filtersApplied = true;
        $this->filtersDirty = false;
    }

    public function clearFilters(): void
    {
        $this->reset([
            'customerId',
            'integrationId',
            'source',
            'campaignOrigin',
            'plataforma',
            'crmState',
            'qualification',
            'lenguaje',
            'geo',
            'from',
            'to',
            'sort',
            'dir',
        ]);

        $this->appliedQuery = [];
        $this->filtersApplied = false;
        $this->filtersDirty = false;
    }

    public function sortBy(string $section, string $column): void
    {
        if (! in_array($section, self::TABLE_SECTIONS, true) || ! in_array($column, self::SORTABLE_COLUMNS, true)) {
            return;
        }

        $currentSort = $this->sort[$section] ?? '';
        $currentDir = $this->dir[$section] ?? 'desc';

        $this->sort[$section] = $column;
        $this->dir[$section] = $currentSort === $column && $currentDir === 'asc' ? 'desc' : 'asc';

        if ($this->filtersApplied) {
            $this->appliedQuery = $this->query();
        }
    }

    public function render()
    {
        $service = app(GeneralLeadsDashboardService::class);
        $request = $this->requestFromAppliedQuery();
        $filters = GeneralLeadsFilters::fromRequest($request);
        $dashboard = $this->filtersApplied
            ? $service->build($request, $filters, includeAds: false, includeLiveCosts: false)
            : $service->shell($filters);

        return view('livewire.general-leads-dashboard', [
            'dashboard' => $dashboard,
            'filtersQuery' => $filters->query([
                'sort' => $this->cleanSorts(),
                'dir' => $this->cleanDirections(),
            ]),
            'hasData' => $this->filtersApplied,
            'filtersDirty' => $this->filtersDirty,
        ]);
    }

    private function requestFromAppliedQuery(): Request
    {
        return Request::create(route('dashboard.general-leads', absolute: false), 'GET', $this->appliedQuery);
    }

    private function query(): array
    {
        return array_filter([
            'customer_id' => $this->customerId,
            'integration_id' => $this->integrationId,
            'from' => $this->from,
            'to' => $this->to,
            'source' => $this->source,
            'campaign_origin' => $this->campaignOrigin,
            'plataforma' => $this->plataforma,
            'crm_state' => $this->crmState,
            'qualification' => $this->qualification,
            'lenguaje' => $this->lenguaje,
            'geo' => $this->geo,
            'sort' => $this->cleanSorts(),
            'dir' => $this->cleanDirections(),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    private function cleanSorts(): array
    {
        return array_filter(
            $this->sort,
            fn ($column, $section) => in_array($section, self::TABLE_SECTIONS, true) && in_array($column, self::SORTABLE_COLUMNS, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function cleanDirections(): array
    {
        return array_filter(
            $this->dir,
            fn ($direction, $section) => in_array($section, self::TABLE_SECTIONS, true) && in_array($direction, ['asc', 'desc'], true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
