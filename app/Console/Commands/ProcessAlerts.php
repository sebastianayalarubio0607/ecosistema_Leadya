<?php

namespace App\Console\Commands;

use App\Jobs\Alerts\ProcessAlertsJob;
use App\Services\Alerts\AlertAvailability;
use Illuminate\Console\Command;

class ProcessAlerts extends Command
{
    protected $signature = 'alerts:process';

    protected $description = 'Procesa observaciones y notificaciones internas; no consulta Meta ni modifica sus cuentas.';

    public function handle(): int
    {
        if (! app(AlertAvailability::class)->ready()) {
            $this->warn('El módulo no está habilitado o su migración no está aplicada.');

            return self::FAILURE;
        }
        ProcessAlertsJob::dispatchSync();
        $this->info('Ciclo de alertas completado.');

        return self::SUCCESS;
    }
}
