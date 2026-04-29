<?php

namespace App\Jobs;

use App\Http\Services\GoogleAds\GoogleAdsSyncService;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGoogleAdsDailyMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public array $backoff = [120, 600];

    public function __construct(
        public ?string $reportDate = null,
        public ?int $customerId = null,
    ) {
        $this->onQueue('google-ads');
    }

    public function handle(GoogleAdsSyncService $syncService): void
    {
        $customer = $this->customerId ? Customer::find($this->customerId) : null;
        $date = $this->reportDate ? Carbon::parse($this->reportDate) : now()->subDay();

        Log::info('SyncGoogleAdsDailyMetricsJob started.', [
            'report_date' => $date->toDateString(),
            'customer_id' => $customer?->id,
            'customer_found' => (bool) $customer || $this->customerId === null,
        ]);

        $result = $syncService->syncForDate($date, $customer);

        Log::info('SyncGoogleAdsDailyMetricsJob finished.', $result);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncGoogleAdsDailyMetricsJob failed.', [
            'report_date' => $this->reportDate,
            'customer_id' => $this->customerId,
            'message' => $exception->getMessage(),
        ]);
    }
}
