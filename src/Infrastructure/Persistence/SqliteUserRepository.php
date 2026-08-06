<?php

declare(strict_types=1);

/**
 * Implements registered-user persistence using SQLite.
 */

namespace GameTracker\Infrastructure\Persistence;

use GameTracker\Domain\Entity\User;
use GameTracker\Domain\Repository\UserRepository;
use PDO;

final readonly class SqliteUserRepository implements UserRepository
{
    public function __construct(private PDO $connection)
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    public function save(User $user): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)'
        );
        $statement->execute([
            'email' => strtolower($user->email()),
            'password_hash' => $user->passwordHash(),
        ]);
        $user->assignId((int) $this->connection->lastInsertId());
    }

    public function find(int $id): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT id, email, password_hash FROM users WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        return $this->hydrate($statement->fetch());
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT id, email, password_hash FROM users WHERE email = :email COLLATE NOCASE'
        );
        $statement->execute(['email' => strtolower(trim($email))]);

        return $this->hydrate($statement->fetch());
    }

    private function hydrate(array|false $row): ?User
    {
        return $row === false ? null : new User(
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            id: (int) $row['id'],
        );
    }
}
