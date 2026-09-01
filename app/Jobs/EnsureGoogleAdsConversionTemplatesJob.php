<?php

namespace App\Jobs;

use App\Http\Services\GoogleAds\GoogleAdsConversionTemplateService;
use App\Models\Customer;
use App\Models\GoogleAdsConversionTemplateHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnsureGoogleAdsConversionTemplatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [300, 900, 1800];

    public function __construct(
        public int $customerId,
        public string $actorType = 'job',
        public ?int $actorId = null,
        public ?string $actorName = null,
    ) {
        $this->onQueue('tracking');
    }

    public function handle(GoogleAdsConversionTemplateService $service): void
    {
        $customer = Customer::query()->find($this->customerId);

        if (! $customer) {
            GoogleAdsConversionTemplateHistory::record([
                'action' => 'template_sync_skipped',
                'actor' => $this->actor(),
                'success' => false,
                'error_message' => 'Customer no encontrado.',
            ]);

            return;
        }

        $result = $service->ensureTemplatesForCustomer(
            $customer,
            $this->actorType,
            $this->actorId,
            $this->actorName
        );

        Log::info('EnsureGoogleAdsConversionTemplatesJob finished.', [
            'customer_id' => $this->customerId,
            'result' => $result,
        ]);

        if (! ($result['success'] ?? false) && ! ($result['skipped'] ?? false)) {
            throw new \RuntimeException($result['error_message'] ?? 'No fue posible asegurar plantillas de conversion de Google Ads.');
        }
    }

    public function failed(Throwable $exception): void
    {
        $customer = Customer::query()->find($this->customerId);

        GoogleAdsConversionTemplateHistory::record([
            'customer_id' => $customer?->id ?? $this->customerId,
            'google_ads_customer_id' => $customer?->id_Gads,
            'action' => 'template_sync_failed',
            'actor' => $this->actor(),
            'success' => false,
            'error_message' => $exception->getMessage(),
        ]);

        Log::error('EnsureGoogleAdsConversionTemplatesJob failed permanently.', [
            'customer_id' => $this->customerId,
            'message' => $exception->getMessage(),
        ]);
    }

    private function actor(): array
    {
        return [
            'type' => $this->actorType,
            'id' => $this->actorId,
            'name' => $this->actorName,
        ];
    }
}
