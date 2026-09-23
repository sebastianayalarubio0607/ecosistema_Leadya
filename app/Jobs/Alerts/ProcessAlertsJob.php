<?php

namespace App\Jobs\Alerts;

use App\Services\Alerts\AlertAvailability;
use App\Services\Alerts\AlertNotificationService;
use App\Services\Alerts\ProcessAlertObservations;
use App\Services\Alerts\ReconcileMetaAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessAlertsJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [60, 120, 300];

    public function __construct()
    {
        $this->onQueue(config('alerts.queue', 'alerts'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('alerts-processing'))->releaseAfter(60)->expireAfter(75)];
    }

    public function handle(ReconcileMetaAlerts $reconcile, ProcessAlertObservations $processor, AlertNotificationService $notifications): void
    {
        if (! app(AlertAvailability::class)->ready()) {
            return;
        }
        $caughtUp = $reconcile->run();
        $processor->run();
        // Do not deliver while recovery observations may still be waiting.
        if ($caughtUp && ! \App\Models\Alerts\AlertObservation::whereNull('processed_at')->exists()) {
            $notifications->evaluate();
        }
    }
}
