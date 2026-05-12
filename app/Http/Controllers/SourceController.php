<?php

namespace App\Http\Controllers;

use App\Http\Services\SourceService;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SourceController extends Controller
{
    public function __construct(private readonly SourceService $sourceService)
    {
    }

    public function index(Request $request): View
    {
        $q = $request->get('q');
        $sources = $this->sourceService->list($q);

        return view('sources.index', compact('sources', 'q'));
    }

    public function create(): View
    {
        return view('sources.create', [
            'source' => new Source(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->sourceService->store($this->validateRequest($request));

        return redirect()
            ->route('sources.index')
            ->with('success', 'Fuente creada correctamente.');
    }

    public function show(Source $source): View
    {
        return view('sources.show', compact('source'));
    }

    public function edit(Source $source): View
    {
        return view('sources.edit', compact('source'));
    }

    public function update(Request $request, Source $source): RedirectResponse
    {
        $this->sourceService->update($source, $this->validateRequest($request, $source));

        return redirect()
            ->route('sources.index')
            ->with('success', 'Fuente actualizada correctamente.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        $this->sourceService->destroy($source);

        return redirect()
            ->route('sources.index')
            ->with('success', 'Fuente eliminada.');
    }

    private function validateRequest(Request $request, ?Source $source = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sources', 'name')->ignore($source?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
