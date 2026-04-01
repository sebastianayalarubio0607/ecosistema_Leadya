<?php

return [
    'base_url' => env('MONDAY_BASE_URL', 'https://api.monday.com/v2'),
    'api_version' => env('MONDAY_API_VERSION', '2024-10'),
    'timeout' => (int) env('MONDAY_TIMEOUT', 20),
    'connect_timeout' => (int) env('MONDAY_CONNECT_TIMEOUT', 10),
];
