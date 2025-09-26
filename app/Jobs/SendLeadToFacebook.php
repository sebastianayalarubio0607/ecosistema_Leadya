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

class SendLeadToFacebook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $leadId;
    public int $customerId;

    public $tries = 1;
    public $backoff = [60, 120, 300, 600];

    public function __construct(int $leadId, int $customerId)
    {
        $this->leadId = $leadId;
        $this->customerId = $customerId;
        $this->onQueue('tracking');
    }

    public function handle(FacebookConversionsService $svc): void
    {
        $lead = Lead::find($this->leadId);
        if (!$lead) return;

        // Usar el customerId del job, no el del lead
        $result = $svc->sendLeadEvent($lead, $this->customerId);

        $baseLog = [
            'lead_id'       => $lead->id,
            'customer_id'   => $this->customerId, // 👈 corregido
            'event_name'    => 'Lead',
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
