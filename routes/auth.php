<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CrmState\CrmStateWebController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardGerencialLeadsController;
use App\Http\Controllers\Funnel\FunnelWebController;
use App\Http\Controllers\GeneralLeadsDashboardController;
use App\Http\Controllers\Integration\IntegrationWebController;
use App\Http\Controllers\Integration\MondayBoardController;
use App\Http\Controllers\IntegrationtypeWebController;
use App\Http\Controllers\LeadManagementController;
use App\Http\Controllers\GoogleAds\GoogleAdsAdController;
use App\Http\Controllers\GoogleAds\GoogleAdsAdGroupController;
use App\Http\Controllers\GoogleAds\GoogleAdsCampaignController as GoogleAdsMetricsCampaignController;
use App\Http\Controllers\GoogleAds\GoogleAdsConversionController;
use App\Http\Controllers\GoogleAds\GoogleAdsCredentialController;
use App\Http\Controllers\GoogleAds\GoogleAdsSyncController;
use App\Http\Controllers\Meta\MetaAccessTokenController;
use App\Http\Controllers\Meta\MetaAdAccountSubscriptionJobController;
use App\Http\Controllers\Meta\MetaAdAccountController;
use App\Http\Controllers\Meta\MetaAdController;
use App\Http\Controllers\Meta\MetaAdInsightController;
use App\Http\Controllers\Meta\MetaAdSetController;
use App\Http\Controllers\Meta\MetaCampaignController;
use App\Http\Controllers\Meta\MetaEventController;
use App\Http\Controllers\Meta\MetaFormController;
use App\Http\Controllers\Meta\MetaFormFieldMappingController;
use App\Http\Controllers\Meta\MetaPageController;
use App\Http\Controllers\Meta\MetaPageSubscriptionJobController;
use App\Http\Controllers\Meta\MetaSyncController;
use App\Http\Controllers\Meta\MetaWhatsappController;
use App\Http\Controllers\Meta\MetaWhatsappSubscriptionJobController;
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
 * Dashboard Gerencial de Leads routes for managing leads. These routes are defined for viewing the gerencial leads dashboard, listing leads, and exporting the leads list. The routes are grouped under the 'dashboard' prefix and use the DashboardGerencialLeadsController for handling the requests. Each route is named for easy reference in the application.
 */
    Route::get('/gestion-leads', [LeadManagementController::class, 'index'])->name('lead-management.index');
    Route::patch('/gestion-leads/{lead}/crm-state', [LeadManagementController::class, 'updateCrmState'])->name('lead-management.crm-state');
    Route::patch('/gestion-leads/{lead}/value', [LeadManagementController::class, 'updateValue'])->name('lead-management.value');
    Route::get('/dashboard/gerencial-leads', [DashboardGerencialLeadsController::class, 'dashboardGerencialLeads'])->name('dashboard.gerencial-leads');
    Route::get('/dashboard/gerencial-leads/list', [DashboardGerencialLeadsController::class, 'dashboardGerencialLeadsList'])->name('dashboard.gerencial-leads.list');
    Route::get('/dashboard/gerencial-leads/list/export', [DashboardGerencialLeadsController::class, 'dashboardGerencialLeadsListExport'])->name('dashboard.gerencial-leads.list.export');
    Route::get('/dashboard/general-leads', GeneralLeadsDashboardController::class)->name('dashboard.general-leads');
    Route::get('/dashboard/general-leads/list', [GeneralLeadsDashboardController::class, 'list'])->name('dashboard.general-leads.list');
    Route::get('/dashboard/general-leads/list/export', [GeneralLeadsDashboardController::class, 'exportList'])->name('dashboard.general-leads.list.export');


    Volt::route('verify-email', 'pages.auth.verify-email')->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')->name('password.confirm');
    Volt::route('register', 'pages.auth.register')->name('register');

    Route::resource('customers', CustomerController::class);
    Route::resource('currencies', CurrencyController::class);
    Route::resource('integrationtypes', IntegrationtypeWebController::class);
    Route::get('integrations/kommo-pipeline/pipelines/{integration}', [IntegrationWebController::class, 'kommoPipelinePipelines'])
        ->name('integrations.kommo-pipeline.pipelines');
    Route::get('integrations/kommo-pipeline/statuses/{integration}/{pipelineId}', [IntegrationWebController::class, 'kommoPipelineStatuses'])
        ->name('integrations.kommo-pipeline.statuses');
    Route::post('integrations/{integration}/kommo/sync-boards', [IntegrationWebController::class, 'syncKommoBoards'])
        ->name('integrations.kommo.sync-boards');
    Route::resource('integrations', IntegrationWebController::class);

    
    Route::prefix('google-ads')->name('google-ads.')->group(function () {
        Route::view('/', 'google_ads.index')->name('index');

        Route::resource('credentials', GoogleAdsCredentialController::class)
            ->parameters(['credentials' => 'credential']);

        Route::post('credentials/{credential}/reveal-secret', [GoogleAdsCredentialController::class, 'revealSecret'])
            ->name('credentials.reveal-secret')
            ->middleware('throttle:20,1');

        Route::post('credentials/{credential}/refresh-token', [GoogleAdsCredentialController::class, 'refreshToken'])
            ->name('credentials.refresh-token')
            ->middleware('throttle:10,1');

        Route::get('campaigns', [GoogleAdsMetricsCampaignController::class, 'index'])->name('campaigns.index');
        Route::get('ad-groups', [GoogleAdsAdGroupController::class, 'index'])->name('ad-groups.index');
        Route::get('ads', [GoogleAdsAdController::class, 'index'])->name('ads.index');
        Route::get('conversion-actions', [GoogleAdsConversionController::class, 'conversionActions'])->name('conversion-actions.index');
        Route::get('conversion-jobs', [GoogleAdsConversionController::class, 'index'])->name('conversion-jobs.index');
        Route::post('failed-jobs/{failedJob}/retry', [GoogleAdsConversionController::class, 'retry'])->name('failed-jobs.retry');
        Route::post('sync/manual', [GoogleAdsSyncController::class, 'sync'])->name('sync.manual');
    });

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
        Route::view('/', 'meta.index')->name('index');

        Route::resource('ad-accounts', MetaAdAccountController::class)
            ->parameters(['ad-accounts' => 'ad_account']);

        Route::get('ad-accounts-subscriptions/jobs', [MetaAdAccountSubscriptionJobController::class, 'index'])
            ->name('ad-accounts.subscription-jobs.index');
        Route::post('ad-accounts-subscriptions/scan', [MetaAdAccountSubscriptionJobController::class, 'scan'])
            ->name('ad-accounts.subscription-jobs.scan');
        Route::post('ad-accounts-subscriptions/queued/{jobId}/release', [MetaAdAccountSubscriptionJobController::class, 'releaseQueued'])
            ->name('ad-accounts.subscription-jobs.queued.release');
        Route::post('ad-accounts-subscriptions/queued/release-all', [MetaAdAccountSubscriptionJobController::class, 'releaseAllQueued'])
            ->name('ad-accounts.subscription-jobs.queued.release-all');
        Route::post('ad-accounts-subscriptions/failed/{failedJob}/retry', [MetaAdAccountSubscriptionJobController::class, 'retry'])
            ->name('ad-accounts.subscription-jobs.failed.retry');
        Route::post('ad-accounts-subscriptions/failed/retry-all', [MetaAdAccountSubscriptionJobController::class, 'retryAll'])
            ->name('ad-accounts.subscription-jobs.failed.retry-all');

        Route::resource('whatsapps', MetaWhatsappController::class)
            ->parameters(['whatsapps' => 'whatsapp']);

        Route::get('whatsapps-subscriptions/jobs', [MetaWhatsappSubscriptionJobController::class, 'index'])
            ->name('whatsapps.subscription-jobs.index');
        Route::post('whatsapps-subscriptions/scan', [MetaWhatsappSubscriptionJobController::class, 'scan'])
            ->name('whatsapps.subscription-jobs.scan');
        Route::post('whatsapps-subscriptions/queued/{jobId}/release', [MetaWhatsappSubscriptionJobController::class, 'releaseQueued'])
            ->name('whatsapps.subscription-jobs.queued.release');
        Route::post('whatsapps-subscriptions/queued/release-all', [MetaWhatsappSubscriptionJobController::class, 'releaseAllQueued'])
            ->name('whatsapps.subscription-jobs.queued.release-all');
        Route::post('whatsapps-subscriptions/failed/{failedJob}/retry', [MetaWhatsappSubscriptionJobController::class, 'retry'])
            ->name('whatsapps.subscription-jobs.failed.retry');
        Route::post('whatsapps-subscriptions/failed/retry-all', [MetaWhatsappSubscriptionJobController::class, 'retryAll'])
            ->name('whatsapps.subscription-jobs.failed.retry-all');

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

        Route::get('pages-subscriptions/jobs', [MetaPageSubscriptionJobController::class, 'index'])
            ->name('pages.subscription-jobs.index');
        Route::post('pages-subscriptions/scan', [MetaPageSubscriptionJobController::class, 'scan'])
            ->name('pages.subscription-jobs.scan');
        Route::post('pages-subscriptions/queued/{jobId}/release', [MetaPageSubscriptionJobController::class, 'releaseQueued'])
            ->name('pages.subscription-jobs.queued.release');
        Route::post('pages-subscriptions/queued/release-all', [MetaPageSubscriptionJobController::class, 'releaseAllQueued'])
            ->name('pages.subscription-jobs.queued.release-all');
        Route::post('pages-subscriptions/failed/{failedJob}/retry', [MetaPageSubscriptionJobController::class, 'retry'])
            ->name('pages.subscription-jobs.failed.retry');
        Route::post('pages-subscriptions/failed/retry-all', [MetaPageSubscriptionJobController::class, 'retryAll'])
            ->name('pages.subscription-jobs.failed.retry-all');

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
