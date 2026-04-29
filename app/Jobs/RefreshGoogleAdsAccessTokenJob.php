<?php

namespace App\Jobs;

use App\Http\Services\GoogleAds\GoogleAdsAuthService;
use App\Models\GoogleAdsCredential;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshGoogleAdsAccessTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public ?int $googleAdsCredentialId = null,
    ) {
        $this->onQueue('google-ads');
    }

    public function handle(GoogleAdsAuthService $authService): void
    {
        $credential = $this->googleAdsCredentialId
            ? GoogleAdsCredential::find($this->googleAdsCredentialId)
            : null;

        $authService->refreshDueCredentials($credential);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RefreshGoogleAdsAccessTokenJob failed.', [
            'google_ads_credential_id' => $this->googleAdsCredentialId,
            'message' => $exception->getMessage(),
        ]);
    }
}
