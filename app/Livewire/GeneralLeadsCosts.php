<?php

namespace App\Livewire;

use App\Http\Services\GeneralLeads\GeneralLeadsDashboardService;
use App\Http\Services\GeneralLeads\GeneralLeadsFilters;
use App\Http\Services\GeneralLeads\GeneralLeadsPlatformRateLimiter;
use Illuminate\Http\Request;
use Livewire\Component;

class GeneralLeadsCosts extends Component
{
    public array $query = [];

    public string $mode = 'summary';

    public array $costs = [
        'meta' => 'Consultando...',
        'google' => 'Consultando...',
        'total' => 'Consultando...',
    ];

    public bool $loaded = false;

    public ?string $error = null;

    public bool $waiting = false;

    public int $waitSeconds = 0;

    public ?string $waitingPlatform = null;

    public function mount(array $query, string $mode = 'summary'): void
    {
        $this->query = $query;
        $this->mode = $mode;
    }

    public function load(): void
    {
        $this->error = null;
        $this->waiting = false;
        $this->waitSeconds = 0;
        $this->waitingPlatform = null;

        try {
            foreach (['meta' => 'Meta Ads', 'google' => 'Google Ads'] as $platform => $label) {
                $limit = app(GeneralLeadsPlatformRateLimiter::class)->hit($platform);
                if (! $limit['allowed']) {
                    $this->waiting = true;
                    $this->waitSeconds = (int) $limit['retry_after'];
                    $this->waitingPlatform = $label;
                    $this->loaded = true;

                    return;
                }
            }

            $request = Request::create(route('dashboard.general-leads', absolute: false), 'GET', $this->query);
            $filters = GeneralLeadsFilters::fromRequest($request);
            $this->costs = app(GeneralLeadsDashboardService::class)->costSummary($filters);
            $this->loaded = true;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error = $this->userFacingError($exception->getMessage());
            $this->loaded = true;
        }
    }

    public function retry(): void
    {
        $this->loaded = false;
        $this->waiting = false;
        $this->load();
    }

    public function render()
    {
        return view('livewire.general-leads-costs');
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

        return 'No fue posible consultar los costos de las plataformas.';
    }
}
