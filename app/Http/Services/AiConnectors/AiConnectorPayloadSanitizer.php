<?php

namespace App\Http\Services\AiConnectors;

class AiConnectorPayloadSanitizer
{
    private const BLOCKED_KEYS = [
        'url',
        'urls',
        'clear_url',
        'back_url',
        'export_url',
        'email',
        'phone',
        'remote_ip',
        'meta_payload',
        'g_clid',
        'gclid',
        'gbraid',
        'wbraid',
        'fbp',
        'fbc',
        'ctwa_clid',
        'whasapp_user_id',
        'whatsapp_business_account_id',
        'meta_lead_id',
        'meta_page_id',
        'meta_form_id',
    ];

    public function sanitize(array $payload): array
    {
        return $this->sanitizeValue($payload);
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, self::BLOCKED_KEYS, true)) {
                continue;
            }

            $clean[$key] = $this->sanitizeValue($item);
        }

        return $clean;
    }
}
