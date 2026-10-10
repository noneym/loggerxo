<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ClickHouse (HTTP interface)
    |--------------------------------------------------------------------------
    */
    'clickhouse' => [
        'protocol' => env('CLICKHOUSE_PROTOCOL', 'http'),
        'host' => env('CLICKHOUSE_HOST', '127.0.0.1'),
        'port' => (int) env('CLICKHOUSE_PORT', 8123),
        'database' => env('CLICKHOUSE_DB', 'default'),
        'username' => env('CLICKHOUSE_USER', 'default'),
        'password' => env('CLICKHOUSE_PASSWORD', ''),
        'timeout' => (int) env('CLICKHOUSE_TIMEOUT', 30),
        'tables' => [
            'events' => env('CLICKHOUSE_TABLE_EVENTS', 'lx_events'),
            'transactions' => env('CLICKHOUSE_TABLE_TRANSACTIONS', 'lx_transactions'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingest pipeline
    |--------------------------------------------------------------------------
    */
    'ingest' => [
        'queue_key' => 'lx:ingest',
        'dead_key' => 'lx:ingest:dead',
        'batch_size' => (int) env('INGEST_BATCH_SIZE', 500),
        'batch_wait_ms' => (int) env('INGEST_BATCH_WAIT_MS', 1500),
        'rate_limit_per_minute' => (int) env('INGEST_RATE_LIMIT_PER_MINUTE', 6000),
        'max_body_kb' => (int) env('INGEST_MAX_BODY_KB', 2048),
        'max_events_per_request' => 500,
        'max_browser_events_per_request' => 50,
        'max_event_bytes' => 256 * 1024,
        'max_breadcrumbs' => 50,
        'max_frames' => 60,
        'max_message_length' => 8000,
    ],

    'default_retention_days' => (int) env('DEFAULT_RETENTION_DAYS', 30),
    'max_retention_days' => 365,

    'registration_enabled' => filter_var(env('REGISTRATION_ENABLED', true), FILTER_VALIDATE_BOOL),

    'super_admin_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('SUPER_ADMIN_EMAILS', ''))))),

    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'levels' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],

    'sdk' => [
        'browser_version' => '0.1.0',
        'laravel_version' => '0.1.0',
    ],
];
