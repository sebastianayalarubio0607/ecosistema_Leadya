<?php

namespace App\Livewire;

use App\Http\Services\GeneralLeads\GeneralLeadsAdsTableCacheService;
use App\Http\Services\GeneralLeads\GeneralLeadsFilters;
use App\Http\Services\GeneralLeads\GeneralLeadsPlatformRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Livewire\Component;

class GerencialLeadsAdsTable extends Component
{
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

    public string $section;

    public array $query = [];

    public array $sort = [];

    public array $dir = [];

    public string $customerName = '';

    public string $periodLabel = '';

    public ?array $table = null;

    public bool $loaded = false;

    public ?string $error = null;

    public bool $waiting = false;

    public int $waitSeconds = 0;

    public ?string $waitingPlatform = null;

    public function mount(string $section, array $query, string $customerName, string $periodLabel): void
    {
        $this->section = $section;
        $this->query = $query;
        $this->customerName = $customerName;
        $this->periodLabel = $periodLabel;
        $this->sort = (array) ($query['sort'] ?? []);
        $this->dir = (array) ($query['dir'] ?? []);
        $this->table = $this->placeholderTable();
    }

    public function load(): void
    {
        $this->error = null;
        $this->waiting = false;
        $this->waitSeconds = 0;
        $this->waitingPlatform = null;

        try {
            $request = $this->requestFromState();
            $filters = GeneralLeadsFilters::fromRequest($request);
            $cache = app(GeneralLeadsAdsTableCacheService::class);

            if (! $cache->has($filters, $this->section)) {
                $limit = app(GeneralLeadsPlatformRateLimiter::class)->hit($this->platform());
                if (! $limit['allowed']) {
                    $this->waiting = true;
                    $this->waitSeconds = (int) $limit['retry_after'];
                    $this->waitingPlatform = $this->platformLabel();
                    $this->table = $this->placeholderTable();
                    $this->loaded = true;

                    return;
                }
            }

            $this->table = $cache->table($filters, $request, $this->section);
            $this->loaded = true;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error = $this->userFacingError($exception->getMessage());
            $this->table = $this->placeholderTable();
            $this->loaded = true;
        }
    }

    public function retry(): void
    {
        $this->loaded = false;
        $this->waiting = false;
        $this->load();
    }

    public function sortBy(string $section, string $column): void
    {
        if ($section !== $this->section || ! in_array($column, self::SORTABLE_COLUMNS, true)) {
            return;
        }

        $currentSort = $this->sort[$section] ?? '';
        $currentDir = $this->dir[$section] ?? 'desc';

        $this->sort[$section] = $column;
        $this->dir[$section] = $currentSort === $column && $currentDir === 'asc' ? 'desc' : 'asc';
        $this->load();
    }

    public function render()
    {
        return view('livewire.gerencial-leads-ads-table', [
            'displaySection' => $this->displaySection(),
        ]);
    }

    private function displaySection(): array
    {
        $columns = collect([
            'name' => 'Nombre',
            'cost' => 'Costo',
            'impressions' => 'Impresiones',
            'clicks' => 'Clicks',
            'ctr' => 'CTR',
            'cpc' => 'CPC',
            'cpm' => 'CPM',
            'conversions' => 'Conversiones Totales',
            'roas' => 'ROAS',
            'leads' => 'Leads en LQ',
            'qualified_leads' => 'Leads en LQ calificados',
            'unqualified_leads' => 'Leads en LQ no calificados',
            'cpl' => 'CPL',
        ])->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();

        $rows = collect($this->table['rows'] ?? [])
            ->map(function (array $row) {
                $row['url'] = $this->rowUrl($row);

                return $row;
            })
            ->values()
            ->all();

        return [
            'title' => $this->title(),
            'table' => [
                'enabled' => ! empty($rows),
                'note' => empty($rows) ? 'No hay datos disponibles para los filtros seleccionados.' : null,
                'section' => $this->section,
                'sort' => $this->sort[$this->section] ?? 'cost',
                'dir' => $this->dir[$this->section] ?? 'desc',
                'columns' => $columns,
                'rows' => $rows,
            ],
            'empty_note' => 'No hay datos disponibles para los filtros seleccionados.',
        ];
    }

    private function rowUrl(array $row): ?string
    {
        $entityId = (string) ($row['entity_value'] ?? '');

        if ($entityId === '') {
            return null;
        }

        return route('dashboard.gerencial-leads.list', array_filter(array_merge(
            Arr::except($this->query, ['sort', 'dir', 'group_type', 'group_id', 'page', '_live_ads']),
            [
                'ad_section' => $this->section,
                'ad_entity_id' => $entityId,
                'ad_entity_name' => (string) ($row['name'] ?? ''),
            ]
        ), fn ($value) => $value !== null && $value !== '' && $value !== []));
    }

    private function requestFromState(): Request
    {
        return Request::create(route('dashboard.gerencial-leads', absolute: false), 'GET', array_filter(array_merge($this->query, [
            'sort' => $this->sort,
            'dir' => $this->dir,
            '_live_ads' => now()->timestamp,
        ]), fn ($value) => $value !== null && $value !== '' && $value !== []));
    }

    private function placeholderTable(): array
    {
        return [
            'title' => $this->title(),
            'section' => $this->section,
            'sort' => $this->sort[$this->section] ?? 'cost',
            'dir' => $this->dir[$this->section] ?? 'desc',
            'rows' => [],
        ];
    }

    private function title(): string
    {
        return match ($this->section) {
            'meta_campaigns' => 'Campañas Meta',
            'meta_ad_sets' => 'Grupos de anuncios Meta',
            'meta_ads' => 'Anuncios Meta',
            'google_campaigns' => 'Campañas Google',
            'google_ad_groups' => 'Grupos de anuncios Google',
            default => 'Anuncios Google',
        };
    }

    private function platform(): string
    {
        return str_starts_with($this->section, 'google_') ? 'google' : 'meta';
    }

    private function platformLabel(): string
    {
        return $this->platform() === 'google' ? 'Google Ads' : 'Meta Ads';
    }

    private function userFacingError(string $message): string
    {
        if (str_contains($message, 'oauth2.googleapis.com')) {
            return 'El servidor no pudo conectarse a oauth2.googleapis.com para refrescar Google Ads.';
        }

        if (str_contains($message, 'googleads.googleapis.com')) {
            return 'El servidor no pudo conectarse a googleads.googleapis.com para consultar Google Ads.';
        }

        if (str_contains($message, 'graph.facebook.com')) {
            return 'El servidor no pudo conectarse a graph.facebook.com para consultar Meta Ads.';
        }

        return 'No fue posible consultar la plataforma en este momento. Revisa credenciales, permisos o logs.';
    }
}
