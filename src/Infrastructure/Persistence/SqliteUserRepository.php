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
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                dashboard_image TEXT NULL,
                dashboard_image_mode TEXT NOT NULL DEFAULT 'banner',
                dashboard_overlay INTEGER NOT NULL DEFAULT 55,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );

        $this->migrateUsername();
        $this->migrateDashboardAppearance();
    }

    public function save(User $user): void
    {
        $parameters = [
            'username' => $user->username(),
            'email' => strtolower($user->email()),
            'password_hash' => $user->passwordHash(),
            'dashboard_image' => $user->dashboardImage(),
            'dashboard_image_mode' => $user->dashboardImageMode(),
            'dashboard_overlay' => $user->dashboardOverlay(),
        ];

        if ($user->id() === null) {
            $statement = $this->connection->prepare(
                'INSERT INTO users (username, email, password_hash, dashboard_image, dashboard_image_mode, dashboard_overlay)
                 VALUES (:username, :email, :password_hash, :dashboard_image, :dashboard_image_mode, :dashboard_overlay)'
            );
            $statement->execute($parameters);
            $user->assignId((int) $this->connection->lastInsertId());
            return;
        }

        $statement = $this->connection->prepare(
            'UPDATE users
             SET username = :username, email = :email, password_hash = :password_hash,
                 dashboard_image = :dashboard_image, dashboard_image_mode = :dashboard_image_mode,
                 dashboard_overlay = :dashboard_overlay
             WHERE id = :id'
        );
        $statement->execute([...$parameters, 'id' => $user->id()]);
    }

    public function find(int $id): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT id, username, email, password_hash, dashboard_image, dashboard_image_mode, dashboard_overlay FROM users WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        return $this->hydrate($statement->fetch());
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT id, username, email, password_hash, dashboard_image, dashboard_image_mode, dashboard_overlay FROM users WHERE email = :email COLLATE NOCASE'
        );
        $statement->execute(['email' => strtolower(trim($email))]);

        return $this->hydrate($statement->fetch());
    }

    public function findByUsername(string $username): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT id, username, email, password_hash, dashboard_image, dashboard_image_mode, dashboard_overlay
             FROM users WHERE username = :username COLLATE NOCASE'
        );
        $statement->execute(['username' => trim($username)]);

        return $this->hydrate($statement->fetch());
    }

    private function hydrate(array|false $row): ?User
    {
        return $row === false ? null : new User(
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            id: (int) $row['id'],
            username: $row['username'] === null ? null : (string) $row['username'],
            dashboardImage: $row['dashboard_image'] === null ? null : (string) $row['dashboard_image'],
            dashboardImageMode: (string) $row['dashboard_image_mode'],
            dashboardOverlay: (int) $row['dashboard_overlay'],
        );
    }

    private function migrateUsername(): void
    {
        $columns = $this->connection->query('PRAGMA table_info(users)')->fetchAll();

        if (!in_array('username', array_column($columns, 'name'), true)) {
            $this->connection->exec('ALTER TABLE users ADD COLUMN username TEXT NULL');
        }

        $this->connection->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS users_username_unique
             ON users(username COLLATE NOCASE) WHERE username IS NOT NULL'
        );
    }

    private function migrateDashboardAppearance(): void
    {
        $columns = array_column($this->connection->query('PRAGMA table_info(users)')->fetchAll(), 'name');

        if (!in_array('dashboard_image', $columns, true)) {
            $this->connection->exec('ALTER TABLE users ADD COLUMN dashboard_image TEXT NULL');
        }
        if (!in_array('dashboard_image_mode', $columns, true)) {
            $this->connection->exec("ALTER TABLE users ADD COLUMN dashboard_image_mode TEXT NOT NULL DEFAULT 'banner'");
        }
        if (!in_array('dashboard_overlay', $columns, true)) {
            $this->connection->exec('ALTER TABLE users ADD COLUMN dashboard_overlay INTEGER NOT NULL DEFAULT 55');
        }
    }
}
