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
use RuntimeException;

class MetaWhatsappSubscriptionCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [1800, 1800, 1800];
    private const CONNECTION = 'meta_whatsapp_subscriptions';
    private const QUEUE = 'meta-whatsapp-subscriptions';

    public ?int $metaAccessTokenId = null;
    public ?int $customerId = null;

    public function __construct(public int $metaWhatsappId, ?int $metaAccessTokenId = null, ?int $customerId = null)
    {
        $this->metaAccessTokenId = $metaAccessTokenId;
        $this->customerId = $customerId;
        $this->onConnection(self::CONNECTION)->onQueue(self::QUEUE);
    }

    public function handle(MetaWhatsappSubscriptionService $service): void
    {
        $whatsapp = MetaWhatsapp::find($this->metaWhatsappId);

        if (! $whatsapp) {
            throw new RuntimeException('No existe la cuenta WhatsApp local '.$this->metaWhatsappId.'.');
        }

        $service->inspectAndQueue($whatsapp, true, $this->metaAccessTokenId, $this->customerId);
    }

    public function failed(\Throwable $exception): void
    {
        $whatsapp = MetaWhatsapp::find($this->metaWhatsappId);

        MetaWhatsappSubscriptionFailedJob::create([
            'uuid' => $this->job?->uuid(),
            'connection' => self::CONNECTION,
            'queue' => self::QUEUE,
            'job_class' => self::class,
            'action' => 'check',
            'resource_id' => $this->metaWhatsappId,
            'resource_identifier' => $whatsapp?->waba_id,
            'payload' => [
                'meta_whatsapp_id' => $this->metaWhatsappId,
                'meta_access_token_id' => $this->metaAccessTokenId,
                'customer_id' => $this->customerId,
            ],
            'exception' => $exception->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
