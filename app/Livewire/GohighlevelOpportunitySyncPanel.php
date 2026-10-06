<?php

namespace App\Livewire;

use App\Http\Services\Integration\GohighlevelService;
use App\Jobs\QueueGohighlevelOpportunitySyncRunsJob;
use App\Models\GohighlevelOpportunitySyncLog;
use App\Models\GohighlevelOpportunitySyncRun;
use App\Models\Integration;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class GohighlevelOpportunitySyncPanel extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $integrationId = null;

    public string $notice = '';

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function mount(?int $integrationId = null): void
    {
        $this->integrationId = $integrationId;
        if ($integrationId !== null) {
            $integration = Integration::with('integrationtype')->findOrFail($integrationId);
            abort_unless(app(GohighlevelService::class)->supportsOpportunity($integration), 404);
        }
    }

    public function queueSync(): void
    {
        $this->notice = '';
        if ($this->integrationId !== null) {
            $integration = Integration::with('integrationtype')->findOrFail($this->integrationId);
            abort_unless(app(GohighlevelService::class)->supportsOpportunity($integration), 404);
            if ($integration->gohighlevel_return_mode !== 'api') {
                $this->addError('sync', 'Selecciona Consulta API en la configuración de la integración para activar el polling.');
                return;
            }

            QueueGohighlevelOpportunitySyncRunsJob::queueIntegration($integration, 'manual-integration');
            $this->notice = 'La consulta de esta integración quedó en la cola GoHighLevel.';
            $this->resetPage();
            return;
        }

        QueueGohighlevelOpportunitySyncRunsJob::dispatch('manual');
        $this->notice = 'La consulta global quedó en la cola GoHighLevel.';
        $this->resetPage();
    }

    public function render()
    {
        $runs = GohighlevelOpportunitySyncRun::query()
            ->with('integration:id,name')
            ->when($this->integrationId, fn ($query) => $query->where('integration_id', $this->integrationId))
            ->latest('id')
            ->limit(20)
            ->get();

        $canRun = $this->integrationId === null || Integration::query()
            ->whereKey($this->integrationId)
            ->where('gohighlevel_return_mode', 'api')
            ->exists();

        $logs = GohighlevelOpportunitySyncLog::query()
            ->with(['lead:id,name,last_name,crm_id_oportunidad', 'run:id,trigger_source,created_at'])
            ->when($this->integrationId, fn ($query) => $query->where('integration_id', $this->integrationId))
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->paginate(25);

        return view('livewire.gohighlevel-opportunity-sync-panel', compact('runs', 'logs', 'canRun'));
    }
}
