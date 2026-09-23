<?php

namespace App\Livewire;

use App\Http\Services\Integration\FreshworksOpportunityIntegrationService;
use App\Models\Integration;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

class FreshworksOpportunityPipelineCatalog extends Component
{
    #[Locked]
    public int $integrationId;
    public ?string $syncUrl = null;
    public string $pipelineId = '';
    #[Locked]
    public array $pipelines = [];
    #[Locked]
    public array $stages = [];
    public bool $loaded = false;
    public string $catalogError = '';

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function mount(): void
    {
        $this->loadPipelines();
    }

    public function loadPipelines(): void
    {
        $this->reset('pipelineId', 'pipelines', 'stages', 'loaded', 'catalogError');
        try {
            $integration = Integration::with('integrationtype:id,name')->findOrFail($this->integrationId);
            $service = app(FreshworksOpportunityIntegrationService::class);
            abort_unless($service->supports($integration), 403);
            $this->pipelines = $service->getPipelines($integration);
            $this->loaded = true;
        } catch (ConnectionException) {
            $this->catalogError = 'No se pudo conectar con Freshworks. Intenta nuevamente.';
        } catch (RuntimeException $exception) {
            $this->catalogError = $exception->getMessage();
        }
    }

    public function updatedPipelineId(): void
    {
        $this->stages = [];
        if ($this->pipelineId === '') {
            return;
        }

        try {
            $integration = Integration::with('integrationtype:id,name')->findOrFail($this->integrationId);
            $this->stages = app(FreshworksOpportunityIntegrationService::class)->getStages($integration, $this->pipelineId);
        } catch (ConnectionException) {
            $this->catalogError = 'No se pudo conectar con Freshworks. Intenta nuevamente.';
        } catch (RuntimeException $exception) {
            $this->catalogError = $exception->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.freshworks-opportunity-pipeline-catalog', [
            'selectedPipeline' => collect($this->pipelines)->firstWhere('id', $this->pipelineId),
        ]);
    }
}
