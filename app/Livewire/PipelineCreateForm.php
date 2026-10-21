<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Integration;
use App\Models\Pipeline;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PipelineCreateForm extends Component
{
    public string $customerId = '';
    public string $integrationId = '';
    public string $externalId = '';
    public string $name = '';

    public function updatedCustomerId(): void
    {
        $this->integrationId = '';
        $this->resetValidation('integrationId');
    }

    public function save()
    {
        $validated = $this->validate([
            'customerId' => ['required', 'integer', 'exists:customers,id'],
            'integrationId' => [
                'required',
                'integer',
                Rule::exists('integrations', 'id')->where('customer_id', $this->customerId),
            ],
            'externalId' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pipelines', 'external_id')
                    ->where(fn ($query) => $query->where('integration_id', $this->integrationId)),
            ],
            'name' => ['required', 'string', 'max:255'],
        ], [], [
            'customerId' => 'cliente',
            'integrationId' => 'integración',
            'externalId' => 'ID externo',
            'name' => 'nombre',
        ]);

        $pipeline = Pipeline::query()->create([
            'integration_id' => $validated['integrationId'],
            'external_id' => trim($validated['externalId']),
            'name' => trim($validated['name']),
        ]);

        session()->flash('success', 'Pipeline creado correctamente.');

        return $this->redirectRoute('pipelines.show', ['pipeline' => $pipeline], navigate: false);
    }

    public function render()
    {
        return view('livewire.pipeline-create-form', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'integrations' => $this->customerId !== ''
                ? Integration::query()
                    ->where('customer_id', $this->customerId)
                    ->with('integrationtype:id,name')
                    ->orderBy('name')
                    ->get(['id', 'name', 'integrationtype_id', 'customer_id'])
                : collect(),
        ]);
    }
}
