<?php

namespace App\Http\Services\Convention;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Customer;
use App\Models\Lead;

class FacebookConversionsService
{
    /**
     * Envía un evento de Lead a Facebook Conversions API
     *
     * @param Lead $lead El lead que se va a enviar.
     * @param int $customerId El ID del cliente asociado al lead.
     * @return array Resultado del envío con detalles.
     */
    public function sendLeadEvent(Lead $lead, int $customerId): array
    {
        /* Recupera credenciales del cliente */
        $customer = Customer::findOrFail($customerId);

        $pixelId     = data_get($customer, 'fb_pixel_id');
        $accessToken = data_get($customer, 'fb_access_token');
        $testCode    = data_get($customer, 'fb_test_event_code'); // opcional

        /* Verifica que existan las credenciales necesarias */
        if (!$pixelId || !$accessToken) {
            return [
                'ok' => false,
                'error' => 'Faltan credenciales de Facebook (pixel o access token).',
                'request' => null,
                'pixel_id' => $pixelId,
                'test_event_code' => $testCode,
            ];
        }

        /* Construye el payload */
        [$userData, $customData] = $this->buildPayload($lead);

        /* Endpoint con token en query */
        $endpoint = "https://graph.facebook.com/v17.0/{$pixelId}/events?access_token={$accessToken}";

        /* Define el evento */
        $event = [
            'event_name'       => 'Lead',
            'event_time'       => $userData['created_at'],
            'action_source'    => 'website',
            'event_source_url' => 'https://tu-dominio.com/landing',
            'user_data'        => [
                'client_ip_address' => $customData['client_ip'] ?? null,
                'client_user_agent' => $customData['agent'] ?? null,
                'fbp'               => $userData['fbp'] ?? null,
                'fbc'               => $userData['fbc'] ?? null,
                'em'                => $userData['em'] ?? null,
                'ph'                => $userData['ph'] ?? null,
                'fn'                => $userData['fn'] ?? null,
                'ln'                => $userData['ln'] ?? null,
            ],
            'custom_data' => [
                'content_name' => 'Lead desde LP',
                'lead_source'  => 'Facebook Ads',
            ],
        ];

        /* Payload final */
        $payload = [
            'data' => [$event],
        ];

        /* Agrega test_event_code si aplica */
        if (!empty($testCode)) {
            $payload['test_event_code'] = $testCode;
        }

        /* Envía la solicitud */
        $response = Http::asJson()
            ->timeout(15)
            ->retry(3, 500)
            ->post($endpoint, $payload);

        /* Maneja la respuesta */
        if ($response->successful()) {
            return [
                'ok' => true,
                'data' => $response->json(),
                'status' => $response->status(),
                'request' => $payload,
                'pixel_id' => $pixelId,
                'test_event_code' => $testCode,
            ];
        }

        return [
            'ok' => false,
            'status' => $response->status(),
            'error' => $response->json() ?: $response->body(),
            'request' => $payload,
            'pixel_id' => $pixelId,
            'test_event_code' => $testCode,
        ];
    }

    /**
     * Construye el payload con la información del lead
     */
    protected function buildPayload(Lead $lead): array
    {
        $email   = $lead->email ?? null;
        $phone   = $lead->phone ?? null;
        $fname   = $lead->name ?? null;
        $lname   = $lead->last_name ?? null;
        $city    = $lead->city ?? null;
        $country = $lead->country ?? null;
        $fbp     = $lead->fbp ?? null;
        $fbc     = $lead->fbc ?? null;

        $created_at = optional($lead->created_at)->copy()->timezone('UTC')->timestamp ?? now()->timestamp;
        $userData   = [];

        $userData['created_at'] = $created_at;

        if ($email)   $userData['em'] = [$this->sha256($this->normalizeEmail($email))];
        if ($phone)   $userData['ph'] = [$this->sha256($this->normalizePhone($phone))];
        if ($fname)   $userData['fn'] = $this->sha256($this->normLower($fname));
        if ($lname)   $userData['ln'] = $this->sha256($this->normLower($lname));
        if ($city)    $userData['ct'] = $this->sha256($this->normLower($city));
        if ($country) $userData['country'] = $this->sha256($this->normLower($country));

        if ($fbp) $userData['fbp'] = $fbp;
        if ($fbc) $userData['fbc'] = $fbc;

        $userData['external_id'] = $this->sha256((string)$lead->id);

        $customData = array_filter([
            'content_name'    => $lead->service ?? null,
            'status'          => $lead->status ?? null,
            'lead_source'     => $lead->campaign_origin ?? null,
            'service_city'    => $lead->service_city ?? null,
            'reference'       => $lead->reference ?? null,
            'page'            => $lead->page ?? null,
            'page_url'        => $lead->page_url ?? null,
            'company'         => $lead->company ?? null,
            'position'        => $lead->position ?? null,
            'agent'           => $lead->agent ?? null,
            'integration_id'  => $lead->integration_id ?? null,
            'client_ip'       => $lead->remote_ip ?? null,
        ], fn($v) => !is_null($v) && $v !== '');

        return [$userData, $customData];
    }

    protected function normLower(?string $v): string
    {
        return trim(Str::lower($v ?? ''));
    }

    protected function normalizeEmail(string $email): string
    {
        return $this->normLower($email);
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        return ltrim($digits, '0');
    }

    protected function sha256(string $value): string
    {
        return hash('sha256', trim($value));
    }
}
