<?php

namespace App\Jobs;

use App\Models\HubspotOpportunitySyncRun;
use App\Models\Integration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QueueHubspotOpportunitySyncRunsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $triggerSource = 'scheduled')
    {
        $this->onConnection('gohighlevel_sync')->onQueue('gohighlevel-sync');
    }

    public function handle(): void
    {
        Integration::query()
            ->with('integrationtype:id,name')
            ->where('status', true)
            ->where('hubspot_return_mode', 'api')
            ->whereNotNull('hubspot_pipeline_id')
            ->whereHas('integrationtype', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['hubspot']))
            ->orderBy('id')
            ->chunkById(50, function ($integrations): void {
                foreach ($integrations as $integration) {
                    self::queueIntegration($integration, $this->triggerSource);
                }
            });
    }

    public static function queueIntegration(Integration $integration, string $triggerSource): HubspotOpportunitySyncRun
    {
        $run = HubspotOpportunitySyncRun::query()->create([
            'integration_id' => $integration->id,
            'trigger_source' => $triggerSource,
            'status' => 'pending',
        ]);

        SyncHubspotOpportunityStatesJob::dispatch((int) $run->id);

        return $run;
    }
}
