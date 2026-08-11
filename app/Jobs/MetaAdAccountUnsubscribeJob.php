<?php

namespace App\Jobs;

use App\Http\Services\Meta\Subscription\Account\MetaAccountSubscriptionService;
use App\Models\MetaAdAccount;
use App\Models\MetaAdAccountSubscriptionFailedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MetaAdAccountUnsubscribeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [36, 36, 36];
    private const CONNECTION = 'meta_ad_account_subscriptions';
    private const QUEUE = 'meta-ad-account-subscriptions';

    public function __construct(
        public ?int $metaAdAccountId,
        public string $metaAccountId,
    ) {
        $this->onConnection(self::CONNECTION)->onQueue(self::QUEUE);
    }

    public function handle(MetaAccountSubscriptionService $service): void
    {
        $account = $this->metaAdAccountId ? MetaAdAccount::find($this->metaAdAccountId) : null;
        $service->unsubscribeByMetaAccountId($this->metaAccountId, $account);
    }

    public function failed(\Throwable $exception): void
    {
        MetaAdAccountSubscriptionFailedJob::create([
            'uuid' => $this->job?->uuid(),
            'connection' => self::CONNECTION,
            'queue' => self::QUEUE,
            'job_class' => self::class,
            'action' => 'unsubscribe',
            'resource_id' => $this->metaAdAccountId,
            'resource_identifier' => $this->metaAccountId,
            'payload' => [
                'meta_ad_account_id' => $this->metaAdAccountId,
                'meta_account_id' => $this->metaAccountId,
            ],
            'exception' => $exception->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
