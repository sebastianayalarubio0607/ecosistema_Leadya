<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Models\MetaAd;
use App\Models\MetaAdSet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetaAdController extends Controller
{
    public function index(Request $request)
    {
        $q = MetaAd::with('adSet.campaign.account.customer');

        if ($request->filled('search')) {
            $s = $request->string('search')->toString();
            $q->where(function ($qq) use ($s) {
                $qq->where('meta_ad_id', 'like', "%{$s}%")
                   ->orWhere('name', 'like', "%{$s}%");
            });
        }

        return view('meta.ads.index', [
            'items' => $q->orderByDesc('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('meta.ads.create', [
            'adSets' => MetaAdSet::with('campaign.account.customer')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'meta_ad_set_id' => ['required', 'exists:meta_ad_sets,id'],
            'meta_ad_id'     => ['required', 'string', 'max:64', 'unique:meta_ads,meta_ad_id'],
            'name'           => ['nullable', 'string', 'max:255'],
            'status'         => ['required', Rule::in(['active', 'inactive'])],
        ]);

        MetaAd::create($data);

        return redirect()->route('meta.ads.index')->with('success', 'Anuncio creado.');
    }

    public function show(MetaAd $ad)
    {
        $ad->load('adSet.campaign.account.customer');

        return view('meta.ads.show', compact('ad'));
    }

    public function edit(MetaAd $ad)
    {
        $ad->load('adSet.campaign.account.customer');

        return view('meta.ads.edit', [
            'ad' => $ad,
            'adSets' => MetaAdSet::with('campaign.account.customer')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, MetaAd $ad)
    {
        $data = $request->validate([
            'meta_ad_set_id' => ['required', 'exists:meta_ad_sets,id'],
            'meta_ad_id'     => ['required', 'string', 'max:64', Rule::unique('meta_ads', 'meta_ad_id')->ignore($ad->id)],
            'name'           => ['nullable', 'string', 'max:255'],
            'status'         => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $ad->update($data);

        return redirect()->route('meta.ads.index')->with('success', 'Anuncio actualizado.');
    }

    public function destroy(MetaAd $ad)
    {
        $ad->delete();

        return redirect()->route('meta.ads.index')->with('success', 'Anuncio eliminado.');
    }
}
