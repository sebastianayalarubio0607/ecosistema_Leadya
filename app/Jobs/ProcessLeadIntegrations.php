<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Http\Services\Integration\IntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Trabajo para procesar las integraciones de un lead de forma asíncrona.
 * Esto permite que el proceso de creación/actualización del lead no se vea afectado por la latencia o posibles errores de las integraciones, mejorando la experiencia del usuario y la robustez del sistema.
 * El trabajo recibe el ID del lead y un array de integraciones a procesar, y utiliza el IntegrationService para ejecutar la lógica de cada integración.
 * Se implementa un         middleware de WithoutOverlapping para evitar que se procesen múltiples trabajos simultáneamente para el mismo lead, lo que podría causar conflictos o duplicados en las integraciones.
 * Se configuran reintentos y backoff para manejar posibles fallos temporales en las integraciones, y se asigna el trabajo a una cola específica para integraciones.
 * En caso de fallo, se puede implementar un método failed() para registrar el error o notificar a los administradores, asegurando que los problemas con las integraciones sean visibles y puedan ser atendidos.
 *  
 */
class ProcessLeadIntegrations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $leadId;
    public array $integrations;

    /**
     * @param int   $leadId
     * @param array $integrations   // lo que hoy pasas a tu servicio
     */
    public function __construct(int $leadId, array $integrations = [])
    {
        $this->leadId = $leadId;
        $this->integrations = $integrations;

        $this->onQueue('integrations');   // cola dedicada (opcional)
    }

    // Reintentos y backoff
    public $tries = 5;
    public $backoff = [60, 120, 300, 600];

    // Evita solapado por lead (si disparas dos veces muy rápido)
    public function middleware(): array
    {
        return [ (new WithoutOverlapping("lead:{$this->leadId}:integrations"))->expireAfter(600) ];
    }

    public function handle(IntegrationService $integrationService): void
    {
        $lead = Lead::find($this->leadId);
        if (!$lead) return;

        // === Esta era tu llamada sincrónica ===
        // $integrations = $this->integrationService->processLeadIntegrations($lead, $integrations);

        // === Ahora en el Job, inyectando el servicio ===
        $integrationService->processLeadIntegrations($lead, $this->integrations);
    }
}
