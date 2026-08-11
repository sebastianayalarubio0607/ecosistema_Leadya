<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Jobs\MetaAdAccountSubscribeJob;
use App\Jobs\MetaAdAccountSubscriptionCheckJob;
use App\Jobs\MetaAdAccountSubscriptionScanJob;
use App\Jobs\MetaAdAccountUnsubscribeJob;
use App\Models\MetaAdAccountSubscriptionFailedJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MetaAdAccountSubscriptionJobController extends Controller
{
    private const QUEUE_TABLE = 'meta_ad_account_subscription_jobs';

    public function index(Request $request): View
    {
        return view('meta.ad_accounts.subscription_jobs', [
            'queuedJobs' => DB::table(self::QUEUE_TABLE)
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'queued_page')
                ->withQueryString()
                ->through(fn ($job) => $this->presentQueuedJob($job)),
            'failedJobs' => MetaAdAccountSubscriptionFailedJob::query()
                ->orderByDesc('failed_at')
                ->paginate(15, ['*'], 'failed_page')
                ->withQueryString(),
        ]);
    }

    public function scan(): RedirectResponse
    {
        MetaAdAccountSubscriptionScanJob::dispatch();

        return back()->with('success', 'Revision de suscripciones de cuentas publicitarias enviada a la cola.');
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

        return back()->with('success', 'Job de cuenta publicitaria liberado para procesarse ahora.');
    }

    public function releaseAllQueued(): RedirectResponse
    {
        DB::table(self::QUEUE_TABLE)->update([
            'reserved_at' => null,
            'available_at' => now()->timestamp,
        ]);

        return back()->with('success', 'Todos los jobs de cuentas publicitarias quedaron disponibles.');
    }

    public function retry(MetaAdAccountSubscriptionFailedJob $failedJob): RedirectResponse
    {
        $this->dispatchFailedJob($failedJob);
        $failedJob->forceFill(['retried_at' => now()])->save();

        return back()->with('success', 'Job fallido de cuenta publicitaria reenviado a la cola.');
    }

    public function retryAll(): RedirectResponse
    {
        $failedJobs = MetaAdAccountSubscriptionFailedJob::query()->orderBy('id')->get();

        foreach ($failedJobs as $failedJob) {
            $this->dispatchFailedJob($failedJob);
            $failedJob->forceFill(['retried_at' => now()])->save();
        }

        return back()->with('success', 'Jobs fallidos de cuentas publicitarias reenviados a la cola.');
    }

    private function dispatchFailedJob(MetaAdAccountSubscriptionFailedJob $failedJob): void
    {
        match ($failedJob->action) {
            'scan' => MetaAdAccountSubscriptionScanJob::dispatch(),
            'check' => $failedJob->resource_id ? MetaAdAccountSubscriptionCheckJob::dispatch((int) $failedJob->resource_id) : null,
            'subscribe' => $failedJob->resource_id ? MetaAdAccountSubscribeJob::dispatch((int) $failedJob->resource_id) : null,
            'unsubscribe' => filled($failedJob->resource_identifier)
                ? MetaAdAccountUnsubscribeJob::dispatch($failedJob->resource_id ? (int) $failedJob->resource_id : null, (string) $failedJob->resource_identifier)
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
