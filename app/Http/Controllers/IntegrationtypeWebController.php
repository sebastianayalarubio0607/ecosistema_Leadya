<?php

namespace App\Http\Controllers;

use App\Models\Integrationtype;
use Illuminate\Http\Request;

class IntegrationtypeWebController extends Controller
{
    public function index(Request $request)
    {
         $q = $request->get('q');

    $types = Integrationtype::query()
        ->when($q, function ($query) use ($q) {
            $query->where('name', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%");
        })
        ->orderBy('id', 'desc')
        ->paginate(15)
        ->withQueryString();

    return view('integrationtypes.index', compact('types', 'q'));
    }

    public function create()
    {
        $type = new Integrationtype();
        return view('integrationtypes.create', compact('type'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        Integrationtype::create($validated);

        return redirect()
            ->route('integrationtypes.index')
            ->with('success', 'Integration Type creado correctamente.');
    }

    public function show(Integrationtype $integrationtype)
    {
        $type = $integrationtype;
        return view('integrationtypes.show', compact('type'));
    }

    public function edit(Integrationtype $integrationtype)
    {
        $type = $integrationtype;
        return view('integrationtypes.edit', compact('type'));
    }

    public function update(Request $request, Integrationtype $integrationtype)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        $integrationtype->update($validated);

        return redirect()
            ->route('integrationtypes.index')
            ->with('success', 'Integration Type actualizado correctamente.');
    }

    public function destroy(Integrationtype $integrationtype)
    {
        $integrationtype->delete();

        return redirect()
            ->route('integrationtypes.index')
            ->with('success', 'Integration Type eliminado.');
    }
}
