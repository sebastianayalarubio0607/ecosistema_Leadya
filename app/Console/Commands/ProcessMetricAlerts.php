<?php

namespace App\Console\Commands;

use App\Jobs\Alerts\EvaluateMetricAlertsJob;
use App\Models\Alerts\AlertMetricSubscription;
use App\Services\Alerts\Metrics\MetricAvailability;
use App\Services\Alerts\Metrics\MetricNotificationService;
use App\Services\Alerts\Metrics\MetricSchedule;
use Illuminate\Console\Command;

class ProcessMetricAlerts extends Command
{
    protected $signature = 'alerts:metrics';

    protected $description = 'Programa consultas de métricas y entrega avisos según sus horarios independientes';

    public function handle(): int
    {
        if (! app(MetricAvailability::class)->ready()) {
            $this->warn('Alertas de métricas deshabilitadas o migración pendiente.');

            return self::SUCCESS;
        }
        AlertMetricSubscription::where('enabled', true)->whereHas('customer', fn ($q) => $q->where('status', true))->chunkById(100, function ($subscriptions) {
            foreach ($subscriptions as $s) {
                if ((! $s->next_query_at || $s->next_query_at->lte(now())) && app(MetricSchedule::class)->allows($s->settings, 'query', now())) {
                    EvaluateMetricAlertsJob::dispatch($s->id);
                }
                app(MetricNotificationService::class)->deliver($s->id);
            }
        });

        return self::SUCCESS;
    }
}
