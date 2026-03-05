<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CrmState\CrmStateWebController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardLeadsController;
use App\Http\Controllers\Funnel\FunnelWebController;
use App\Http\Controllers\Integration\IntegrationWebController;
use App\Http\Controllers\IntegrationtypeWebController;
use App\Http\Controllers\Meta\MetaEventController;
use App\Http\Controllers\Meta\MetaAdAccountController;
use App\Http\Controllers\Meta\MetaAdController;
use App\Http\Controllers\Meta\MetaAdInsightController;
use App\Http\Controllers\Meta\MetaAdSetController;
use App\Http\Controllers\Meta\MetaCampaignController;
use App\Http\Controllers\Meta\MetaSyncController;
use App\Http\Controllers\Qualification\QualificationWebController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;


Route::middleware('guest')->group(function () {

    Volt::route('login', 'pages.auth.login')
        ->name('login');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');
    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');

});

Route::middleware('auth')->group(function () {

    Volt::route('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');

    Volt::route('register', 'pages.auth.register')
        ->name('register');

    Route::resource('customers', CustomerController::class);

    Route::resource('integrationtypes', IntegrationtypeWebController::class);

    Route::resource('integrations', IntegrationWebController::class);

    Route::resource('qualifications', QualificationWebController::class);

    Route::resource('crmstates', CrmStateWebController::class);

    Route::resource('funnels', FunnelWebController::class);

    Route::get('/dashboard/leads', [DashboardLeadsController::class, 'leads'])->name('dashboard.leads');
    Route::get('/dashboard/leads/list', [DashboardLeadsController::class, 'leadsList'])->name('dashboard.leads.list');
    Route::get('/dashboard/leads/list/export', [DashboardLeadsController::class, 'leadsListExport'])->name('dashboard.leads.list.export');

    Route::prefix('meta')->name('meta.')->group(function () {
        Route::resource('ad-accounts', MetaAdAccountController::class)
            ->parameters(['ad-accounts' => 'ad_account']);

        Route::resource('campaigns', MetaCampaignController::class);

        Route::resource('ad-sets', MetaAdSetController::class)
            ->parameters(['ad-sets' => 'ad_set']);

        Route::resource('ads', MetaAdController::class);

        Route::resource('insights', MetaAdInsightController::class)
            ->parameters(['insights' => 'insight']);

        Route::post('sync/insights-yesterday', [MetaSyncController::class, 'syncInsightsYesterday'])
            ->name('sync.insights.yesterday')
            ->middleware('throttle:5,1');

        Route::post('/insights/consult', [MetaAdInsightController::class, 'consult'])
            ->name('insights.consult')
            ->middleware('throttle:3,1');

        Route::resource('meta-events', MetaEventController::class)
            ->parameters(['meta-events' => 'meta_event']); // opcional, pero claro para el binding

    });

});
