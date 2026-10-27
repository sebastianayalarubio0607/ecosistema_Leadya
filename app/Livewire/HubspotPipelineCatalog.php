<?php

namespace App\Livewire;

use App\Http\Services\Integration\HubspotPipelineSyncService;
use App\Models\Integration;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

class HubspotPipelineCatalog extends Component
{
    #[Locked]
    public int $integrationId;

    public ?string $syncUrl = null;

    public string $pipelineId = '';

    #[Locked]
    public array $pipelines = [];

    public bool $loaded = false;

    public string $catalogError = '';

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function mount(int $integrationId, ?string $syncUrl = null): void
    {
        $this->integrationId = $integrationId;
        $this->syncUrl = $syncUrl;
        $integration = $this->hubspotIntegration();
        $this->pipelineId = (string) ($integration->hubspot_pipeline_id ?? '');
        $this->loadPipelines();
    }

    public function loadPipelines(): void
    {
        $this->pipelines = [];
        $this->loaded = false;
        $this->catalogError = '';

        try {
            $integration = $this->hubspotIntegration();
            $this->pipelines = app(HubspotPipelineSyncService::class)->getPipelines($integration);
            $this->loaded = true;
            if ($this->pipelineId !== '' && ! collect($this->pipelines)->contains('id', $this->pipelineId)) {
                $this->pipelineId = '';
            }
        } catch (ConnectionException) {
            $this->catalogError = 'No se pudo conectar con HubSpot. Intenta nuevamente.';
        } catch (RuntimeException $exception) {
            $this->catalogError = $exception->getMessage();
        }
    }

    private function hubspotIntegration(): Integration
    {
        $integration = Integration::with('integrationtype')->findOrFail($this->integrationId);
        abort_unless(strtolower((string) $integration->integrationtype?->name) === 'hubspot', 404);

        return $integration;
    }

    public function render()
    {
        $integration = $this->hubspotIntegration();

        return view('livewire.hubspot-pipeline-catalog', [
            'integration' => $integration,
            'selectedPipeline' => collect($this->pipelines)->firstWhere('id', $this->pipelineId),
        ]);
    }
}
