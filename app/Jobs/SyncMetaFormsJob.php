<?php

namespace App\Jobs;

use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Models\MetaPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMetaFormsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public ?int $metaPageId = null,
    ) {
        $this->onQueue('meta');
    }

    public function handle(MetaLeadAdsSyncService $service): void
    {
        $page = $this->metaPageId ? MetaPage::find($this->metaPageId) : null;
        $service->syncForms($page);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncMetaFormsJob failed', [
            'meta_page_id' => $this->metaPageId,
            'message' => $exception->getMessage(),
        ]);
    }
}
