<?php

use Illuminate\Support\Facades\Route;
use App\Jobs\SyncMetaLeadsJob;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\IntegrationTypeController;
use App\Http\Controllers\LeadIntegrationController;
use App\Http\Middleware\ApiAuthMiddleware;
use App\Http\Controllers\Api\LeadCrmStateController;
use App\Http\Controllers\Webhooks\MetaLeadAdsWebhookController;
use App\Models\Customer;
use Illuminate\Http\Request;



//Route::apiResource('customers', CustomerController::class);
Route::post('/customers/{id}/regenerate-token', [CustomerController::class, 'regenerateToken']);


//Route::apiResource('customers', CustomerController::class);
Route::post('leads-formualario', [LeadController::class, 'store']);

// Usamos el middleware por alias registrado en Kernel
Route::middleware([ApiAuthMiddleware::class])->group(function () {
    

    Route::apiResource('leads', LeadController::class);

});




Route::middleware([ApiAuthMiddleware::class])->group(function () {
    Route::get('/test-auth', function () {
        return response()->json([
            'message' => 'Autenticación exitosa',
            'cliente' => request()->get('authenticated_customer')
        ]);
    });
});

Route::get('/prueba-sin-auth', function () {
    return response()->json(['ok' => true]);
});



Route::post('/integrations/leads/crm-state/{public_key}', [LeadCrmStateController::class, 'update'])
    ->middleware(['throttle:460,1']); // 60 por minuto (ajusta)

Route::get('/webhooks/meta/lead-ads', [MetaLeadAdsWebhookController::class, 'verify']);
Route::post('/webhooks/meta/lead-ads', [MetaLeadAdsWebhookController::class, 'receive']);

Route::match(['get', 'post'], '/meta/sync-leads', function (Request $request) {
    $authValue = $request->bearerToken()
        ?: $request->header('X-FB-Pixel-ID')
        ?: $request->header('X-Auth-Token');

    if (blank($authValue)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: missing authentication value.',
        ], 401);
    }

    $authorized = Customer::query()
        ->whereNotNull('fb_pixel_id')
        ->where('fb_pixel_id', '!=', '')
        ->where('fb_pixel_id', $authValue)
        ->exists();

    if (! $authorized) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: invalid authentication value.',
        ], 401);
    }

    SyncMetaLeadsJob::dispatch();

    return response()->json([
        'success' => true,
        'message' => 'SyncMetaLeadsJob dispatched successfully.',
    ]);
})->middleware('throttle:5,1');
