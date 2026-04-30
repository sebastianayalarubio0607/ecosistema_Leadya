<?php

namespace App\Http\Controllers\CrmState;

use App\Http\Controllers\Controller;
use App\Models\CrmState;
use App\Models\Integration;
use App\Models\MetaEvent;
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
            ->with([
                'qualificationModel:id,name',
                'metaEvent:id,nombre',
            ])
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

        $integrations = Integration::with('customer:id,name,id_Gads')
            ->orderByDesc('id')
            ->get(['id', 'name', 'customer_id']);

        $qualifications = Qualification::orderBy('name')->get(['id', 'name']);

        $metaEvents = MetaEvent::orderBy('nombre')->get(['id', 'nombre', 'estados']);

        return view('crm_states.create', compact('crmstate', 'integrations', 'qualifications', 'metaEvents'));
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
            'external_id'    => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'id'             => ['required', 'string', 'max:255', Rule::unique('crm_state', 'id')],
            'name'           => ['required', 'string', 'max:255'],
            'qualification'  => ['required', 'exists:qualification,id'],
            'meta_event_id'  => ['nullable', 'exists:meta_events,id'],
            'unmanaged'      => ['nullable', 'boolean'],
            'google_ads_conversion_enabled' => ['nullable', 'boolean'],
            'google_ads_conversion_action_id' => ['nullable', 'required_if:google_ads_conversion_enabled,1', 'string', 'max:64'],
            'google_ads_conversion_action_name' => ['nullable', 'string', 'max:255'],
            'google_ads_conversion_action_resource_name' => ['nullable', 'required_if:google_ads_conversion_enabled,1', 'string', 'max:255'],
            'google_ads_conversion_value' => ['nullable', 'numeric', 'min:0'],
            'google_ads_conversion_currency' => ['nullable', 'string', 'size:3'],
        ], [
            'external_id.regex' => 'El ID ingresado solo puede tener letras, números, guion (-) y guion bajo (_).',
        ]);

        $validator->validate();

        CrmState::create([
            'id' => $finalId,
            'name' => $request->string('name'),
            'qualification' => $request->input('qualification'),
            'meta_event_id' => $request->input('meta_event_id'),
            'unmanaged' => $request->boolean('unmanaged'),
            'google_ads_conversion_enabled' => $request->boolean('google_ads_conversion_enabled'),
            'google_ads_conversion_action_id' => $request->input('google_ads_conversion_action_id'),
            'google_ads_conversion_action_name' => $request->input('google_ads_conversion_action_name'),
            'google_ads_conversion_action_resource_name' => $request->input('google_ads_conversion_action_resource_name'),
            'google_ads_conversion_value' => $request->input('google_ads_conversion_value'),
            'google_ads_conversion_currency' => $this->normalizeCurrency($request->input('google_ads_conversion_currency')),
        ]);

        return redirect()
            ->route('crmstates.index')
            ->with('success', 'CRM State creado correctamente.');
    }

    public function show(CrmState $crmstate)
    {
        $crmstate->load([
            'qualificationModel:id,name',
            'metaEvent:id,nombre,estados',
        ]);

        [$integrationId, $externalId] = $this->splitId($crmstate->id);

        $integration = Integration::with('customer:id,name,id_Gads')
            ->find($integrationId);

        return view('crm_states.show', compact('crmstate', 'integrationId', 'externalId', 'integration'));
    }

    public function edit(CrmState $crmstate)
    {
        $crmstate->load([
            'qualificationModel:id,name',
            'metaEvent:id,nombre,estados',
        ]);

        [$integrationId, $externalId] = $this->splitId($crmstate->id);

        $integration = Integration::with('customer:id,name,id_Gads')
            ->find($integrationId);

        $qualifications = Qualification::orderBy('name')->get(['id', 'name']);
        $metaEvents = MetaEvent::orderBy('nombre')->get(['id', 'nombre', 'estados']);

        return view('crm_states.edit', compact(
            'crmstate',
            'integrationId',
            'externalId',
            'integration',
            'qualifications',
            'metaEvents'
        ));
    }

    public function update(Request $request, CrmState $crmstate)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'qualification' => ['required', 'exists:qualification,id'],
            'meta_event_id' => ['nullable', 'exists:meta_events,id'],
            'unmanaged'     => ['nullable', 'boolean'],
            'google_ads_conversion_enabled' => ['nullable', 'boolean'],
            'google_ads_conversion_action_id' => ['nullable', 'required_if:google_ads_conversion_enabled,1', 'string', 'max:64'],
            'google_ads_conversion_action_name' => ['nullable', 'string', 'max:255'],
            'google_ads_conversion_action_resource_name' => ['nullable', 'required_if:google_ads_conversion_enabled,1', 'string', 'max:255'],
            'google_ads_conversion_value' => ['nullable', 'numeric', 'min:0'],
            'google_ads_conversion_currency' => ['nullable', 'string', 'size:3'],
        ]);

        $validated['unmanaged'] = $request->boolean('unmanaged');
        $validated['google_ads_conversion_enabled'] = $request->boolean('google_ads_conversion_enabled');
        $validated['google_ads_conversion_currency'] = $this->normalizeCurrency($validated['google_ads_conversion_currency'] ?? null);

        $crmstate->update($validated);

        return redirect()
            ->route('crmstates.index')
            ->with('success', 'CRM State actualizado correctamente.');
    }

    public function destroy(CrmState $crmstate)
    {
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

    private function normalizeCurrency(?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency));

        return $currency !== '' ? $currency : 'COP';
    }
}
