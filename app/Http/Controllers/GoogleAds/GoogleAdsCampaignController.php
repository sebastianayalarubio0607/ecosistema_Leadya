<?php

namespace App\Http\Controllers\GoogleAds;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GoogleAdsCampaign;
use Illuminate\Http\Request;

class GoogleAdsCampaignController extends Controller
{
    public function index(Request $request)
    {
        $items = GoogleAdsCampaign::query()
            ->with('customer')
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('report_date'), fn ($query) => $query->whereDate('report_date', $request->string('report_date')->toString()))
            ->when($request->filled('campaign_name'), fn ($query) => $query->where('campaign_name', 'like', '%'.$request->string('campaign_name')->toString().'%'))
            ->when($request->filled('campaign_status'), fn ($query) => $query->where('campaign_status', 'like', '%'.$request->string('campaign_status')->toString().'%'))
            ->orderByDesc('report_date')
            ->orderBy('campaign_name')
            ->paginate(15)
            ->withQueryString();

        return view('google_ads.campaigns.index', [
            'items' => $items,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
