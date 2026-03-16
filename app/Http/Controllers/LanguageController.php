<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LanguageController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->get('q');

        $languages = Language::query()
            ->when($q, fn ($query) => $query->where(function ($innerQuery) use ($q) {
                $innerQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('languages.index', compact('languages', 'q'));
    }

    public function create(): View
    {
        return view('languages.create', [
            'language' => new Language(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Language::create($this->validateRequest($request));

        return redirect()
            ->route('languages.index')
            ->with('success', 'Idioma creado correctamente.');
    }

    public function show(Language $language): View
    {
        return view('languages.show', compact('language'));
    }

    public function edit(Language $language): View
    {
        return view('languages.edit', compact('language'));
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $language->update($this->validateRequest($request, $language));

        return redirect()
            ->route('languages.index')
            ->with('success', 'Idioma actualizado correctamente.');
    }

    public function destroy(Language $language): RedirectResponse
    {
        $language->delete();

        return redirect()
            ->route('languages.index')
            ->with('success', 'Idioma eliminado.');
    }

    private function validateRequest(Request $request, ?Language $language = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                'alpha_dash',
                Rule::unique('languages', 'code')->ignore($language?->id),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('languages', 'name')->ignore($language?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
