<?php

declare(strict_types=1);

/**
 * Records structured timing information for prepared database statements.
 */

namespace GameTracker\Core;

use PDOStatement;
use Throwable;

/** Writes one JSON line per executed statement for local tailing and analysis. */
final class LoggedPDOStatement extends PDOStatement
{
    /** Receives logging configuration from PDO's statement-class option. */
    protected function __construct(
        private readonly string $logPath,
        private readonly float $slowThresholdMs,
    ) {
    }

    /** Executes the statement and records its duration without parameter values. */
    public function execute(?array $params = null): bool
    {
        $startedAt = hrtime(true);
        $error = null;

        try {
            return parent::execute($params);
        } catch (Throwable $exception) {
            $error = $exception::class;
            throw $exception;
        } finally {
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
            $directory = dirname($this->logPath);
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            $entry = json_encode([
                'timestamp' => gmdate('c'),
                'duration_ms' => round($durationMs, 3),
                'slow' => $durationMs >= $this->slowThresholdMs,
                'statement' => preg_replace('/\s+/', ' ', trim($this->queryString)),
                'parameter_count' => $params === null ? 0 : count($params),
                'error' => $error,
            ], JSON_UNESCAPED_SLASHES);

            if ($entry !== false) {
                @file_put_contents($this->logPath, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        }
    }
}
