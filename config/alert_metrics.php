<?php

return [
    'enabled' => env('ALERT_METRICS_ENABLED', true),
    'queue' => 'alert-metrics',
    'connection' => 'alert_metrics',
];
