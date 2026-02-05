<?php

namespace App\Http\Controllers\CrmState;

use App\Http\Controllers\Controller;
use App\Models\CrmState;
use App\Models\Integration;
use App\Models\Qualification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CrmStateWebController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');

        $crmstates = CrmState::query()
            ->with('qualificationModel:id,name')
            ->when($q, function ($query) use ($q) {
                $query->where('id', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('crm_states.index', compact('crmstates', 'q'));
    }

    public function create()
    {
        $crmstate = new CrmState();

        $integrations = Integration::with('customer:id,name')
            ->orderByDesc('id')
            ->get(['id', 'name', 'customer_id']);

        $qualifications = Qualification::orderBy('name')->get(['id', 'name']);

        return view('crm_states.create', compact('crmstate', 'integrations', 'qualifications'));
    }

    public function store(Request $request)
    {
        // Construimos el ID final como: integration_id + '-' + external_id
        $integrationId = (string) $request->input('integration_id');
        $externalId    = (string) $request->input('external_id');
        $finalId       = $integrationId . '-' . $externalId;

        $data = $request->all();
        $data['id'] = $finalId;

        $validator = Validator::make($data, [
            'integration_id' => ['required', 'exists:integrations,id'],
            'external_id'    => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'], // status_id de CRM suele ser numérico
            'id'             => ['required', 'string', 'max:255', Rule::unique('crm_state', 'id')],
            'name'           => ['required', 'string', 'max:255'],
            'qualification'  => ['required', 'exists:qualification,id'],
        ], [
           'external_id.regex' => 'El ID ingresado solo puede tener letras, números, guion (-) y guion bajo (_).',
        ]);

        $validator->validate();

        CrmState::create([
            'id' => $finalId,
            'name' => $request->string('name'),
            'qualification' => $request->input('qualification'),
        ]);

        return redirect()
            ->route('crmstates.index')
            ->with('success', 'CRM State creado correctamente.');
    }

    public function show(CrmState $crmstate)
    {
        $crmstate->load('qualificationModel:id,name');

        [$integrationId, $externalId] = $this->splitId($crmstate->id);

        $integration = Integration::with('customer:id,name')
            ->find($integrationId);

        return view('crm_states.show', compact('crmstate', 'integrationId', 'externalId', 'integration'));
    }

    public function edit(CrmState $crmstate)
    {
        $crmstate->load('qualificationModel:id,name');

        [$integrationId, $externalId] = $this->splitId($crmstate->id);

        $integration = Integration::with('customer:id,name')
            ->find($integrationId);

        $qualifications = Qualification::orderBy('name')->get(['id', 'name']);

        return view('crm_states.edit', compact('crmstate', 'integrationId', 'externalId', 'integration', 'qualifications'));
    }

    public function update(Request $request, CrmState $crmstate)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'qualification' => ['required', 'exists:qualification,id'],
        ]);

        $crmstate->update($validated);

        return redirect()
            ->route('crmstates.index')
            ->with('success', 'CRM State actualizado correctamente.');
    }

    public function destroy(CrmState $crmstate)
    {
        // Seguridad opcional: si hay leads usando ese estado, no borrar
        if (method_exists($crmstate, 'leads') && $crmstate->leads()->exists()) {
            return back()->with('success', 'No se puede eliminar: hay leads asociados a este estado.');
        }

        $crmstate->delete();

        return redirect()
            ->route('crmstates.index')
            ->with('success', 'CRM State eliminado.');
    }

    private function splitId(string $id): array
    {
        $parts = explode('-', $id, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
