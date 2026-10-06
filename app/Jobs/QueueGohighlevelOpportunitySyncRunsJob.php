<?php

namespace App\Jobs;

use App\Http\Services\Integration\GohighlevelService;
use App\Models\GohighlevelOpportunitySyncRun;
use App\Models\Integration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QueueGohighlevelOpportunitySyncRunsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $triggerSource = 'scheduled')
    {
        $this->onConnection('gohighlevel_sync')->onQueue('gohighlevel-sync');
    }

    public function handle(GohighlevelService $service): void
    {
        $integrations = Integration::query()
            ->with('integrationtype:id,name')
            ->where('status', true)
            ->where('gohighlevel_return_mode', 'api')
            ->get();

        foreach ($integrations as $integration) {
            if (! $service->supportsOpportunity($integration)) {
                continue;
            }

            $this->queueIntegration($integration, $this->triggerSource);
        }
    }

    public static function queueIntegration(Integration $integration, string $triggerSource): GohighlevelOpportunitySyncRun
    {
        $run = GohighlevelOpportunitySyncRun::query()->create([
            'integration_id' => $integration->id,
            'trigger_source' => $triggerSource,
            'status' => 'pending',
        ]);

        SyncGohighlevelOpportunityStatesJob::dispatch((int) $run->id);

        return $run;
    }
}
