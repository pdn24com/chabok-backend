<?php

declare(strict_types=1);

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('BRANCH_PANEL_ORIGINS', '')),
)));

return [
    'branch_panel' => [
        'origins' => $origins,
        'local_http_allowed' => (bool) env('LOCAL_HTTP_ALLOWED', false),
    ],
    'access_token' => [
        'secret' => (string) env('CHABOK_JWT_SECRET', env('APP_KEY', '')),
        'issuer' => (string) env('CHABOK_JWT_ISSUER', 'chabok-api'),
        'audience' => (string) env('CHABOK_JWT_AUDIENCE', 'chabok-branch-panel'),
        'ttl_seconds' => (int) env('CHABOK_ACCESS_TOKEN_TTL', 300),
    ],
    'refresh_cookie' => [
        'name' => (string) env('CHABOK_REFRESH_COOKIE', 'chabok_refresh'),
        'path' => '/api/v1/auth',
        'same_site' => 'lax',
        'secure' => true,
        'http_only' => true,
        'ttl_seconds' => (int) env('CHABOK_REFRESH_TOKEN_TTL', 2592000),
    ],
    'forced_password_allowed_routes' => [
        'auth.password.change',
        'auth.refresh',
        'auth.logout',
        'auth.logout-all',
    ],
];
