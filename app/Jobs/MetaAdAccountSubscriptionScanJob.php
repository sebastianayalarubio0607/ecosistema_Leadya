<?php

namespace App\Jobs;

use App\Http\Services\Meta\Subscription\Account\MetaAccountSubscriptionService;
use App\Models\MetaAdAccountSubscriptionFailedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MetaAdAccountSubscriptionScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [36, 36, 36];
    private const CONNECTION = 'meta_ad_account_subscriptions';
    private const QUEUE = 'meta-ad-account-subscriptions';

    public function __construct()
    {
        $this->onConnection(self::CONNECTION)->onQueue(self::QUEUE);
    }

    public function handle(MetaAccountSubscriptionService $service): void
    {
        $service->syncAll();
    }

    public function failed(\Throwable $exception): void
    {
        MetaAdAccountSubscriptionFailedJob::create([
            'uuid' => $this->job?->uuid(),
            'connection' => self::CONNECTION,
            'queue' => self::QUEUE,
            'job_class' => self::class,
            'action' => 'scan',
            'payload' => [],
            'exception' => $exception->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
