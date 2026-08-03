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
    'outbox' => [
        'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 5),
        'base_backoff_seconds' => (int) env('OUTBOX_BASE_BACKOFF_SECONDS', 5),
        'max_backoff_seconds' => (int) env('OUTBOX_MAX_BACKOFF_SECONDS', 900),
        'claim_timeout_seconds' => (int) env('OUTBOX_CLAIM_TIMEOUT_SECONDS', 120),
        'heartbeat_ttl_seconds' => (int) env('OUTBOX_HEARTBEAT_TTL_SECONDS', 180),
    ],
    'notifications' => [
        'driver' => 'deterministic',
        'fail_event_ids' => array_values(array_filter(explode(
            ',',
            (string) env('DETERMINISTIC_NOTIFICATION_FAIL_EVENT_IDS', ''),
        ))),
    ],
    'consignment' => [
        'quote_ttl_seconds' => (int) env('CHABOK_QUOTE_TTL_SECONDS', 900),
        'editable_statuses' => ['CFM', 'PD'],
        'legacy_pricing' => [
            'base_url' => (string) env('LEGACY_PRICING_BASE_URL', ''),
            'username' => (string) env('LEGACY_PRICING_USERNAME', ''),
            'password' => (string) env('LEGACY_PRICING_PASSWORD', ''),
            'token_ttl_seconds' => (int) env('LEGACY_PRICING_TOKEN_TTL_SECONDS', 300),
            'expiry_timezone' => (string) env('LEGACY_PRICING_EXPIRY_TIMEZONE', 'UTC'),
            'expiry_skew_seconds' => (int) env('LEGACY_PRICING_EXPIRY_SKEW_SECONDS', 30),
            'connect_timeout_seconds' => 3,
            'request_timeout_seconds' => 10,
            'origin_codes' => json_decode((string) env('LEGACY_PRICING_ORIGIN_CODES_JSON', '{}'), true) ?: [],
            'destination_codes' => json_decode((string) env('LEGACY_PRICING_DESTINATION_CODES_JSON', '{}'), true) ?: [],
            'party_codes' => json_decode((string) env('LEGACY_PRICING_PARTY_CODES_JSON', '{}'), true) ?: [],
            'input_values' => json_decode((string) env('LEGACY_PRICING_INPUT_VALUES_JSON', '{}'), true) ?: [],
        ],
    ],
    'pricing' => [
        'provider' => (string) env('CHABOK_PRICING_PROVIDER', 'legacy'),
        'quote_ttl_seconds' => (int) env('CHABOK_QUOTE_TTL_SECONDS', 900),
        'historical_recalculation_mode' => 'ORIGINAL_AS_OF',
    ],
];
