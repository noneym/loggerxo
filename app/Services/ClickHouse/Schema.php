<?php

namespace App\Services\ClickHouse;

class Schema
{
    /**
     * DDL statements, idempotent (IF NOT EXISTS). Run by `loggerxo:clickhouse-migrate`.
     *
     * @return array<string, string>
     */
    public static function statements(ClickHouseClient $ch): array
    {
        $events = $ch->table('events');
        $transactions = $ch->table('transactions');
        $maxDays = (int) config('loggerxo.max_retention_days', 365);

        return [
            "create {$events}" => <<<SQL
CREATE TABLE IF NOT EXISTS {$events}
(
    event_id        UUID,
    project_id      UInt64,
    issue_id        UInt64 DEFAULT 0,
    type            LowCardinality(String),
    level           LowCardinality(String),
    timestamp       DateTime64(3, 'UTC'),
    received_at     DateTime64(3, 'UTC') DEFAULT now64(3),
    environment     LowCardinality(String) DEFAULT '',
    release         LowCardinality(String) DEFAULT '',
    platform        LowCardinality(String) DEFAULT '',
    logger          LowCardinality(String) DEFAULT '',
    message         String DEFAULT '',
    exception_type  String DEFAULT '',
    exception_value String DEFAULT '',
    fingerprint     String DEFAULT '',
    transaction     String DEFAULT '',
    server_name     LowCardinality(String) DEFAULT '',
    url             String DEFAULT '',
    http_method     LowCardinality(String) DEFAULT '',
    status_code     UInt16 DEFAULT 0,
    user_id         String DEFAULT '',
    user_email      String DEFAULT '',
    user_ip         String DEFAULT '',
    user_agent      String DEFAULT '',
    browser         LowCardinality(String) DEFAULT '',
    os              LowCardinality(String) DEFAULT '',
    device          LowCardinality(String) DEFAULT '',
    sdk             LowCardinality(String) DEFAULT '',
    tags            Map(LowCardinality(String), String),
    payload         String CODEC(ZSTD(3)),
    INDEX idx_message message TYPE tokenbf_v1(32768, 3, 0) GRANULARITY 4,
    INDEX idx_issue issue_id TYPE bloom_filter(0.01) GRANULARITY 4,
    INDEX idx_user user_id TYPE bloom_filter(0.01) GRANULARITY 4,
    INDEX idx_level level TYPE set(16) GRANULARITY 4
)
ENGINE = MergeTree
PARTITION BY toYYYYMMDD(timestamp)
ORDER BY (project_id, type, timestamp, event_id)
TTL toDateTime(timestamp) + toIntervalDay({$maxDays})
SETTINGS index_granularity = 8192, ttl_only_drop_parts = 1
SQL,
            "create {$transactions}" => <<<SQL
CREATE TABLE IF NOT EXISTS {$transactions}
(
    event_id        UUID,
    project_id      UInt64,
    timestamp       DateTime64(3, 'UTC'),
    received_at     DateTime64(3, 'UTC') DEFAULT now64(3),
    environment     LowCardinality(String) DEFAULT '',
    release         LowCardinality(String) DEFAULT '',
    platform        LowCardinality(String) DEFAULT '',
    op              LowCardinality(String) DEFAULT 'http.server',
    name            String,
    http_method     LowCardinality(String) DEFAULT '',
    status_code     UInt16 DEFAULT 0,
    duration_ms     Float64,
    db_time_ms      Float64 DEFAULT 0,
    db_query_count  UInt32 DEFAULT 0,
    memory_mb       Float32 DEFAULT 0,
    server_name     LowCardinality(String) DEFAULT '',
    url             String DEFAULT '',
    user_id         String DEFAULT '',
    user_ip         String DEFAULT '',
    user_agent      String DEFAULT '',
    browser         LowCardinality(String) DEFAULT '',
    os              LowCardinality(String) DEFAULT '',
    device          LowCardinality(String) DEFAULT '',
    sdk             LowCardinality(String) DEFAULT '',
    tags            Map(LowCardinality(String), String),
    measurements    Map(LowCardinality(String), Float64),
    payload         String CODEC(ZSTD(3)),
    INDEX idx_name name TYPE bloom_filter(0.01) GRANULARITY 4
)
ENGINE = MergeTree
PARTITION BY toYYYYMMDD(timestamp)
ORDER BY (project_id, op, name, timestamp)
TTL toDateTime(timestamp) + toIntervalDay({$maxDays})
SETTINGS index_granularity = 8192, ttl_only_drop_parts = 1
SQL,
        ];
    }
}
