<?php

namespace App\Http\Controllers;

use App\Models\Origin;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OriginController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->get('q');

        $origins = Origin::query()
            ->with('source')
            ->when($q, fn ($query) => $query->where(function ($innerQuery) use ($q) {
                $innerQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('origins.index', compact('origins', 'q'));
    }

    public function create(): View
    {
        return view('origins.create', [
            'origin' => new Origin(),
            'sources' => $this->sourceOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Origin::create($this->validateRequest($request));

        return redirect()
            ->route('origins.index')
            ->with('success', 'Origen creado correctamente.');
    }

    public function show(Origin $origin): View
    {
        $origin->load('source');

        return view('origins.show', compact('origin'));
    }

    public function edit(Origin $origin): View
    {
        return view('origins.edit', [
            'origin' => $origin,
            'sources' => $this->sourceOptions(),
        ]);
    }

    public function update(Request $request, Origin $origin): RedirectResponse
    {
        $origin->update($this->validateRequest($request, $origin));

        return redirect()
            ->route('origins.index')
            ->with('success', 'Origen actualizado correctamente.');
    }

    public function destroy(Origin $origin): RedirectResponse
    {
        $origin->delete();

        return redirect()
            ->route('origins.index')
            ->with('success', 'Origen eliminado.');
    }

    private function validateRequest(Request $request, ?Origin $origin = null): array
    {
        $validated = $request->validate([
            'source_id' => ['nullable', 'exists:sources,id'],
            'code' => [
                'required',
                'string',
                'max:20',
                'alpha_dash',
                Rule::unique('origins', 'code')->ignore($origin?->id),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('origins', 'name')->ignore($origin?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['source_id'] = ($validated['source_id'] ?? null) ?: null;

        return $validated;
    }

    private function sourceOptions()
    {
        return Source::query()
            ->orderBy('name')
            ->get();
    }
}
