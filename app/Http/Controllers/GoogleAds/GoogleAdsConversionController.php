<?php

namespace App\Http\Controllers\GoogleAds;

use App\Http\Controllers\Controller;
use App\Http\Services\Convention\GoogleAdsConversionsService;
use App\Jobs\SendLeadToGoogleAds;
use App\Models\Customer;
use App\Models\GoogleAdsConversionJob;
use App\Models\GoogleAdsFailedJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleAdsConversionController extends Controller
{
    public function index(Request $request)
    {
        return view('google_ads.conversions.index', [
            'jobs' => GoogleAdsConversionJob::query()
                ->with(['lead:id,name,email,phone', 'customer:id,name', 'crmState:id,name'])
                ->when($request->filled('lead_id'), fn ($query) => $query->where('lead_id', $request->integer('lead_id')))
                ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'jobs_page')
                ->withQueryString(),
            'failedJobs' => GoogleAdsFailedJob::query()
                ->with(['lead:id,name,email,phone', 'customer:id,name', 'crmState:id,name'])
                ->when($request->filled('lead_id'), fn ($query) => $query->where('lead_id', $request->integer('lead_id')))
                ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'failed_page')
                ->withQueryString(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function conversionActions(Request $request, GoogleAdsConversionsService $service): JsonResponse
    {
        $customer = Customer::query()
            ->whereKey($request->integer('customer_id'))
            ->first();

        if (! $customer) {
            return response()->json([
                'success' => false,
                'actions' => [],
                'error_message' => 'Customer no encontrado.',
            ], 404);
        }

        $result = $service->listConversionActions($customer);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function retry(GoogleAdsFailedJob $failedJob): RedirectResponse
    {
        if (! $failedJob->lead_id) {
            return back()->withErrors([
                'google_ads_retry' => 'No se puede reprocesar porque el registro no tiene lead_id.',
            ]);
        }

        SendLeadToGoogleAds::dispatch((int) $failedJob->lead_id, $failedJob->crm_state_id);

        $failedJob->forceFill([
            'retried_at' => now(),
        ])->save();

        return back()->with('success', 'Job de Google Ads reenviado a la cola.');
    }
}
