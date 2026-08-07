<?php

declare(strict_types=1);

/**
 * Defines environment-independent application configuration values.
 *
 * @return array{environment: string, app_url: string, database_path: string, query_log_path: string, slow_query_threshold_ms: float, session_idle_timeout: int, session_absolute_timeout: int}
 */
return [
    'environment' => getenv('APP_ENV') ?: 'local',
    'app_url' => rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/'),
    'database_path' => getenv('DATABASE_PATH') ?: dirname(__DIR__) . '/storage/game-tracker.sqlite',
    'query_log_path' => getenv('QUERY_LOG_PATH') ?: dirname(__DIR__) . '/storage/logs/database.jsonl',
    'slow_query_threshold_ms' => (float) (getenv('SLOW_QUERY_THRESHOLD_MS') ?: 100),
    'session_idle_timeout' => (int) (getenv('SESSION_IDLE_TIMEOUT') ?: 7200),
    'session_absolute_timeout' => (int) (getenv('SESSION_ABSOLUTE_TIMEOUT') ?: 86400),
];
