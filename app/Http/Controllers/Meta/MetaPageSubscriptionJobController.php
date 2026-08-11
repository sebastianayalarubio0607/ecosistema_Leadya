<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Jobs\MetaPageSubscribeLeadgenJob;
use App\Jobs\MetaPageSubscriptionCheckJob;
use App\Jobs\MetaPageSubscriptionScanJob;
use App\Jobs\MetaPageUnsubscribeLeadgenJob;
use App\Models\MetaPageSubscriptionFailedJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MetaPageSubscriptionJobController extends Controller
{
    private const QUEUE_TABLE = 'meta_page_subscription_jobs';

    public function index(Request $request): View
    {
        return view('meta.pages.subscription_jobs', [
            'queuedJobs' => DB::table(self::QUEUE_TABLE)
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'queued_page')
                ->withQueryString()
                ->through(fn ($job) => $this->presentQueuedJob($job)),
            'failedJobs' => MetaPageSubscriptionFailedJob::query()
                ->orderByDesc('failed_at')
                ->paginate(15, ['*'], 'failed_page')
                ->withQueryString(),
        ]);
    }

    public function scan(): RedirectResponse
    {
        MetaPageSubscriptionScanJob::dispatch();

        return back()->with('success', 'Revision de suscripciones leadgen enviada a la cola.');
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

        return back()->with('success', 'Job de pagina Meta liberado para procesarse ahora.');
    }

    public function releaseAllQueued(): RedirectResponse
    {
        DB::table(self::QUEUE_TABLE)->update([
            'reserved_at' => null,
            'available_at' => now()->timestamp,
        ]);

        return back()->with('success', 'Todos los jobs de paginas Meta quedaron disponibles.');
    }

    public function retry(MetaPageSubscriptionFailedJob $failedJob): RedirectResponse
    {
        $this->dispatchFailedJob($failedJob);
        $failedJob->forceFill(['retried_at' => now()])->save();

        return back()->with('success', 'Job fallido de pagina Meta reenviado a la cola.');
    }

    public function retryAll(): RedirectResponse
    {
        $failedJobs = MetaPageSubscriptionFailedJob::query()->orderBy('id')->get();

        foreach ($failedJobs as $failedJob) {
            $this->dispatchFailedJob($failedJob);
            $failedJob->forceFill(['retried_at' => now()])->save();
        }

        return back()->with('success', 'Jobs fallidos de paginas Meta reenviados a la cola.');
    }

    private function dispatchFailedJob(MetaPageSubscriptionFailedJob $failedJob): void
    {
        match ($failedJob->action) {
            'scan' => MetaPageSubscriptionScanJob::dispatch(),
            'check' => $failedJob->resource_id ? MetaPageSubscriptionCheckJob::dispatch((int) $failedJob->resource_id) : null,
            'subscribe' => $failedJob->resource_id ? MetaPageSubscribeLeadgenJob::dispatch((int) $failedJob->resource_id) : null,
            'unsubscribe' => filled($failedJob->resource_identifier)
                ? MetaPageUnsubscribeLeadgenJob::dispatch($failedJob->resource_id ? (int) $failedJob->resource_id : null, (string) $failedJob->resource_identifier)
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
