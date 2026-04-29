<?php

namespace App\Http\Controllers\GoogleAds;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleAds\GoogleAdsSyncRequest;
use App\Http\Services\GoogleAds\GoogleAdsSyncService;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class GoogleAdsSyncController extends Controller
{
    public function sync(GoogleAdsSyncRequest $request, GoogleAdsSyncService $syncService): RedirectResponse
    {
        $customer = $request->filled('customer_id')
            ? Customer::findOrFail($request->integer('customer_id'))
            : null;

        try {
            $result = $syncService->syncForDate(Carbon::parse($request->string('report_date')->toString()), $customer);

            $message = "Sincronización Google Ads completada para {$result['report_date']}. "
                ."Customers: {$result['customers_processed']}, "
                ."Campañas: {$result['campaigns_upserted']}, "
                ."Grupos: {$result['ad_groups_upserted']}, "
                ."Anuncios: {$result['ads_upserted']}.";

            if (! empty($result['errors'])) {
                $message .= ' Errores controlados: '.count($result['errors']).'.';
            }

            return back()->with('success', $message);
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'google_ads_sync' => 'No fue posible completar la sincronización manual: '.$exception->getMessage(),
            ]);
        }
    }
}
