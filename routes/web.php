<?php

use App\Http\Controllers\CampaignObjectiveController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\OriginController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\SourceController;
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

Route::middleware(['auth'])->group(function () {
    Route::resource('sources', SourceController::class);
    Route::resource('origins', OriginController::class);
    Route::resource('platforms', PlatformController::class);
    Route::resource('geos', GeoController::class);
    Route::resource('languages', LanguageController::class);
    Route::resource('campaign_objectives', CampaignObjectiveController::class);
});

require __DIR__.'/auth.php';
