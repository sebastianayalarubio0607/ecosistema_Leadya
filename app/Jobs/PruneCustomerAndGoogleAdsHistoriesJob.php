<?php

namespace App\Jobs;

use App\Models\CustomerActionHistory;
use App\Models\GoogleAdsConversionTemplateHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PruneCustomerAndGoogleAdsHistoriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public array $backoff = [300, 900];

    public function __construct()
    {
        $this->onQueue('tracking');
    }

    public function handle(): void
    {
        $customerDeleted = CustomerActionHistory::query()
            ->where('created_at', '<', now()->subMonthsNoOverflow(2))
            ->delete();

        $googleAdsDeleted = GoogleAdsConversionTemplateHistory::query()
            ->where('created_at', '<', now()->subMonthNoOverflow())
            ->delete();

        Log::info('Customer and Google Ads histories pruned.', [
            'customer_action_histories_deleted' => $customerDeleted,
            'google_ads_conversion_template_histories_deleted' => $googleAdsDeleted,
        ]);
    }
}
