<?php

namespace App\Http\Controllers;

use App\Models\SiteLink;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteLinkController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->get('q');

        $siteLinks = SiteLink::query()
            ->with('source')
            ->when($q, fn ($query) => $query->where(function ($innerQuery) use ($q) {
                $innerQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('site_links.index', compact('siteLinks', 'q'));
    }

    public function create(): View
    {
        return view('site_links.create', [
            'siteLink' => new SiteLink(),
            'sources' => $this->sourceOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SiteLink::create($this->validateRequest($request));

        return redirect()
            ->route('site-links.index')
            ->with('success', 'Site Link creado correctamente.');
    }

    public function show(SiteLink $siteLink): View
    {
        $siteLink->load('source');

        return view('site_links.show', compact('siteLink'));
    }

    public function edit(SiteLink $siteLink): View
    {
        return view('site_links.edit', [
            'siteLink' => $siteLink,
            'sources' => $this->sourceOptions(),
        ]);
    }

    public function update(Request $request, SiteLink $siteLink): RedirectResponse
    {
        $siteLink->update($this->validateRequest($request, $siteLink));

        return redirect()
            ->route('site-links.index')
            ->with('success', 'Site Link actualizado correctamente.');
    }

    public function destroy(SiteLink $siteLink): RedirectResponse
    {
        $siteLink->delete();

        return redirect()
            ->route('site-links.index')
            ->with('success', 'Site Link eliminado.');
    }

    private function validateRequest(Request $request, ?SiteLink $siteLink = null): array
    {
        return $request->validate([
            'source_id' => ['required', 'exists:sources,id'],
            'code' => [
                'required',
                'string',
                'max:20',
                'alpha_dash',
                Rule::unique('site_links', 'code')
                    ->where(fn ($query) => $query->where('source_id', $request->input('source_id')))
                    ->ignore($siteLink?->id),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('site_links', 'name')
                    ->where(fn ($query) => $query->where('source_id', $request->input('source_id')))
                    ->ignore($siteLink?->id),
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
