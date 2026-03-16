<?php

namespace App\Http\Controllers;

use App\Models\Geo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GeoController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->get('q');

        $geos = Geo::query()
            ->when($q, fn ($query) => $query->where(function ($innerQuery) use ($q) {
                $innerQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('geos.index', compact('geos', 'q'));
    }

    public function create(): View
    {
        return view('geos.create', [
            'geo' => new Geo(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Geo::create($this->validateRequest($request));

        return redirect()
            ->route('geos.index')
            ->with('success', 'Geo creado correctamente.');
    }

    public function show(Geo $geo): View
    {
        return view('geos.show', compact('geo'));
    }

    public function edit(Geo $geo): View
    {
        return view('geos.edit', compact('geo'));
    }

    public function update(Request $request, Geo $geo): RedirectResponse
    {
        $geo->update($this->validateRequest($request, $geo));

        return redirect()
            ->route('geos.index')
            ->with('success', 'Geo actualizado correctamente.');
    }

    public function destroy(Geo $geo): RedirectResponse
    {
        $geo->delete();

        return redirect()
            ->route('geos.index')
            ->with('success', 'Geo eliminado.');
    }

    private function validateRequest(Request $request, ?Geo $geo = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                'alpha_dash',
                Rule::unique('geos', 'code')->ignore($geo?->id),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('geos', 'name')->ignore($geo?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
