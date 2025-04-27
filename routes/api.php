<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\IntegrationTypeController;
use App\Http\Controllers\LeadIntegrationController;
use App\Http\Middleware\ApiAuthMiddleware;

/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
*/

// Rutas públicas
Route::apiResource('customers', CustomerController::class);
Route::post('/customers/{id}/regenerate-token', [CustomerController::class, 'regenerateToken']);
Route::apiResource('leads', LeadController::class);
// Rutas protegidas por el ApiAuthMiddleware
Route::middleware([ApiAuthMiddleware::class])->group(function () {
    
    Route::apiResource('integrations', IntegrationController::class);
    Route::apiResource('integration-types', IntegrationTypeController::class);
    Route::apiResource('lead-integrations', LeadIntegrationController::class);
});
