<?php

namespace App\Http\Controllers;

use App\Models\CampaignObjective;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampaignObjectiveController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->get('q');

        $campaignObjectives = CampaignObjective::query()
            ->when($q, fn ($query) => $query->where('nombre', 'like', "%{$q}%"))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('campaign_objectives.index', compact('campaignObjectives', 'q'));
    }

    public function create(): View
    {
        return view('campaign_objectives.create', [
            'campaignObjective' => new CampaignObjective(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CampaignObjective::create($this->validateRequest($request));

        return redirect()
            ->route('campaign_objectives.index')
            ->with('success', 'Objetivo de campaña creado correctamente.');
    }

    public function show(CampaignObjective $campaignObjective): View
    {
        return view('campaign_objectives.show', compact('campaignObjective'));
    }

    public function edit(CampaignObjective $campaignObjective): View
    {
        return view('campaign_objectives.edit', compact('campaignObjective'));
    }

    public function update(Request $request, CampaignObjective $campaignObjective): RedirectResponse
    {
        $campaignObjective->update($this->validateRequest($request, $campaignObjective));

        return redirect()
            ->route('campaign_objectives.index')
            ->with('success', 'Objetivo de campaña actualizado correctamente.');
    }

    public function destroy(CampaignObjective $campaignObjective): RedirectResponse
    {
        $campaignObjective->delete();

        return redirect()
            ->route('campaign_objectives.index')
            ->with('success', 'Objetivo de campaña eliminado.');
    }

    private function validateRequest(Request $request, ?CampaignObjective $campaignObjective = null): array
    {
        return $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('campaign_objectives', 'nombre')->ignore($campaignObjective?->id),
            ],
            'estado' => ['required', 'boolean'],
        ]);
    }
}
