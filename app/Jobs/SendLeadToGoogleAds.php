<?php

namespace App\Jobs;

use App\Http\Services\Convention\GoogleAdsConversionsService;
use App\Models\CrmState;
use App\Models\GoogleAdsConversionJob;
use App\Models\GoogleAdsFailedJob;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trabajo para enviar un lead a Google Ads Conversions API de forma asíncrona.
 * Esto permite que el proceso de creación/actualización del lead no se vea afectado por la latencia o posibles errores en la comunicación con Google Ads, mejorando la experiencia del usuario y la robustez del sistema.
 * El trabajo recibe el ID del lead y opcionalmente el ID del CrmState, y utiliza el GoogleAdsConversionsService para enviar el evento a Google Ads.    
 */
class SendLeadToGoogleAds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $leadId;
    public ?string $crmStateId;

    public $tries = 5;

    public function __construct(int $leadId, ?string $crmStateId = null)
    {
        $this->leadId = $leadId;
        $this->crmStateId = $crmStateId;
        $this->onQueue('tracking');
    }

    public function backoff(): array
    {
        return [
            1800,
            14400,
            28800,
            43200,
        ];
    }

    public function handle(GoogleAdsConversionsService $service): void
    {
        $lead = Lead::query()
            ->with(['customer', 'crmState'])
            ->find($this->leadId);

        if (! $lead) {
            $this->recordFailed(null, null, null, 'Lead no encontrado.', null);
            return;
        }

        $customer = $lead->customer;

        if (! $customer) {
            $this->recordFailed($lead, null, $lead->crmState, 'El lead no tiene customer asociado.', null);
            return;
        }

        if (! $customer->id_Gads) {
            $this->recordFailed($lead, $customer, $lead->crmState, 'El customer no tiene id_Gads configurado.', null);
            return;
        }

        $crmState = $this->resolveCrmState($lead);

        if (! $crmState) {
            $this->recordFailed($lead, $customer, null, 'No existe CrmState configurable para el lead.', null);
            return;
        }

        $orderId = $service->buildOrderId($lead, $crmState);

        if ($this->alreadySent($lead->id, $orderId)) {
            Log::info('Google Ads conversion skipped because it was already sent.', [
                'lead_id' => $lead->id,
                'customer_id' => $customer->id,
                'crm_state_id' => $crmState->id,
                'order_id' => $orderId,
            ]);
            return;
        }

        if (! $crmState->google_ads_conversion_enabled) {
            $this->recordConversionJob($lead, $customer, $crmState, [
                'success' => false,
                'partial_failure' => false,
                'error_message' => 'El CrmState no tiene habilitado el envio a Google Ads.',
                'order_id' => $orderId,
                'conversion_action' => null,
                'payload' => null,
                'response' => null,
                'click_identifier_type' => null,
                'click_identifier_value' => null,
                'skipped' => true,
            ]);
            return;
        }

        $result = $service->sendLeadConversion($lead, $customer, $crmState);
        $this->recordConversionJob($lead, $customer, $crmState, $result);

        if (! ($result['success'] ?? false) && ! ($result['skipped'] ?? false)) {
            throw new \RuntimeException($result['error_message'] ?? 'Error enviando conversion a Google Ads.');
        }
    }

    public function failed(Throwable $e): void
    {
        $lead = Lead::query()
            ->with(['customer', 'crmState'])
            ->find($this->leadId);

        $crmState = $lead ? $this->resolveCrmState($lead) : null;

        $this->recordFailed(
            $lead,
            $lead?->customer,
            $crmState,
            $e->getMessage(),
            $e
        );

        Log::error('SendLeadToGoogleAds failed permanently.', [
            'lead_id' => $this->leadId,
            'crm_state_id' => $this->crmStateId,
            'message' => $e->getMessage(),
        ]);
    }

    protected function resolveCrmState(Lead $lead): ?CrmState
    {
        if ($this->crmStateId) {
            return CrmState::query()->find($this->crmStateId);
        }

        if ($lead->crmState) {
            return $lead->crmState;
        }

        $query = CrmState::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', ['leads']);

        if ($lead->integration_id) {
            $sameIntegration = (clone $query)
                ->where('id', 'like', $lead->integration_id.'-%')
                ->first();

            if ($sameIntegration) {
                return $sameIntegration;
            }
        }

        return $query->first();
    }

    protected function alreadySent(int $leadId, string $orderId): bool
    {
        return GoogleAdsConversionJob::query()
            ->where('lead_id', $leadId)
            ->where('order_id', $orderId)
            ->where('success', true)
            ->exists();
    }

    protected function recordConversionJob($lead, $customer, ?CrmState $crmState, array $result): GoogleAdsConversionJob
    {
        $payload = $this->encode($result['payload'] ?? null);
        $response = $this->encode($result['response'] ?? null);
        $conversionAction = (string) ($result['conversion_action'] ?? '');

        return GoogleAdsConversionJob::updateOrCreate(
            [
                'lead_id' => $lead?->id,
                'order_id' => $result['order_id'] ?? null,
            ],
            [
                'customer_id' => $customer?->id,
                'crm_state_id' => $crmState?->id,
                'status' => $crmState?->name,
                'conversion_action_id' => $conversionAction ? last(explode('/', $conversionAction)) : ($crmState?->google_ads_conversion_action_id),
                'conversion_action_resource_name' => $conversionAction ?: $crmState?->google_ads_conversion_action_resource_name,
                'click_identifier_type' => $result['click_identifier_type'] ?? null,
                'click_identifier_value' => $result['click_identifier_value'] ?? null,
                'attempts' => $this->attempts(),
                'payload' => $payload,
                'response' => $response,
                'success' => (bool) ($result['success'] ?? false),
                'partial_failure' => (bool) ($result['partial_failure'] ?? false),
                'error_message' => $result['error_message'] ?? null,
                'processed_at' => now(),
            ]
        );
    }

    protected function recordFailed($lead, $customer, ?CrmState $crmState, string $message, ?Throwable $exception): GoogleAdsFailedJob
    {
        $lastJob = $lead
            ? GoogleAdsConversionJob::query()
                ->where('lead_id', $lead->id)
                ->latest('id')
                ->first()
            : null;

        return GoogleAdsFailedJob::create([
            'lead_id' => $lead?->id ?? $this->leadId,
            'customer_id' => $customer?->id,
            'crm_state_id' => $crmState?->id ?? $this->crmStateId,
            'status' => $crmState?->name,
            'job_class' => self::class,
            'attempts' => $this->attempts(),
            'payload' => $lastJob?->payload,
            'response' => $lastJob?->response,
            'error_message' => $message,
            'exception' => $exception ? (string) $exception : null,
            'failed_at' => now(),
        ]);
    }

    protected function encode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
