<?php

namespace App\Jobs;

use App\Http\Services\Meta\MetaAssetStatusSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMetaAssetStatusesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public string $queryType = 'scheduled',
        public string $assetType = MetaAssetStatusSyncService::ASSET_TYPE_ALL,
    ) {
        $this->onQueue('meta');
    }

    public function handle(MetaAssetStatusSyncService $service): void
    {
        $service->syncAll($this->queryType, $this->assetType);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncMetaAssetStatusesJob failed', [
            'query_type' => $this->queryType,
            'asset_type' => $this->assetType,
            'message' => $exception->getMessage(),
        ]);
    }
}
