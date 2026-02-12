<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\IntegrationTypeController;
use App\Http\Controllers\LeadIntegrationController;
use App\Http\Middleware\ApiAuthMiddleware;
use App\Http\Controllers\Api\LeadCrmStateController;



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