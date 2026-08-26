<?php

use App\Http\Controllers\CampaignObjectiveController;
use App\Http\Controllers\AiConnectors\AiConnectorController;
use App\Http\Controllers\AiConnectors\AiConnectorOAuthAuthorizeController;
use App\Http\Controllers\AiConnectors\AiConnectorOAuthMetadataController;
use App\Http\Controllers\AiConnectors\AiConnectorOAuthTokenController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\OriginController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\SiteLinkController;
use App\Http\Controllers\SourceController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UrlGeneratorController;

Route::get('/generate-url', [UrlGeneratorController::class, 'showForm']);
Route::post('/generate-url', [UrlGeneratorController::class, 'generateUrl'])->name('generate-url');

Route::view('/', 'welcome');
 

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('/.well-known/oauth-protected-resource', [AiConnectorOAuthMetadataController::class, 'protectedResource'])
    ->name('ai-connectors.oauth.protected-resource');
Route::get('/.well-known/oauth-protected-resource/{path}', [AiConnectorOAuthMetadataController::class, 'protectedResource'])
    ->where('path', '.*')
    ->name('ai-connectors.oauth.protected-resource.path');
Route::get('/.well-known/oauth-authorization-server', [AiConnectorOAuthMetadataController::class, 'authorizationServer'])
    ->name('ai-connectors.oauth.authorization-server');
Route::post('/token', AiConnectorOAuthTokenController::class)
    ->middleware('throttle:30,1')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('ai-connectors.oauth.token');

Route::middleware(['auth'])->group(function () {
    Route::get('authorize', [AiConnectorOAuthAuthorizeController::class, 'show'])
        ->name('ai-connectors.oauth.authorize.public');
    Route::get('conectores-ia/oauth/authorize', [AiConnectorOAuthAuthorizeController::class, 'show'])
        ->name('ai-connectors.oauth.authorize');
    Route::post('conectores-ia/oauth/authorize/approve', [AiConnectorOAuthAuthorizeController::class, 'approve'])
        ->name('ai-connectors.oauth.authorize.approve');
    Route::post('conectores-ia/oauth/authorize/deny', [AiConnectorOAuthAuthorizeController::class, 'deny'])
        ->name('ai-connectors.oauth.authorize.deny');

    Route::prefix('conectores-ia')->name('ai-connectors.')->group(function () {
        Route::get('/', [AiConnectorController::class, 'index'])->name('index');
        Route::get('/create', [AiConnectorController::class, 'create'])->name('create');
        Route::post('/', [AiConnectorController::class, 'store'])->name('store');
        Route::get('/{aiConnector}', [AiConnectorController::class, 'show'])->name('show');
        Route::get('/{aiConnector}/edit', [AiConnectorController::class, 'edit'])->name('edit');
        Route::put('/{aiConnector}', [AiConnectorController::class, 'update'])->name('update');
        Route::post('/{aiConnector}/reveal-secret', [AiConnectorController::class, 'revealSecret'])->name('reveal-secret');
        Route::post('/{aiConnector}/rotate-secret', [AiConnectorController::class, 'rotateSecret'])->name('rotate-secret');
    });

    Route::resource('sources', SourceController::class);
    Route::resource('origins', OriginController::class);
    Route::resource('platforms', PlatformController::class);
    Route::resource('geos', GeoController::class);
    Route::resource('languages', LanguageController::class);
    Route::resource('campaign_objectives', CampaignObjectiveController::class);
    Route::resource('site-links', SiteLinkController::class);
});

require __DIR__.'/auth.php';
