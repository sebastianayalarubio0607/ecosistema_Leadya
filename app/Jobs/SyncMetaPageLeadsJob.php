<?php

namespace App\Jobs;

use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Models\MetaPage;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMetaPageLeadsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $uniqueFor = 300;

    public function __construct(
        public string $metaPageId,
        public ?string $metaEventTime = null,
    ) {
        $this->onQueue('meta');
    }

    public function uniqueId(): string
    {
        return $this->metaPageId;
    }

    public function handle(MetaLeadAdsSyncService $service): void
    {
        $page = MetaPage::query()->firstWhere('meta_page_id', $this->metaPageId);

        if (! $page) {
            Log::warning('SyncMetaPageLeadsJob skipped because the Meta page does not exist locally', [
                'meta_page_id' => $this->metaPageId,
                'meta_event_time' => $this->metaEventTime,
            ]);

            return;
        }

        $from = $this->resolveFromDate();

        $service->syncLeadsForPage($page, $from, now(config('app.timezone')));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncMetaPageLeadsJob failed', [
            'meta_page_id' => $this->metaPageId,
            'meta_event_time' => $this->metaEventTime,
            'message' => $exception->getMessage(),
        ]);
    }

    private function resolveFromDate(): Carbon
    {
        if (filled($this->metaEventTime)) {
            try {
                $metaEventTime = trim((string) $this->metaEventTime);

                return (is_numeric($metaEventTime)
                    ? Carbon::createFromTimestamp((int) $metaEventTime)
                    : Carbon::parse($metaEventTime)
                )->subMinutes(15);
            } catch (\Throwable) {
                // Continue with the fallback window below.
            }
        }

        return now(config('app.timezone'))->subHour();
    }
}
