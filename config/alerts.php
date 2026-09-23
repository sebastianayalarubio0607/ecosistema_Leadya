<?php

return [
    'enabled' => env('ALERTS_ENABLED', true),
    'queue' => env('ALERTS_QUEUE', 'alerts'),
    // Like the existing back-office, authenticated users can manage by default.
    // Set a comma-separated allowlist to restrict only this new module.
    'manager_ids' => array_filter(array_map('trim', explode(',', (string) env('ALERT_MANAGER_IDS', '')))),
];
