<?php

declare(strict_types=1);

/**
 * Defines environment-independent application configuration values.
 *
 * @return array{database_path: string, query_log_path: string, slow_query_threshold_ms: float}
 */
return [
    'database_path' => dirname(__DIR__) . '/storage/game-tracker.sqlite',
    'query_log_path' => dirname(__DIR__) . '/storage/logs/database.jsonl',
    'slow_query_threshold_ms' => 100.0,
];
