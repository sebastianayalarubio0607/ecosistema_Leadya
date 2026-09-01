<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->get('q');

        $platforms = Platform::query()
            ->with('sources')
            ->when($q, fn ($query) => $query->where(function ($innerQuery) use ($q) {
                $innerQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('platforms.index', compact('platforms', 'q'));
    }

    public function create(): View
    {
        return view('platforms.create', [
            'platform' => new Platform(),
            'sources' => $this->sourceOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);
        $sourceIds = $validated['source_ids'];
        unset($validated['source_ids']);

        $platform = Platform::create($validated);
        $platform->sources()->sync($sourceIds);

        return redirect()
            ->route('platforms.index')
            ->with('success', 'Plataforma creada correctamente.');
    }

    public function show(Platform $platform): View
    {
        $platform->load('sources');

        return view('platforms.show', compact('platform'));
    }

    public function edit(Platform $platform): View
    {
        $platform->load('sources');

        return view('platforms.edit', [
            'platform' => $platform,
            'sources' => $this->sourceOptions(),
        ]);
    }

    public function update(Request $request, Platform $platform): RedirectResponse
    {
        $validated = $this->validateRequest($request, $platform);
        $sourceIds = $validated['source_ids'];
        unset($validated['source_ids']);

        $platform->update($validated);
        $platform->sources()->sync($sourceIds);

        return redirect()
            ->route('platforms.index')
            ->with('success', 'Plataforma actualizada correctamente.');
    }

    public function destroy(Platform $platform): RedirectResponse
    {
        $platform->delete();

        return redirect()
            ->route('platforms.index')
            ->with('success', 'Plataforma eliminada.');
    }

    private function validateRequest(Request $request, ?Platform $platform = null): array
    {
        return $request->validate([
            'source_ids' => ['required', 'array', 'min:1'],
            'source_ids.*' => ['required', 'exists:sources,id'],
            'code' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('platforms', 'code')->ignore($platform?->id),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('platforms', 'name')->ignore($platform?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function sourceOptions()
    {
        return Source::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
