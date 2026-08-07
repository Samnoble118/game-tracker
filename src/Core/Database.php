<?php

declare(strict_types=1);

/**
 * Provides the application's lazily initialized PDO database connection.
 */

namespace GameTracker\Core;

use PDO;

/**
 * Maintains one shared database wrapper and opens PDO only when first needed.
 */
final class Database
{
    private static ?self $instance = null;

    private ?PDO $connection = null;

    /**
     * Stores the SQLite path without opening a connection immediately.
     */
    private function __construct(
        private readonly string $databasePath,
        private readonly string $queryLogPath,
        private readonly float $slowQueryThresholdMs,
    ) {
    }

    /**
     * Prevents the singleton from being cloned.
     */
    private function __clone(): void
    {
    }

    /**
     * Prevents serialized data from creating another database instance.
     */
    public function __wakeup(): never
    {
        throw new \LogicException('Database cannot be unserialized.');
    }

    /**
     * Returns the shared database wrapper, creating it on first access.
     */
    public static function instance(
        string $databasePath,
        ?string $queryLogPath = null,
        float $slowQueryThresholdMs = 100.0,
    ): self
    {
        return self::$instance ??= new self(
            $databasePath,
            $queryLogPath ?? dirname($databasePath) . '/logs/database.jsonl',
            $slowQueryThresholdMs,
        );
    }

    /**
     * Returns the shared PDO connection, opening SQLite on first access.
     */
    public function connection(): PDO
    {
        if ($this->connection === null) {
            $directory = dirname($this->databasePath);

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $this->connection = new PDO('sqlite:' . $this->databasePath, options: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_STATEMENT_CLASS => [LoggedPDOStatement::class, [
                    $this->queryLogPath,
                    $this->slowQueryThresholdMs,
                ]],
            ]);
            $this->connection->exec('PRAGMA foreign_keys = ON');
            $this->connection->exec('PRAGMA journal_mode = WAL');
            $this->connection->exec('PRAGMA busy_timeout = 5000');
        }

        return $this->connection;
    }
}
