<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Jobs\MetaWhatsappSubscribeJob;
use App\Jobs\MetaWhatsappSubscriptionCheckJob;
use App\Jobs\MetaWhatsappSubscriptionScanJob;
use App\Jobs\MetaWhatsappUnsubscribeJob;
use App\Models\MetaWhatsappSubscriptionFailedJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MetaWhatsappSubscriptionJobController extends Controller
{
    private const QUEUE_TABLE = 'meta_whatsapp_subscription_jobs';

    public function index(Request $request): View
    {
        return view('meta.whatsapps.subscription_jobs', [
            'queuedJobs' => DB::table(self::QUEUE_TABLE)
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'queued_page')
                ->withQueryString()
                ->through(fn ($job) => $this->presentQueuedJob($job)),
            'failedJobs' => MetaWhatsappSubscriptionFailedJob::query()
                ->orderByDesc('failed_at')
                ->paginate(15, ['*'], 'failed_page')
                ->withQueryString(),
        ]);
    }

    public function scan(): RedirectResponse
    {
        MetaWhatsappSubscriptionScanJob::dispatch();

        return back()->with('success', 'Revision de suscripciones WhatsApp enviada a la cola.');
    }

    public function releaseQueued(int $jobId): RedirectResponse
    {
        DB::table(self::QUEUE_TABLE)
            ->where('id', $jobId)
            ->update([
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'attempts' => 0,
            ]);

        return back()->with('success', 'Job WhatsApp liberado para procesarse ahora.');
    }

    public function releaseAllQueued(): RedirectResponse
    {
        DB::table(self::QUEUE_TABLE)->update([
            'reserved_at' => null,
            'available_at' => now()->timestamp,
        ]);

        return back()->with('success', 'Todos los jobs WhatsApp quedaron disponibles.');
    }

    public function retry(MetaWhatsappSubscriptionFailedJob $failedJob): RedirectResponse
    {
        $this->dispatchFailedJob($failedJob);
        $failedJob->forceFill(['retried_at' => now()])->save();

        return back()->with('success', 'Job fallido WhatsApp reenviado a la cola.');
    }

    public function retryAll(): RedirectResponse
    {
        $failedJobs = MetaWhatsappSubscriptionFailedJob::query()->orderBy('id')->get();

        foreach ($failedJobs as $failedJob) {
            $this->dispatchFailedJob($failedJob);
            $failedJob->forceFill(['retried_at' => now()])->save();
        }

        return back()->with('success', 'Jobs fallidos WhatsApp reenviados a la cola.');
    }

    private function dispatchFailedJob(MetaWhatsappSubscriptionFailedJob $failedJob): void
    {
        $payload = $failedJob->payload ?? [];
        $metaAccessTokenId = isset($payload['meta_access_token_id']) ? (int) $payload['meta_access_token_id'] : null;
        $customerId = isset($payload['customer_id']) ? (int) $payload['customer_id'] : null;

        match ($failedJob->action) {
            'scan' => MetaWhatsappSubscriptionScanJob::dispatch(),
            'check' => $failedJob->resource_id ? MetaWhatsappSubscriptionCheckJob::dispatch((int) $failedJob->resource_id, $metaAccessTokenId, $customerId) : null,
            'subscribe' => $failedJob->resource_id ? MetaWhatsappSubscribeJob::dispatch((int) $failedJob->resource_id, $metaAccessTokenId, $customerId) : null,
            'unsubscribe' => filled($failedJob->resource_identifier)
                ? MetaWhatsappUnsubscribeJob::dispatch(
                    $failedJob->resource_id ? (int) $failedJob->resource_id : null,
                    (string) $failedJob->resource_identifier,
                    $metaAccessTokenId,
                    $customerId,
                )
                : null,
            default => null,
        };
    }

    private function presentQueuedJob(object $job): object
    {
        $payload = json_decode($job->payload, true) ?: [];
        $displayName = (string) ($payload['displayName'] ?? 'Job');

        $job->display_name = Str::afterLast($displayName, '\\');
        $job->available_at_label = now()->setTimestamp((int) $job->available_at)->format('Y-m-d H:i:s');
        $job->created_at_label = now()->setTimestamp((int) $job->created_at)->format('Y-m-d H:i:s');

        return $job;
    }
}
