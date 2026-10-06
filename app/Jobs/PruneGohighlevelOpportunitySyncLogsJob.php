<?php

namespace App\Jobs;

use App\Models\GohighlevelOpportunitySyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PruneGohighlevelOpportunitySyncLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onConnection('gohighlevel_sync')->onQueue('gohighlevel-sync');
    }

    public function handle(): void
    {
        GohighlevelOpportunitySyncRun::query()
            ->where('created_at', '<', now()->subDays(7))
            ->delete();
    }
}
