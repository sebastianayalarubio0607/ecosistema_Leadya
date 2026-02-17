<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Models\MetaAdSet;
use App\Models\MetaCampaign;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetaAdSetController extends Controller
{
    public function index(Request $request)
    {
        $q = MetaAdSet::with('campaign.account.customer');

        if ($request->filled('search')) {
            $s = $request->string('search')->toString();
            $q->where(function ($qq) use ($s) {
                $qq->where('meta_ad_set_id', 'like', "%{$s}%")
                   ->orWhere('name', 'like', "%{$s}%");
            });
        }

        return view('meta.ad_sets.index', [
            'items' => $q->orderByDesc('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('meta.ad_sets.create', [
            'campaigns' => MetaCampaign::with('account.customer')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'meta_campaign_id'    => ['required', 'exists:meta_campaigns,id'],
            'meta_ad_set_id'      => ['required', 'string', 'max:64', 'unique:meta_ad_sets,meta_ad_set_id'],
            'name'                => ['nullable', 'string', 'max:255'],
            'optimization_goal'   => ['nullable', 'string', 'max:100'],
            'attribution_setting' => ['nullable', 'string', 'max:100'],
            'status'              => ['required', Rule::in(['active', 'inactive'])],
        ]);

        MetaAdSet::create($data);

        return redirect()->route('meta.ad-sets.index')->with('success', 'Grupo de anuncios creado.');
    }

    public function show(MetaAdSet $ad_set)
    {
        $ad_set->load('campaign.account.customer');

        return view('meta.ad_sets.show', compact('ad_set'));
    }

    public function edit(MetaAdSet $ad_set)
    {
        $ad_set->load('campaign.account.customer');

        return view('meta.ad_sets.edit', [
            'ad_set' => $ad_set,
            'campaigns' => MetaCampaign::with('account.customer')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, MetaAdSet $ad_set)
    {
        $data = $request->validate([
            'meta_campaign_id'    => ['required', 'exists:meta_campaigns,id'],
            'meta_ad_set_id'      => ['required', 'string', 'max:64', Rule::unique('meta_ad_sets', 'meta_ad_set_id')->ignore($ad_set->id)],
            'name'                => ['nullable', 'string', 'max:255'],
            'optimization_goal'   => ['nullable', 'string', 'max:100'],
            'attribution_setting' => ['nullable', 'string', 'max:100'],
            'status'              => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $ad_set->update($data);

        return redirect()->route('meta.ad-sets.index')->with('success', 'Grupo de anuncios actualizado.');
    }

    public function destroy(MetaAdSet $ad_set)
    {
        $ad_set->delete();

        return redirect()->route('meta.ad-sets.index')->with('success', 'Grupo de anuncios eliminado.');
    }
}
