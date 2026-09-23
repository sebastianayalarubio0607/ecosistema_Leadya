<?php

namespace App\Console\Commands;

use App\Models\Alerts\Alert;
use App\Models\Alerts\AlertObservation;
use App\Models\Alerts\AlertRule;
use App\Services\Alerts\AlertAvailability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AlertStatus extends Command
{
    protected $signature = 'alerts:status';

    protected $description = 'Muestra el estado del módulo de alertas sin modificar datos.';

    public function handle(): int
    {
        if (! app(AlertAvailability::class)->ready()) {
            $this->warn('Módulo deshabilitado o migración pendiente.');

            return self::SUCCESS;
        }
        $this->table(['Indicador', 'Valor'], [
            ['Cola requerida', config('alerts.queue')],
            ['Reglas activas', AlertRule::where('enabled', true)->count()],
            ['Incidencias abiertas', Alert::whereIn('status', ['open', 'in_progress'])->count()],
            ['Observaciones pendientes', AlertObservation::whereNull('processed_at')->count()],
            ['Último procesamiento', AlertObservation::max('processed_at') ?? 'Sin procesar'],
            ['Historial Meta procesado hasta ID', DB::table('alert_checkpoints')->where('name', 'meta_history')->value('last_id')],
        ]);

        return self::SUCCESS;
    }
}
