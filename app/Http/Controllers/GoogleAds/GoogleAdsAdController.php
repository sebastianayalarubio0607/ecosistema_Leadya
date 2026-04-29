<?php

namespace App\Http\Controllers\GoogleAds;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GoogleAdsAd;
use Illuminate\Http\Request;

class GoogleAdsAdController extends Controller
{
    public function index(Request $request)
    {
        $items = GoogleAdsAd::query()
            ->with('customer')
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('report_date'), fn ($query) => $query->whereDate('report_date', $request->string('report_date')->toString()))
            ->when($request->filled('campaign_name'), fn ($query) => $query->where('campaign_name', 'like', '%'.$request->string('campaign_name')->toString().'%'))
            ->when($request->filled('ad_group_name'), fn ($query) => $query->where('ad_group_name', 'like', '%'.$request->string('ad_group_name')->toString().'%'))
            ->when($request->filled('google_ad_id'), fn ($query) => $query->where('google_ad_id', 'like', '%'.$request->string('google_ad_id')->toString().'%'))
            ->when($request->filled('ad_status'), fn ($query) => $query->where('ad_status', 'like', '%'.$request->string('ad_status')->toString().'%'))
            ->orderByDesc('report_date')
            ->orderBy('campaign_name')
            ->orderBy('ad_group_name')
            ->paginate(15)
            ->withQueryString();

        return view('google_ads.ads.index', [
            'items' => $items,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
