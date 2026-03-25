<?php

namespace App\Jobs;

use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Models\MetaAccessToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMetaPagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public ?int $metaAccessTokenId = null,
    ) {
        $this->onQueue('meta');
    }

    public function handle(MetaLeadAdsSyncService $service): void
    {
        $token = $this->metaAccessTokenId ? MetaAccessToken::find($this->metaAccessTokenId) : null;
        $service->syncPages($token);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncMetaPagesJob failed', [
            'meta_access_token_id' => $this->metaAccessTokenId,
            'message' => $exception->getMessage(),
        ]);
    }
}
