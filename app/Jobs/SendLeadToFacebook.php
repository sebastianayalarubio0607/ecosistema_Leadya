<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\FacebookConversionLog;
use App\Http\Services\Convention\FacebookConversionsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
/**
 * Trabajo para enviar un lead a Facebook Conversions API de forma asíncrona.
 * Esto permite que el proceso de creación/actualización del lead no se vea afectado por la latencia o posibles errores en la comunicación con Facebook, mejorando la experiencia del usuario y la robustez del sistema.
 * El trabajo recibe el ID del lead y el ID del cliente, y utiliza el FacebookConversionsService para enviar el evento a Facebook.
 * Se configuran reintentos y backoff para manejar posibles fallos temporales en la comunicación con Facebook, y se asigna el trabajo a una cola específica para tracking, lo que ayuda a organizar y priorizar las tareas en el sistema de colas.
 * En caso de fallo, se registra el error en los logs y se lanza una excepción para que el sistema de colas pueda manejar el reintento según la configuración establecida.
 * Además, se registra un log detallado de cada intento de envío a Facebook Conversions API en la tabla FacebookConversionLog, lo que permite tener un historial de los eventos enviados y los errores ocurridos, facilitando la monitorización y el diagnóstico de problemas relacionados con la integración de
 */
class SendLeadToFacebook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $leadId;
    public int $customerId;

    public $tries = 4;
    public $backoff = [60, 120, 300, 600];

    public function __construct(int $leadId, int $customerId)
    {
        $this->leadId = $leadId;
        $this->customerId = $customerId;
        $this->onQueue('tracking');
    }
/**
 * función que maneja el envío del lead a Facebook Conversions API
 * @param  FacebookConversionsService  $svc  Servicio para enviar eventos a Facebook
 * @return void
 */
    public function handle(FacebookConversionsService $svc): void
    {
        /**
         * Recupera el lead por su ID
         */
        $lead = Lead::find($this->leadId);
        if (!$lead) return;

        /**
         * Envía el lead a Facebook Conversions API
         */
        // Usar el customerId del job, no el del lead
        $result = $svc->sendLeadEvent($lead, $this->customerId);
/**
 * Registra el resultado del envío en la tabla de logs de Facebook Conversions
 */
        $baseLog = [
            'lead_id'       => $lead->id,
            'customer_id'   => $this->customerId, // 👈 corregido
            'event_name'    => $lead->crmState?->metaEvent?->nombre ?: 'Lead',
            'event_time'    => $lead->created_at->now()->timestamp,// 👈 corregido
            'action_source' => 'website',
            'event_source_url' => $lead->page_url,
            'user_data'     => [
                'client_ip_address' => $lead->remote_ip ?? null,
                'client_user_agent' => $lead->agent ?? null,
                'fbp'               => $lead->fbp,
                'fbc'               => $lead->fbc,
                'em'                => $lead->email ?? null,
                'ph'                => $lead->phone ?? null,
                'fn'                => $lead->name ?? null,
                'ln'                => $lead->last_Name ?? null,
            ],
            'custom_data' => [
                'content_name' => 'Lead desde LP',
                'lead_source'  => 'Facebook Ads',
            ],
            'sent_at'       => now(),
            'error_message' => $result['ok']
                ? null
                : (is_string($result['error'] ?? null)
                    ? $result['error']
                    : json_encode($result['error'] ?? null)),
        ];

        FacebookConversionLog::create($baseLog);

        if (!$result['ok']) {
            logger()->warning('FB Conversions API error', [
                'lead_id'     => $lead->id,
                'customer_id' => $this->customerId,
                'status'      => $result['status'] ?? null,
                'error'       => $result['error'] ?? null,
            ]);

            throw new \RuntimeException('Error enviando Lead a Facebook');
        }
    }

    public function failed(Throwable $e): void
    {
        logger()->error('SendLeadToFacebook failed', [
            'lead_id'     => $this->leadId,
            'customer_id' => $this->customerId,
            'message'     => $e->getMessage(),
        ]);
    }
}
