<?php

namespace App\Jobs;

use App\Http\Services\Meta\Subscription\Whatsapp\MetaWhatsappSubscriptionService;
use App\Models\MetaWhatsapp;
use App\Models\MetaWhatsappSubscriptionFailedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MetaWhatsappUnsubscribeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [1800, 1800, 1800];
    private const CONNECTION = 'meta_whatsapp_subscriptions';
    private const QUEUE = 'meta-whatsapp-subscriptions';

    public function __construct(
        public ?int $metaWhatsappId,
        public string $wabaId,
        public ?int $metaAccessTokenId = null,
        public ?int $customerId = null,
    ) {
        $this->onConnection(self::CONNECTION)->onQueue(self::QUEUE);
    }

    public function handle(MetaWhatsappSubscriptionService $service): void
    {
        $whatsapp = $this->metaWhatsappId ? MetaWhatsapp::find($this->metaWhatsappId) : null;
        $service->unsubscribeByWabaId($this->wabaId, $whatsapp, $this->metaAccessTokenId, $this->customerId);
    }

    public function failed(\Throwable $exception): void
    {
        MetaWhatsappSubscriptionFailedJob::create([
            'uuid' => $this->job?->uuid(),
            'connection' => self::CONNECTION,
            'queue' => self::QUEUE,
            'job_class' => self::class,
            'action' => 'unsubscribe',
            'resource_id' => $this->metaWhatsappId,
            'resource_identifier' => $this->wabaId,
            'payload' => [
                'meta_whatsapp_id' => $this->metaWhatsappId,
                'waba_id' => $this->wabaId,
                'meta_access_token_id' => $this->metaAccessTokenId,
                'customer_id' => $this->customerId,
            ],
            'exception' => $exception->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
