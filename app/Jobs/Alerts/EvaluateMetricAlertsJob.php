<?php

namespace App\Jobs\Alerts;

use App\Services\Alerts\Metrics\EvaluateMetricSubscription;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateMetricAlertsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $uniqueFor = 1900;

    public int $tries = 1;

    public function __construct(public int $subscriptionId)
    {
        $this->onConnection(config('alert_metrics.connection'))->onQueue(config('alert_metrics.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->subscriptionId;
    }

    public function handle(EvaluateMetricSubscription $service): void
    {
        $service->run($this->subscriptionId);
    }
}
