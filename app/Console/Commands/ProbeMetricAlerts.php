<?php

namespace App\Console\Commands;

use App\Models\Alerts\AlertMetricSubscription;
use App\Models\Customer;
use App\Services\Alerts\Metrics\DirectImpressionReader;
use App\Services\Alerts\Metrics\SaveMetricMonitor;
use Illuminate\Console\Command;

class ProbeMetricAlerts extends Command
{
    protected $signature = 'alerts:metrics-probe {customer : ID del cliente} {--platform=meta : meta o google} {--level=campaign : campaign, group o ad}';

    protected $description = 'Prueba una lectura directa de campañas sin crear alertas ni notificaciones';

    public function handle(): int
    {
        $platform = $this->option('platform');
        $level = $this->option('level');
        $customer = Customer::find($this->argument('customer'));
        if (! in_array($platform, ['meta', 'google'], true) || ! in_array($level, ['campaign', 'group', 'ad'], true) || ! $customer?->status) {
            $this->error('Selecciona un cliente activo y una plataforma válida.');

            return self::FAILURE;
        }
        $s = new AlertMetricSubscription(['customer_id' => $customer->id, 'settings' => array_replace(SaveMetricMonitor::defaults(), [
            'platforms' => [$platform], 'level' => $level, 'window_mode' => $platform === 'google' && $level === 'ad' ? 'days' : 'hours',
        ])]);
        $s->setRelation('customer', $customer);
        try {
            $rows = app(DirectImpressionReader::class)->read($s, now());
            $this->info('Consulta completa ('.$platform.' / '.$level.'). Entidades activas: '.count($rows));
            if ($rows) {
                $this->line('Periodo de la primera cuenta: '.$rows[0]['window_start'].' → '.$rows[0]['window_end']);
            }
        } catch (\Throwable $e) {
            $this->error('Consulta no disponible. Revisa conectividad, credenciales y permisos. Tipo: '.class_basename($e));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
