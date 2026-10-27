<?php

namespace App\Livewire;

use App\Jobs\QueueHubspotOpportunitySyncRunsJob;
use App\Models\HubspotOpportunitySyncLog;
use App\Models\HubspotOpportunitySyncRun;
use App\Models\Integration;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class HubspotOpportunitySyncPanel extends Component
{
    use WithPagination;

    #[Locked]
    public int $integrationId;

    public string $notice = '';

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function mount(int $integrationId): void
    {
        $this->integrationId = $integrationId;
        $integration = Integration::with('integrationtype')->findOrFail($integrationId);
        abort_unless(Str::lower((string) $integration->integrationtype?->name) === 'hubspot', 404);
    }

    public function queueSync(): void
    {
        $this->notice = '';
        $integration = Integration::with('integrationtype')->findOrFail($this->integrationId);
        abort_unless(Str::lower((string) $integration->integrationtype?->name) === 'hubspot', 404);

        if (! $integration->status || $integration->hubspot_return_mode !== 'api') {
            $this->addError('sync', 'Activa la integración y selecciona “API cada 2 horas y webhook” para habilitar la consulta.');
            return;
        }

        if (blank($integration->hubspot_pipeline_id)) {
            $this->addError('sync', 'Selecciona y sincroniza un pipeline antes de ejecutar la consulta API.');
            return;
        }

        QueueHubspotOpportunitySyncRunsJob::queueIntegration($integration, 'manual-integration');
        $this->notice = 'La consulta de HubSpot quedó en la cola.';
        $this->resetPage();
    }

    public function render()
    {
        $runs = HubspotOpportunitySyncRun::query()
            ->where('integration_id', $this->integrationId)
            ->latest('id')
            ->limit(20)
            ->get();

        $logs = HubspotOpportunitySyncLog::query()
            ->with('lead:id,name,last_name,crm_id_oportunidad')
            ->where('integration_id', $this->integrationId)
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->paginate(25);

        $canRun = Integration::query()
            ->whereKey($this->integrationId)
            ->where('status', true)
            ->where('hubspot_return_mode', 'api')
            ->whereNotNull('hubspot_pipeline_id')
            ->exists();

        return view('livewire.hubspot-opportunity-sync-panel', compact('runs', 'logs', 'canRun'));
    }
}
