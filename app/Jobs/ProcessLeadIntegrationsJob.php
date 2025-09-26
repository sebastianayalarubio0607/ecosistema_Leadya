<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Http\Services\Integration\IntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessLeadIntegrationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $lead;
    private $integrations;

    /**
     * Create a new job instance.
     */
    public function __construct(Lead $lead, $integrations)
    {
        $this->lead = $lead;
        $this->integrations = $integrations;
    }

    /**
     * Execute the job.
     */
    public function handle(IntegrationService $integrationService)
    {
        $integrationService->processLeadIntegrations($this->lead, $this->integrations);
    }
}
