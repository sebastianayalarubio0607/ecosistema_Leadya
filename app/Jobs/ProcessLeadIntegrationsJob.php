<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Http\Services\Integration\IntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Trabajo para procesar las integraciones de un lead de forma asíncrona.
 * Esto permite que el proceso de creación/actualización del lead no se vea afectado por la latencia o posibles errores de las integraciones, mejorando la experiencia del usuario y la robustez del sistema.
 * El trabajo recibe el ID del lead y un array de integraciones a procesar, y utiliza el IntegrationService para ejecutar la lógica de cada integración.
 * Se implementa un         middleware de WithoutOverlapping para evitar que se procesen múltiples trabajos simultáneamente para el mismo lead, lo que podría causar conflictos o duplicados en las integraciones.
 * Se configuran reintentos y backoff para manejar posibles fallos temporales en las integraciones, y se asigna el trabajo a una cola específica para integraciones.
 * En caso de fallo, se puede implementar un método failed() para registrar el error o notificar a los administradores, asegurando que los                  
 */
class ProcessLeadIntegrationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $lead;
    private $integrations;

    /**
     * Create a new job instance.
     */
    public function __construct(Lead $lead, $integrations)
    {
        $this->lead = $lead;
        $this->integrations = $integrations;
    }

    /**
     * Execute the job.
     */
    public function handle(IntegrationService $integrationService)
    {
        $integrationService->processLeadIntegrations($this->lead, $this->integrations);
    }
}
