<?php

namespace App\Http\Controllers;

use App\Models\Platform;
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Platform::create($this->validateRequest($request));

        return redirect()
            ->route('platforms.index')
            ->with('success', 'Plataforma creada correctamente.');
    }

    public function show(Platform $platform): View
    {
        return view('platforms.show', compact('platform'));
    }

    public function edit(Platform $platform): View
    {
        return view('platforms.edit', compact('platform'));
    }

    public function update(Request $request, Platform $platform): RedirectResponse
    {
        $platform->update($this->validateRequest($request, $platform));

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
            'code' => [
                'required',
                'string',
                'max:20',
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
}
