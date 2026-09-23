<?php

namespace App\Services\Alerts\Metrics;

use App\Services\Alerts\AlertAvailability;
use Illuminate\Support\Facades\Schema;

class MetricAvailability
{
    public function ready(): bool
    {
        return config('alert_metrics.enabled') && app(AlertAvailability::class)->ready()
            && Schema::hasTable('alert_metric_runs');
    }
}
