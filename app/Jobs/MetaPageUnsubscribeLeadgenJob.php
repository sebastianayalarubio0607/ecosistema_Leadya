<?php

namespace App\Jobs;

use App\Http\Services\Meta\Subscription\Pages\MetaPageSubscriptionLeadgenService;
use App\Models\MetaPage;
use App\Models\MetaPageSubscriptionFailedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MetaPageUnsubscribeLeadgenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [36, 36, 36];
    private const CONNECTION = 'meta_page_subscriptions';
    private const QUEUE = 'meta-page-subscriptions';

    public function __construct(
        public ?int $metaPageId,
        public string $metaPageExternalId,
    ) {
        $this->onConnection(self::CONNECTION)->onQueue(self::QUEUE);
    }

    public function handle(MetaPageSubscriptionLeadgenService $service): void
    {
        $page = $this->metaPageId ? MetaPage::find($this->metaPageId) : null;
        $service->unsubscribeByMetaPageId($this->metaPageExternalId, $page);
    }

    public function failed(\Throwable $exception): void
    {
        MetaPageSubscriptionFailedJob::create([
            'uuid' => $this->job?->uuid(),
            'connection' => self::CONNECTION,
            'queue' => self::QUEUE,
            'job_class' => self::class,
            'action' => 'unsubscribe',
            'resource_id' => $this->metaPageId,
            'resource_identifier' => $this->metaPageExternalId,
            'payload' => [
                'meta_page_model_id' => $this->metaPageId,
                'meta_page_id' => $this->metaPageExternalId,
            ],
            'exception' => $exception->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
