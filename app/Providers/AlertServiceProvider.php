<?php

namespace App\Providers;

use App\Jobs\Alerts\ProcessAlertsJob;
use App\Models\MetaAdAccount;
use App\Models\MetaAdAccountStatusHistory;
use App\Models\User;
use App\Observers\Alerts\MetaAlertObserver;
use App\Services\Alerts\AlertAvailability;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AlertServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('manage-alerts', function (User $user) {
            $ids = config('alerts.manager_ids', []);

            return ! $ids || in_array((string) $user->id, $ids, true);
        });
        MetaAdAccount::observe(MetaAlertObserver::class);
        MetaAdAccountStatusHistory::observe(MetaAlertObserver::class);
        $this->loadRoutesFrom(base_path('routes/alerts.php'));
        config(['queue.connections.alert_metrics' => [
            'driver' => 'database', 'connection' => config('queue.connections.database.connection'),
            'table' => config('queue.connections.database.table', 'jobs'), 'queue' => 'alert-metrics',
            'retry_after' => 1900, 'after_commit' => true,
        ]]);
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('alerts:metrics')->everyMinute()->withoutOverlapping()
                ->when(fn () => app(\App\Services\Alerts\Metrics\MetricAvailability::class)->ready());
            $schedule->job(new ProcessAlertsJob)->everyMinute()->withoutOverlapping()
                ->when(fn () => app(AlertAvailability::class)->ready());
        });
    }
}
