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
use RuntimeException;

class MetaAdAccountSubscriptionCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [36, 36, 36];
    private const CONNECTION = 'meta_ad_account_subscriptions';
    private const QUEUE = 'meta-ad-account-subscriptions';

    public function __construct(public int $metaAdAccountId)
    {
        $this->onConnection(self::CONNECTION)->onQueue(self::QUEUE);
    }

    public function handle(MetaAccountSubscriptionService $service): void
    {
        $account = MetaAdAccount::find($this->metaAdAccountId);

        if (! $account) {
            throw new RuntimeException('No existe la cuenta publicitaria local '.$this->metaAdAccountId.'.');
        }

        $service->inspectAndQueue($account);
    }

    public function failed(\Throwable $exception): void
    {
        $account = MetaAdAccount::find($this->metaAdAccountId);

        MetaAdAccountSubscriptionFailedJob::create([
            'uuid' => $this->job?->uuid(),
            'connection' => self::CONNECTION,
            'queue' => self::QUEUE,
            'job_class' => self::class,
            'action' => 'check',
            'resource_id' => $this->metaAdAccountId,
            'resource_identifier' => $account?->meta_account_id,
            'payload' => ['meta_ad_account_id' => $this->metaAdAccountId],
            'exception' => $exception->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
