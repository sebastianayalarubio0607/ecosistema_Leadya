<?php

namespace App\Jobs;

use App\Http\Services\Meta\MetaAssetStatusSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMetaAdAccountStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $metaAdAccountId,
        public string $queryType = 'manual',
    ) {
        $this->onQueue('meta');
    }

    public function handle(MetaAssetStatusSyncService $service): void
    {
        $service->syncAdAccountId($this->metaAdAccountId, $this->queryType);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncMetaAdAccountStatusJob failed', [
            'meta_ad_account_id' => $this->metaAdAccountId,
            'query_type' => $this->queryType,
            'message' => $exception->getMessage(),
        ]);
    }
}
