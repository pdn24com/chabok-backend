<?php

declare(strict_types=1);

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('BRANCH_PANEL_ORIGINS', '')),
)));

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'X-Correlation-ID',
        'X-Node-Id',
        'Idempotency-Key',
    ],
    'exposed_headers' => ['X-Correlation-ID'],
    'max_age' => 0,
    'supports_credentials' => true,
];
