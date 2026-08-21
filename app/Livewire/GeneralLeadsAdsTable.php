<?php

namespace App\Livewire;

use App\Http\Services\GeneralLeads\GeneralLeadsAdsTableCacheService;
use App\Http\Services\GeneralLeads\GeneralLeadsFilters;
use App\Http\Services\GeneralLeads\GeneralLeadsPlatformRateLimiter;
use Illuminate\Http\Request;
use Livewire\Component;

class GeneralLeadsAdsTable extends Component
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

    public ?array $table = null;

    public bool $loaded = false;

    public ?string $error = null;

    public bool $waiting = false;

    public int $waitSeconds = 0;

    public ?string $waitingPlatform = null;

    public function mount(string $section, array $query): void
    {
        $this->section = $section;
        $this->query = $query;
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
        return view('livewire.general-leads-ads-table');
    }

    private function requestFromState(): Request
    {
        return Request::create(route('dashboard.general-leads', absolute: false), 'GET', array_filter(array_merge($this->query, [
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
            'totals' => [
                'name' => 'Totales',
                'cost' => '$ 0,00',
                'impressions' => '0',
                'clicks' => '0',
                'ctr' => '0,00%',
                'cpc' => '$ 0,00',
                'cpm' => '$ 0,00',
                'conversions' => '0,00',
                'roas' => 'Sin Dato',
                'leads' => '0',
                'qualified_leads' => '0',
                'unqualified_leads' => '0',
                'cpl' => '$ 0,00',
            ],
        ];
    }

    private function title(): string
    {
        return match ($this->section) {
            'meta_campaigns' => 'Campañas Meta',
            'meta_ad_sets' => 'Grupos De Anuncios Meta',
            'meta_ads' => 'Anuncios Meta',
            'google_campaigns' => 'Campañas Google',
            'google_ad_groups' => 'Grupos De Anuncios Google',
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
        if (str_contains($message, 'graph.facebook.com')) {
            return 'El servidor no pudo conectarse a graph.facebook.com. Revisa la salida HTTPS, firewall o proxy del servidor.';
        }

        if (str_contains($message, 'oauth2.googleapis.com')) {
            return 'El servidor no pudo conectarse a oauth2.googleapis.com para refrescar Google Ads. Revisa la salida HTTPS, firewall o proxy del servidor.';
        }

        if (str_contains($message, 'googleads.googleapis.com')) {
            return 'El servidor no pudo conectarse a googleads.googleapis.com para consultar Google Ads. Revisa la salida HTTPS, firewall o proxy del servidor.';
        }

        if (str_contains($message, 'credencial activa de Google Ads')) {
            return 'No hay una credencial activa de Google Ads o no fue posible refrescar el access token.';
        }

        if (str_contains($message, 'Meta no')) {
            return 'Meta no respondio la consulta. Revisa token, permisos de la cuenta publicitaria o conectividad.';
        }

        return 'No fue posible consultar la plataforma en este momento. Revisa credenciales, permisos o logs.';
    }
}
