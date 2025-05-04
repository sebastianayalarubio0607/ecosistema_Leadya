<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\IntegrationTypeController;
use App\Http\Controllers\LeadIntegrationController;
use App\Http\Middleware\ApiAuthMiddleware;

//Route::apiResource('customers', CustomerController::class);
Route::post('/customers/{id}/regenerate-token', [CustomerController::class, 'regenerateToken']);

// Usamos el middleware por alias registrado en Kernel
Route::middleware([ApiAuthMiddleware::class])->group(function () {
    
    Route::apiResource('customers', CustomerController::class);
    
    Route::apiResource('integration-types', IntegrationTypeController::class);
    Route::apiResource('leads', LeadController::class);
    Route::apiResource('integrations', IntegrationController::class);
    Route::apiResource('lead-integrations', LeadIntegrationController::class);
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
