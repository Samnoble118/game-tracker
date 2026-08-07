<?php

declare(strict_types=1);

/**
 * Provides database-backed request throttling for sensitive operations.
 */

namespace GameTracker\Core\Security;

use PDO;

/** Limits repeated actions without storing raw email addresses or IP addresses. */
final readonly class RateLimiter
{
    /** Creates the rate-limit table when it does not yet exist. */
    public function __construct(private PDO $connection)
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS rate_limits (
                key_hash TEXT PRIMARY KEY,
                attempts INTEGER NOT NULL,
                window_started_at INTEGER NOT NULL
            )'
        );
    }

    /** Reports whether a key has exhausted its allowance in the active window. */
    public function tooManyAttempts(string $key, int $maximumAttempts, int $windowSeconds): bool
    {
        $statement = $this->connection->prepare(
            'SELECT attempts, window_started_at FROM rate_limits WHERE key_hash = :key_hash'
        );
        $statement->execute(['key_hash' => $this->hash($key)]);
        $row = $statement->fetch();

        if ($row === false || (int) $row['window_started_at'] <= time() - $windowSeconds) {
            return false;
        }

        return (int) $row['attempts'] >= $maximumAttempts;
    }

    /** Records one attempt and resets an expired window atomically. */
    public function hit(string $key, int $windowSeconds): void
    {
        $keyHash = $this->hash($key);
        $now = time();
        $statement = $this->connection->prepare(
            'INSERT INTO rate_limits (key_hash, attempts, window_started_at)
             VALUES (:key_hash, 1, :now)
             ON CONFLICT(key_hash) DO UPDATE SET
                attempts = CASE WHEN window_started_at <= :expired_before THEN 1 ELSE attempts + 1 END,
                window_started_at = CASE WHEN window_started_at <= :expired_before THEN :now ELSE window_started_at END'
        );
        $statement->execute([
            'key_hash' => $keyHash,
            'now' => $now,
            'expired_before' => $now - $windowSeconds,
        ]);
    }

    /** Clears an action's failures after a successful operation. */
    public function clear(string $key): void
    {
        $statement = $this->connection->prepare('DELETE FROM rate_limits WHERE key_hash = :key_hash');
        $statement->execute(['key_hash' => $this->hash($key)]);
    }

    /** Converts sensitive identifying input into a fixed, non-reversible key. */
    private function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
