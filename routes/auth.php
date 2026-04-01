<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CrmState\CrmStateWebController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardLeadsController;
use App\Http\Controllers\Funnel\FunnelWebController;
use App\Http\Controllers\Integration\IntegrationWebController;
use App\Http\Controllers\Integration\MondayBoardController;
use App\Http\Controllers\IntegrationtypeWebController;
use App\Http\Controllers\Meta\MetaAccessTokenController;
use App\Http\Controllers\Meta\MetaAdAccountController;
use App\Http\Controllers\Meta\MetaAdController;
use App\Http\Controllers\Meta\MetaAdInsightController;
use App\Http\Controllers\Meta\MetaAdSetController;
use App\Http\Controllers\Meta\MetaCampaignController;
use App\Http\Controllers\Meta\MetaEventController;
use App\Http\Controllers\Meta\MetaFormController;
use App\Http\Controllers\Meta\MetaFormFieldMappingController;
use App\Http\Controllers\Meta\MetaPageController;
use App\Http\Controllers\Meta\MetaSyncController;
use App\Http\Controllers\Qualification\QualificationWebController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('login', 'pages.auth.login')->name('login');
    Volt::route('forgot-password', 'pages.auth.forgot-password')->name('password.request');
    Volt::route('reset-password/{token}', 'pages.auth.reset-password')->name('password.reset');
});

Route::middleware('auth')->group(function () {

        /**
         *  Resource routes for managing qualifications, CRM states, and funnels. These routes are defined using the Route::resource method, which automatically creates standard CRUD routes for each resource. The controllers specified for each resource will handle the corresponding requests for creating, reading, updating, and deleting records related to qualifications, CRM states, and funnels.
         */
    Route::resource('qualifications', QualificationWebController::class);
    Route::resource('crmstates', CrmStateWebController::class);
    Route::resource('funnels', FunnelWebController::class);
/**
 * Dashboard routes for managing leads. These routes are defined for viewing the leads dashboard, listing leads, and exporting the leads list. The routes are grouped under the 'dashboard' prefix and use the DashboardLeadsController for handling the requests. Each route is named for easy reference in the application.
 */
    Route::get('/dashboard/leads', [DashboardLeadsController::class, 'leads'])->name('dashboard.leads');
    Route::get('/dashboard/leads/list', [DashboardLeadsController::class, 'leadsList'])->name('dashboard.leads.list');
    Route::get('/dashboard/leads/list/export', [DashboardLeadsController::class, 'leadsListExport'])->name('dashboard.leads.list.export');


    Volt::route('verify-email', 'pages.auth.verify-email')->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')->name('password.confirm');
    Volt::route('register', 'pages.auth.register')->name('register');

    Route::resource('customers', CustomerController::class);
    Route::resource('integrationtypes', IntegrationtypeWebController::class);
    Route::resource('integrations', IntegrationWebController::class);

    /**
     * Routes for managing Monday.com board integrations. These routes allow users to synchronize boards, edit board details, and update board information. The routes are defined for specific actions related to Monday.com integrations and are named for easy reference in the application.
     */
    Route::post('integrations/{integration}/monday/sync-boards', [MondayBoardController::class, 'syncBoards'])
        ->name('integrations.monday.sync-boards');
    Route::get('integrations/{integration}/monday/boards/{board}/edit', [MondayBoardController::class, 'edit'])
        ->name('integrations.monday.boards.edit');
    Route::put('integrations/{integration}/monday/boards/{board}', [MondayBoardController::class, 'update'])
        ->name('integrations.monday.boards.update');
    Route::post('integrations/{integration}/monday/boards/{board}/sync-details', [MondayBoardController::class, 'syncDetails'])
        ->name('integrations.monday.boards.sync-details');


 /**
  * Meta routes for managing ad accounts, campaigns, ad sets, ads, access tokens, pages, forms, insights, and events. These routes are grouped under the 'meta' prefix and use resource controllers for standard CRUD operations. Additional routes are defined for specific actions like refreshing access tokens, syncing pages and forms, and consulting insights. Throttle middleware is applied to certain routes to limit the number of requests.
  */
    Route::prefix('meta')->name('meta.')->group(function () {
        Route::resource('ad-accounts', MetaAdAccountController::class)
            ->parameters(['ad-accounts' => 'ad_account']);

        Route::resource('campaigns', MetaCampaignController::class);

        Route::resource('ad-sets', MetaAdSetController::class)
            ->parameters(['ad-sets' => 'ad_set']);

        Route::resource('ads', MetaAdController::class);

        Route::resource('access-tokens', MetaAccessTokenController::class)
            ->parameters(['access-tokens' => 'access_token']);

        Route::post('access-tokens/{access_token}/refresh', [MetaAccessTokenController::class, 'refresh'])
            ->name('access-tokens.refresh');

        Route::post('access-tokens/{access_token}/sync-pages', [MetaAccessTokenController::class, 'syncPages'])
            ->name('access-tokens.sync-pages');

        Route::resource('pages', MetaPageController::class)
            ->parameters(['pages' => 'page']);

        Route::post('pages/sync', [MetaPageController::class, 'syncAll'])
            ->name('pages.sync-all');

        Route::post('pages/{page}/sync-forms', [MetaPageController::class, 'syncForms'])
            ->name('pages.sync-forms');

        Route::resource('forms', MetaFormController::class)
            ->parameters(['forms' => 'form']);

        Route::post('forms/sync', [MetaFormController::class, 'syncAll'])
            ->name('forms.sync-all');

        Route::post('forms/{form}/sync-leads', [MetaFormController::class, 'syncLeads'])
            ->name('forms.sync-leads');

        Route::resource('form-field-mappings', MetaFormFieldMappingController::class)
            ->parameters(['form-field-mappings' => 'mapping']);

        Route::resource('insights', MetaAdInsightController::class)
            ->parameters(['insights' => 'insight']);

        Route::post('sync/insights-yesterday', [MetaSyncController::class, 'syncInsightsYesterday'])
            ->name('sync.insights.yesterday')
            ->middleware('throttle:5,1');

        Route::post('/insights/consult', [MetaAdInsightController::class, 'consult'])
            ->name('insights.consult')
            ->middleware('throttle:3,1');

        Route::resource('meta-events', MetaEventController::class)
            ->parameters(['meta-events' => 'meta_event']);
    });
});
