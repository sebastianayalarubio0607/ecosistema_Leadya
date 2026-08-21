<?php

namespace App\Jobs;

use App\Http\Services\Meta\MetaAssetStatusSyncService;
use App\Models\MetaWebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMetaAssetStatusesForCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $customerId,
        public ?int $metaWebhookEventId = null,
        public string $queryType = 'webhook',
        public string $assetType = MetaAssetStatusSyncService::ASSET_TYPE_ALL,
    ) {
        $this->onQueue('meta');
    }

    public function handle(MetaAssetStatusSyncService $service): void
    {
        $event = $this->metaWebhookEventId ? MetaWebhookEvent::find($this->metaWebhookEventId) : null;

        $service->syncCustomer($this->customerId, $this->queryType, $event, $this->assetType);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncMetaAssetStatusesForCustomerJob failed', [
            'customer_id' => $this->customerId,
            'meta_webhook_event_id' => $this->metaWebhookEventId,
            'query_type' => $this->queryType,
            'asset_type' => $this->assetType,
            'message' => $exception->getMessage(),
        ]);
    }
}
