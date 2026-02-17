<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Integrationtype;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IntegrationWebController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');

        $integrations = Integration::query()
            ->with(['customer:id,name', 'integrationtype:id,name'])
            ->when($q, function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('url', 'like', "%{$q}%")
                    ->orWhere('public_key', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('integrations.index', compact('integrations', 'q'));
    }

    public function create()
    {
        $integration = new Integration();

        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $types = Integrationtype::orderBy('name')->get(['id', 'name']);

        return view('integrations.create', compact('integration', 'customers', 'types'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'integrationtype_id' => 'required|exists:integrationtypes,id',
            'customer_id' => 'required|exists:customers,id',
            'url' => 'required|url',
            'tokent' => 'nullable|string',
            'status' => 'required|boolean',
            'crm_Id_phone' => ['nullable', 'string', 'max:255'],
            'crm_Id_service' => ['nullable', 'string', 'max:255'],
            'crm_Id_fuente' => ['nullable', 'string', 'max:255'],
            'crm_Id_email' => ['nullable', 'string', 'max:255'],
        ]);

        // ✅ Generar public_key aleatorio y único
        $validated['public_key'] = $this->generatePublicKey();

        $integration = Integration::create($validated);

        return redirect()
            ->route('integrations.show', $integration)
            ->with('success', 'Integración creada correctamente.');
    }

    public function show(Integration $integration)
    {
        $integration->load(['customer:id,name', 'integrationtype:id,name']);

        return view('integrations.show', compact('integration'));
    }

    public function edit(Integration $integration)
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $types = Integrationtype::orderBy('name')->get(['id', 'name']);

        return view('integrations.edit', compact('integration', 'customers', 'types'));
    }

    public function update(Request $request, Integration $integration)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'integrationtype_id' => 'required|exists:integrationtypes,id',
            'customer_id' => 'required|exists:customers,id',
            'url' => 'required|url',
            'tokent' => 'nullable|string',
            'status' => 'required|boolean',
            'crm_Id_phone' => ['nullable', 'string', 'max:255'],
            'crm_Id_service' => ['nullable', 'string', 'max:255'],
            'crm_Id_fuente' => ['nullable', 'string', 'max:255'],
            'crm_Id_email' => ['nullable', 'string', 'max:255'],
            'regenerate_public_key' => 'nullable|boolean',
        ]);

        if ($request->boolean('regenerate_public_key')) {
            $validated['public_key'] = $this->generatePublicKey();
        } else {
            unset($validated['regenerate_public_key']);
        }

        $integration->update($validated);

        return redirect()
            ->route('integrations.show', $integration)
            ->with('success', 'Integración actualizada.');
    }

    public function destroy(Integration $integration)
    {
        $integration->delete();

        return redirect()
            ->route('integrations.index')
            ->with('success', 'Integración eliminada.');
    }

    private function generatePublicKey(): string
    {
        do {
            $key = 'pk_' . Str::random(32);
        } while (Integration::where('public_key', $key)->exists());

        return $key;
    }
}
