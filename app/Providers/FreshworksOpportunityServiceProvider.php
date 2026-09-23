<?php

namespace App\Providers;

use App\Http\Services\Integration\FreshworksOpportunityAwareIntegrationService;
use App\Http\Services\Integration\IntegrationService;
use Illuminate\Support\ServiceProvider;

class FreshworksOpportunityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ProcessLeadIntegrationsJob still type-hints IntegrationService. This binding adds
        // only the new type and delegates every existing type to the original service.
        $this->app->bind(IntegrationService::class, FreshworksOpportunityAwareIntegrationService::class);
    }
}
