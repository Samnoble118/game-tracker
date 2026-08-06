<?php

declare(strict_types=1);

namespace GameTracker\Core;

use PDO;

final class Database
{
    private static ?self $instance = null;

    private ?PDO $connection = null;

    private function __construct(private readonly string $databasePath)
    {
    }

    private function __clone(): void
    {
    }

    public function __wakeup(): never
    {
        throw new \LogicException('Database cannot be unserialized.');
    }

    public static function instance(string $databasePath): self
    {
        return self::$instance ??= new self($databasePath);
    }

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
            ]);
        }

        return $this->connection;
    }
}
