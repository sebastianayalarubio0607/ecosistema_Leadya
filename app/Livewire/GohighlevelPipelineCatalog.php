<?php

namespace App\Livewire;

use App\Http\Services\Integration\GohighlevelService;
use App\Models\Integration;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

class GohighlevelPipelineCatalog extends Component
{
    #[Locked]
    public int $integrationId;

    public string $inputClass = '';

    public string $labelClass = '';

    public string $locationId = '';

    public ?string $syncUrl = null;

    public string $pipelineId = '';

    #[Locked]
    public array $pipelines = [];

    public bool $loaded = false;

    public string $catalogError = '';

    public function boot(): void
    {
        // Integrations currently use the authenticated administration routes.
        abort_unless(auth()->check(), 403);
    }

    public function mount(): void
    {
        if (trim($this->locationId) !== '') {
            $this->loadPipelines();
        }
    }

    public function updatedLocationId(): void
    {
        $this->locationId = trim($this->locationId);
        $this->loadPipelines();
    }

    public function loadPipelines(): void
    {
        $this->reset('pipelines', 'pipelineId', 'loaded', 'catalogError');
        $this->resetValidation('locationId');
        $locationId = trim($this->locationId);
        if ($locationId === '') {
            return;
        }

        if (strlen($locationId) > 100 || ! preg_match('/^[a-zA-Z0-9_-]+$/', $locationId)) {
            $this->addError('locationId', 'Ingresa un locationId válido, sin espacios ni URL.');

            return;
        }

        $integration = Integration::with('integrationtype')->findOrFail($this->integrationId);
        abort_unless(app(GohighlevelService::class)->supportsOpportunity($integration), 403);

        try {
            $this->pipelines = app(GohighlevelService::class)->getPipelines($integration, $locationId);
            $this->loaded = true;
        } catch (ConnectionException $exception) {
            $this->catalogError = 'No se pudo conectar con GoHighLevel. Intenta nuevamente.';
        } catch (RuntimeException $exception) {
            $this->catalogError = $exception->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.gohighlevel-pipeline-catalog', [
            'selectedPipeline' => collect($this->pipelines)->firstWhere('id', $this->pipelineId),
        ]);
    }
}
