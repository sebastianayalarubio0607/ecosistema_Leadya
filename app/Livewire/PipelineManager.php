<?php

namespace App\Livewire;

use App\Models\Integration;
use App\Models\Integrationtype;
use App\Models\Pipeline;
use App\Models\Customer;
use Livewire\Component;
use Livewire\WithPagination;

class PipelineManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $customerId = '';
    public string $typeId = '';
    public string $integrationId = '';

    public function updated($property): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'customerId', 'typeId', 'integrationId');
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        Pipeline::query()->findOrFail($id)->delete();
        session()->flash('success', 'Pipeline eliminado. Los CRM States asociados quedarán sin Pipeline.');
    }

    public function render()
    {
        return view('livewire.pipeline-manager', [
            'pipelines' => Pipeline::query()
                ->with(['integration:id,name,customer_id,integrationtype_id', 'integration.customer:id,name', 'integration.integrationtype:id,name'])
                ->withCount('crmStates')
                ->when(trim($this->search) !== '', function ($query): void {
                    $term = '%'.trim($this->search).'%';
                    $query->where(function ($query) use ($term): void {
                        $query->where('name', 'like', $term)
                            ->orWhere('external_id', 'like', $term);
                    });
                })
                ->when($this->customerId !== '', fn ($query) => $query->whereHas('integration', fn ($integration) => $integration->where('customer_id', $this->customerId)))
                ->when($this->typeId !== '', fn ($query) => $query->whereHas('integration', fn ($integration) => $integration->where('integrationtype_id', $this->typeId)))
                ->when($this->integrationId !== '', fn ($query) => $query->where('integration_id', $this->integrationId))
                ->orderBy('name')
                ->paginate(15),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'types' => Integrationtype::query()->orderBy('name')->get(['id', 'name']),
            'integrations' => Integration::query()
                ->with(['customer:id,name', 'integrationtype:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'customer_id', 'integrationtype_id']),
        ]);
    }
}
