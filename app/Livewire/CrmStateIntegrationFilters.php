<?php

namespace App\Livewire;

use App\Models\Integration;
use Livewire\Component;

class CrmStateIntegrationFilters extends Component
{
    public string $customerId = '';
    public string $typeId = '';
    public string $integrationId = '';

    public function mount(string $customerId = '', string $typeId = '', string $integrationId = ''): void
    {
        $this->customerId = $customerId;
        $this->typeId = $typeId;
        $this->integrationId = $integrationId;
        $this->clearInvalidIntegration();
    }

    public function updatedCustomerId(): void
    {
        $this->clearInvalidIntegration();
    }

    public function updatedTypeId(): void
    {
        $this->clearInvalidIntegration();
    }

    private function clearInvalidIntegration(): void
    {
        if ($this->integrationId === '') {
            return;
        }

        $matchesFilters = Integration::query()
            ->whereKey($this->integrationId)
            ->when($this->customerId !== '', fn ($query) => $query->where('customer_id', $this->customerId))
            ->when($this->typeId !== '', fn ($query) => $query->where('integrationtype_id', $this->typeId))
            ->exists();

        if (! $matchesFilters) {
            $this->integrationId = '';
        }
    }

    public function render()
    {
        return view('livewire.crm-state-integration-filters', [
            'customers' => \App\Models\Customer::query()->orderBy('name')->get(['id', 'name']),
            'types' => \App\Models\Integrationtype::query()->orderBy('name')->get(['id', 'name']),
            'integrations' => Integration::query()
                ->with('customer:id,name')
                ->when($this->customerId !== '', fn ($query) => $query->where('customer_id', $this->customerId))
                ->when($this->typeId !== '', fn ($query) => $query->where('integrationtype_id', $this->typeId))
                ->orderBy('name')
                ->get(['id', 'name', 'customer_id', 'integrationtype_id']),
        ]);
    }
}
