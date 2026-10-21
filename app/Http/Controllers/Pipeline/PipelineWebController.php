<?php

namespace App\Http\Controllers\Pipeline;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PipelineWebController extends Controller
{
    public function index()
    {
        return view('pipelines.index');
    }

    public function create()
    {
        return view('pipelines.create');
    }

    public function store(Request $request)
    {
        $pipeline = Pipeline::query()->create($this->validated($request));

        return redirect()->route('pipelines.show', $pipeline)->with('success', 'Pipeline creado correctamente.');
    }

    public function show(Pipeline $pipeline)
    {
        $pipeline->load(['integration.customer:id,name', 'crmStates:id,name,pipeline_id,progress_percentage']);

        return view('pipelines.show', compact('pipeline'));
    }

    public function edit(Pipeline $pipeline)
    {
        $integrations = $this->integrations();

        return view('pipelines.edit', compact('pipeline', 'integrations'));
    }

    public function update(Request $request, Pipeline $pipeline)
    {
        $validated = $this->validated($request, $pipeline);
        if ($pipeline->crmStates()->exists()
            && (int) $pipeline->integration_id !== (int) $validated['integration_id']) {
            return back()->withInput()->withErrors([
                'integration_id' => 'No puedes cambiar la integración mientras el Pipeline tenga CRM States asociados.',
            ]);
        }

        $pipeline->update($validated);

        return redirect()->route('pipelines.show', $pipeline)->with('success', 'Pipeline actualizado correctamente.');
    }

    public function destroy(Pipeline $pipeline)
    {
        $pipeline->delete();

        return redirect()->route('pipelines.index')->with('success', 'Pipeline eliminado. Los CRM States asociados quedarán sin Pipeline.');
    }

    private function validated(Request $request, ?Pipeline $pipeline = null): array
    {
        return $request->validate([
            'integration_id' => ['required', 'integer', 'exists:integrations,id'],
            'external_id' => [
                'required', 'string', 'max:255',
                Rule::unique('pipelines', 'external_id')
                    ->where(fn ($query) => $query->where('integration_id', $request->input('integration_id')))
                    ->ignore($pipeline?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
        ]);
    }

    private function integrations()
    {
        return Integration::query()
            ->with('customer:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id']);
    }
}
