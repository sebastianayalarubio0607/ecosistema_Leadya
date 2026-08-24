<?php

namespace App\Jobs;

use App\Http\Services\Meta\MetaWhatsappReferralLeadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMetaWhatsappReferralLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public array $payload)
    {
        $this->onQueue('meta');
    }

    public function handle(MetaWhatsappReferralLeadService $service): void
    {
        $service->processPayload($this->payload);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessMetaWhatsappReferralLeadJob failed', [
            'message' => $exception->getMessage(),
            'object' => data_get($this->payload, 'object'),
        ]);
    }
}
