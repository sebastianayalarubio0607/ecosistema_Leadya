<?php

namespace App\Jobs;

use App\Models\MetaAdAccountSubscriptionFailedJob;
use App\Models\MetaPageSubscriptionFailedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PruneMetaSubscriptionFailedJobs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        $threshold = now()->subMonthsNoOverflow(2);

        MetaAdAccountSubscriptionFailedJob::query()
            ->where('failed_at', '<', $threshold)
            ->delete();

        MetaPageSubscriptionFailedJob::query()
            ->where('failed_at', '<', $threshold)
            ->delete();
    }
}
