<?php

namespace App\Jobs;

use App\Http\Services\Integration\HubspotOpportunityPollingService;
use App\Models\HubspotOpportunitySyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncHubspotOpportunityStatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;

    public function __construct(public int $syncRunId)
    {
        $this->onConnection('gohighlevel_sync')->onQueue('gohighlevel-sync');
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(HubspotOpportunityPollingService $service): void
    {
        $run = HubspotOpportunitySyncRun::query()->findOrFail($this->syncRunId);
        if ($run->status === 'completed') {
            return;
        }

        $service->sync($run);
    }

    public function failed(Throwable $exception): void
    {
        HubspotOpportunitySyncRun::query()->whereKey($this->syncRunId)->update([
            'status' => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 60000),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
