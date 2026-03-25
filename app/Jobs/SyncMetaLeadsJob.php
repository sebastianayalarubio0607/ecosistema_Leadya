<?php

namespace App\Jobs;

use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Models\MetaForm;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMetaLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public ?int $metaFormId = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {
        $this->onQueue('meta');
    }

    public function handle(MetaLeadAdsSyncService $service): void
    {
        $form = $this->metaFormId ? MetaForm::find($this->metaFormId) : null;
        $from = $this->from ? Carbon::parse($this->from) : null;
        $to = $this->to ? Carbon::parse($this->to) : null;

        $service->syncLeads($form, $from, $to);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncMetaLeadsJob failed', [
            'meta_form_id' => $this->metaFormId,
            'from' => $this->from,
            'to' => $this->to,
            'message' => $exception->getMessage(),
        ]);
    }
}
