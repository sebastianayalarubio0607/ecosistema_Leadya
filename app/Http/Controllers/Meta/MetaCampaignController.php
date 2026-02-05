<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Models\MetaAdAccount;
use App\Models\MetaCampaign;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetaCampaignController extends Controller
{
    public function index(Request $request)
    {
        $q = MetaCampaign::with('account.customer');

        if ($request->filled('search')) {
            $s = $request->string('search')->toString();
            $q->where(function ($qq) use ($s) {
                $qq->where('meta_campaign_id', 'like', "%{$s}%")
                   ->orWhere('name', 'like', "%{$s}%");
            });
        }

        return view('meta.campaigns.index', [
            'items' => $q->orderByDesc('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('meta.campaigns.create', [
            'accounts' => MetaAdAccount::with('customer')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'meta_ad_account_id' => ['required', 'exists:meta_ad_accounts,id'],
            'meta_campaign_id'   => ['required', 'string', 'max:64', 'unique:meta_campaigns,meta_campaign_id'],
            'name'               => ['nullable', 'string', 'max:255'],
            'objective'          => ['nullable', 'string', 'max:100'],
            'buying_type'        => ['nullable', 'string', 'max:50'],
            'status'             => ['required', Rule::in(['active', 'inactive'])],
        ]);

        MetaCampaign::create($data);

        return redirect()->route('meta.campaigns.index')->with('success', 'Campaña creada.');
    }

    public function show(MetaCampaign $campaign)
    {
        $campaign->load('account.customer');

        return view('meta.campaigns.show', compact('campaign'));
    }

    public function edit(MetaCampaign $campaign)
    {
        $campaign->load('account.customer');

        return view('meta.campaigns.edit', [
            'campaign' => $campaign,
            'accounts' => MetaAdAccount::with('customer')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, MetaCampaign $campaign)
    {
        $data = $request->validate([
            'meta_ad_account_id' => ['required', 'exists:meta_ad_accounts,id'],
            'meta_campaign_id'   => ['required', 'string', 'max:64', Rule::unique('meta_campaigns', 'meta_campaign_id')->ignore($campaign->id)],
            'name'               => ['nullable', 'string', 'max:255'],
            'objective'          => ['nullable', 'string', 'max:100'],
            'buying_type'        => ['nullable', 'string', 'max:50'],
            'status'             => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $campaign->update($data);

        return redirect()->route('meta.campaigns.index')->with('success', 'Campaña actualizada.');
    }

    public function destroy(MetaCampaign $campaign)
    {
        $campaign->delete();

        return redirect()->route('meta.campaigns.index')->with('success', 'Campaña eliminada.');
    }
}
