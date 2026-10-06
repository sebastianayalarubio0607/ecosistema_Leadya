<?php

namespace App\Jobs;

use App\Http\Services\Integration\GohighlevelOpportunityPollingService;
use App\Models\GohighlevelOpportunitySyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncGohighlevelOpportunityStatesJob implements ShouldQueue
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

    public function handle(GohighlevelOpportunityPollingService $service): void
    {
        $run = GohighlevelOpportunitySyncRun::query()->findOrFail($this->syncRunId);
        if ($run->status === 'completed') {
            return;
        }

        $run->forceFill(['status' => 'running', 'started_at' => now(), 'error_message' => null])->save();
        $service->sync($run);
    }

    public function failed(Throwable $exception): void
    {
        GohighlevelOpportunitySyncRun::query()->whereKey($this->syncRunId)->update([
            'status' => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 60000),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
