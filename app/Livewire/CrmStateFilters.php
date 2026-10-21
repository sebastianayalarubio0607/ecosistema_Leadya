<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Integration;
use App\Models\Integrationtype;
use App\Models\MetaEvent;
use App\Models\Pipeline;
use App\Models\Qualification;
use App\Models\WhatsAppEvent;
use Livewire\Component;

class CrmStateFilters extends Component
{
    public string $q = '';
    public string $name = '';
    public string $customerId = '';
    public string $typeId = '';
    public string $integrationId = '';
    public string $unmanaged = '';
    public string $qualificationId = '';
    public string $pipelineId = '';
    public string $metaEventId = '';
    public string $whatsappEventId = '';
    public string $googleAdsEnabled = '';

    public function mount(): void
    {
        $this->q = (string) request()->query('q', '');
        $this->name = (string) request()->query('name', '');
        $this->customerId = (string) request()->query('customer_id', '');
        $this->typeId = (string) request()->query('integrationtype_id', '');
        $this->integrationId = (string) request()->query('integration_id', '');
        $this->unmanaged = (string) request()->query('unmanaged', '');
        $this->qualificationId = (string) request()->query('qualification', '');
        $this->pipelineId = (string) request()->query('pipeline_id', '');
        $this->metaEventId = (string) request()->query('meta_event_id', '');
        $this->whatsappEventId = (string) request()->query('whatsapp_event_id', '');
        $this->googleAdsEnabled = (string) request()->query('google_ads_conversion_enabled', '');
    }

    public function updatedCustomerId(): void
    {
        $this->clearInvalidDependentSelections();
    }

    public function updatedTypeId(): void
    {
        $this->clearInvalidDependentSelections();
    }

    public function updatedIntegrationId(): void
    {
        $this->clearInvalidDependentSelections();
    }

    public function clearFilters()
    {
        $this->reset(
            'q', 'name', 'customerId', 'typeId', 'integrationId', 'unmanaged',
            'qualificationId', 'pipelineId', 'metaEventId', 'whatsappEventId', 'googleAdsEnabled'
        );

        return $this->redirectRoute('crmstates.index', navigate: false);
    }

    public function applyFilters()
    {
        $filters = [
            'q' => trim($this->q),
            'name' => trim($this->name),
            'customer_id' => $this->customerId,
            'integrationtype_id' => $this->typeId,
            'integration_id' => $this->integrationId,
            'unmanaged' => $this->unmanaged,
            'qualification' => $this->qualificationId,
            'pipeline_id' => $this->pipelineId,
            'meta_event_id' => $this->metaEventId,
            'whatsapp_event_id' => $this->whatsappEventId,
            'google_ads_conversion_enabled' => $this->googleAdsEnabled,
        ];

        return $this->redirectRoute('crmstates.index', array_filter(
            $filters,
            fn ($value) => $value !== null && $value !== ''
        ), navigate: false);
    }

    private function clearInvalidDependentSelections(): void
    {
        if ($this->integrationId !== '' && ! $this->integrationQuery()->whereKey($this->integrationId)->exists()) {
            $this->integrationId = '';
        }

        if ($this->pipelineId !== '' && $this->pipelineId !== 'none') {
            $validIntegrationIds = $this->integrationQuery()->select('integrations.id');
            if (! Pipeline::query()->whereKey($this->pipelineId)->whereIn('integration_id', $validIntegrationIds)->exists()) {
                $this->pipelineId = '';
            }
        }
    }

    private function integrationQuery()
    {
        return Integration::query()
            ->when($this->customerId !== '', fn ($query) => $query->where('customer_id', $this->customerId))
            ->when($this->typeId !== '', fn ($query) => $query->where('integrationtype_id', $this->typeId));
    }

    public function render()
    {
        $matchingIntegrationIds = $this->integrationQuery()->select('integrations.id');

        return view('livewire.crm-state-filters', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'types' => Integrationtype::query()->orderBy('name')->get(['id', 'name']),
            'integrations' => $this->integrationQuery()
                ->with('customer:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'customer_id', 'integrationtype_id']),
            'pipelines' => Pipeline::query()
                ->whereIn('integration_id', $matchingIntegrationIds)
                ->orderBy('name')
                ->get(['id', 'name', 'integration_id']),
            'qualifications' => Qualification::query()->orderBy('name')->get(['id', 'name']),
            'metaEvents' => MetaEvent::query()->orderBy('nombre')->get(['id', 'nombre']),
            'whatsappEvents' => WhatsAppEvent::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('event_name')
                ->get(['id', 'event_name', 'description', 'funnel_usefulness']),
        ]);
    }
}
