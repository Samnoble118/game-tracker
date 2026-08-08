<?php

declare(strict_types=1);

/**
 * Implements registered-user persistence using SQLite.
 */

namespace GameTracker\Infrastructure\Persistence;

use GameTracker\Domain\Entity\User;
use GameTracker\Domain\Repository\UserRepository;
use PDO;

/** Stores, retrieves, and migrates registered users in SQLite. */
final readonly class SqliteUserRepository implements UserRepository
{
    /** Creates the repository and ensures the user schema is current. */
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
                merchandise_image TEXT NULL,
                merchandise_image_mode TEXT NOT NULL DEFAULT 'banner',
                merchandise_overlay INTEGER NOT NULL DEFAULT 55,
                franchise_image TEXT NULL,
                franchise_image_mode TEXT NOT NULL DEFAULT 'banner',
                franchise_overlay INTEGER NOT NULL DEFAULT 55,
                theme_preset TEXT NOT NULL DEFAULT 'archive-purple',
                theme_accent TEXT NOT NULL DEFAULT '#7c5cff', theme_background TEXT NOT NULL DEFAULT '#0b0d12',
                theme_panel TEXT NOT NULL DEFAULT '#141821', theme_text TEXT NOT NULL DEFAULT '#f5f7fb',
                layout_density TEXT NOT NULL DEFAULT 'spacious',
                profile_display_name TEXT NOT NULL DEFAULT '',
                profile_bio TEXT NOT NULL DEFAULT '',
                profile_public INTEGER NOT NULL DEFAULT 0,
                profile_image TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );

        $this->migrateUsername();
        $this->migrateDashboardAppearance();
        $this->migrateTheme();
        $this->migratePublicProfile();
    }

    /** Inserts a new user or updates an existing account. */
    public function save(User $user): void
    {
        $parameters = [
            'username' => $user->username(),
            'email' => strtolower($user->email()),
            'password_hash' => $user->passwordHash(),
            'dashboard_image' => $user->dashboardImage(),
            'dashboard_image_mode' => $user->dashboardImageMode(),
            'dashboard_overlay' => $user->dashboardOverlay(),
            'merchandise_image' => $user->merchandiseImage(),
            'merchandise_image_mode' => $user->merchandiseImageMode(),
            'merchandise_overlay' => $user->merchandiseOverlay(),
            'franchise_image' => $user->franchiseImage(),
            'franchise_image_mode' => $user->franchiseImageMode(),
            'franchise_overlay' => $user->franchiseOverlay(),
            'theme_preset'=>$user->themePreset(),'theme_accent'=>$user->themeAccent(),'theme_background'=>$user->themeBackground(),
            'theme_panel'=>$user->themePanel(),'theme_text'=>$user->themeText(),'layout_density'=>$user->layoutDensity(),
            'profile_display_name'=>$user->profileDisplayName(), 'profile_bio'=>$user->profileBio(),
            'profile_public'=>$user->profilePublic() ? 1 : 0, 'profile_image'=>$user->profileImage(),
        ];

        if ($user->id() === null) {
            $statement = $this->connection->prepare(
                'INSERT INTO users (username, email, password_hash, dashboard_image, dashboard_image_mode, dashboard_overlay, merchandise_image, merchandise_image_mode, merchandise_overlay, franchise_image, franchise_image_mode, franchise_overlay, theme_preset, theme_accent, theme_background, theme_panel, theme_text, layout_density, profile_display_name, profile_bio, profile_public, profile_image)
                 VALUES (:username, :email, :password_hash, :dashboard_image, :dashboard_image_mode, :dashboard_overlay, :merchandise_image, :merchandise_image_mode, :merchandise_overlay, :franchise_image, :franchise_image_mode, :franchise_overlay, :theme_preset, :theme_accent, :theme_background, :theme_panel, :theme_text, :layout_density, :profile_display_name, :profile_bio, :profile_public, :profile_image)'
            );
            $statement->execute($parameters);
            $user->assignId((int) $this->connection->lastInsertId());
            return;
        }

        $statement = $this->connection->prepare(
            'UPDATE users
             SET username = :username, email = :email, password_hash = :password_hash,
                 dashboard_image = :dashboard_image, dashboard_image_mode = :dashboard_image_mode,
                 dashboard_overlay = :dashboard_overlay, merchandise_image = :merchandise_image,
                 merchandise_image_mode = :merchandise_image_mode, merchandise_overlay = :merchandise_overlay,
                 franchise_image = :franchise_image, franchise_image_mode = :franchise_image_mode,
                 franchise_overlay = :franchise_overlay,
                 theme_preset=:theme_preset, theme_accent=:theme_accent, theme_background=:theme_background,
                 theme_panel=:theme_panel, theme_text=:theme_text, layout_density=:layout_density,
                 profile_display_name=:profile_display_name, profile_bio=:profile_bio,
                 profile_public=:profile_public, profile_image=:profile_image
             WHERE id = :id'
        );
        $statement->execute([...$parameters, 'id' => $user->id()]);
    }

    /** Finds and hydrates a user by ID. */
    public function find(int $id): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM users WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        return $this->hydrate($statement->fetch());
    }

    /** Finds and hydrates a user by case-insensitive email. */
    public function findByEmail(string $email): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM users WHERE email = :email COLLATE NOCASE'
        );
        $statement->execute(['email' => strtolower(trim($email))]);

        return $this->hydrate($statement->fetch());
    }

    /** Finds and hydrates a user by case-insensitive username. */
    public function findByUsername(string $username): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM users WHERE username = :username COLLATE NOCASE'
        );
        $statement->execute(['username' => trim($username)]);

        return $this->hydrate($statement->fetch());
    }

    /** Converts a database row into a user entity. */
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
            merchandiseImage: $row['merchandise_image'] === null ? null : (string) $row['merchandise_image'],
            merchandiseImageMode: (string) $row['merchandise_image_mode'],
            merchandiseOverlay: (int) $row['merchandise_overlay'],
            franchiseImage: $row['franchise_image'] === null ? null : (string) $row['franchise_image'],
            franchiseImageMode: (string) $row['franchise_image_mode'],
            franchiseOverlay: (int) $row['franchise_overlay'],
            themePreset:(string)$row['theme_preset'],themeAccent:(string)$row['theme_accent'],themeBackground:(string)$row['theme_background'],
            themePanel:(string)$row['theme_panel'],themeText:(string)$row['theme_text'],layoutDensity:(string)$row['layout_density'],
            profileDisplayName:(string)$row['profile_display_name'], profileBio:(string)$row['profile_bio'],
            profilePublic:(bool)$row['profile_public'], profileImage:$row['profile_image'] === null ? null : (string)$row['profile_image'],
        );
    }

    /** Adds username storage and uniqueness to legacy databases. */
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

    /** Adds dashboard appearance columns to legacy databases. */
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
        if (!in_array('merchandise_image', $columns, true)) {
            $this->connection->exec('ALTER TABLE users ADD COLUMN merchandise_image TEXT NULL');
        }
        if (!in_array('merchandise_image_mode', $columns, true)) {
            $this->connection->exec("ALTER TABLE users ADD COLUMN merchandise_image_mode TEXT NOT NULL DEFAULT 'banner'");
        }
        if (!in_array('merchandise_overlay', $columns, true)) {
            $this->connection->exec('ALTER TABLE users ADD COLUMN merchandise_overlay INTEGER NOT NULL DEFAULT 55');
        }
        if (!in_array('franchise_image', $columns, true)) {
            $this->connection->exec('ALTER TABLE users ADD COLUMN franchise_image TEXT NULL');
        }
        if (!in_array('franchise_image_mode', $columns, true)) {
            $this->connection->exec("ALTER TABLE users ADD COLUMN franchise_image_mode TEXT NOT NULL DEFAULT 'banner'");
        }
        if (!in_array('franchise_overlay', $columns, true)) {
            $this->connection->exec('ALTER TABLE users ADD COLUMN franchise_overlay INTEGER NOT NULL DEFAULT 55');
        }
    }

    /** Adds theme and layout preferences to legacy user tables. */
    private function migrateTheme(): void
    {
        $columns = array_column($this->connection->query('PRAGMA table_info(users)')->fetchAll(), 'name');
        $definitions = [
            'theme_preset'=>"TEXT NOT NULL DEFAULT 'archive-purple'",'theme_accent'=>"TEXT NOT NULL DEFAULT '#7c5cff'",
            'theme_background'=>"TEXT NOT NULL DEFAULT '#0b0d12'",'theme_panel'=>"TEXT NOT NULL DEFAULT '#141821'",
            'theme_text'=>"TEXT NOT NULL DEFAULT '#f5f7fb'",'layout_density'=>"TEXT NOT NULL DEFAULT 'spacious'",
        ];
        foreach ($definitions as $column=>$definition) if (!in_array($column,$columns,true)) $this->connection->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
    }

    /** Adds opt-in public cabinet and profile-image fields to legacy databases. */
    private function migratePublicProfile(): void
    {
        $columns = array_column($this->connection->query('PRAGMA table_info(users)')->fetchAll(), 'name');
        $definitions = ['profile_display_name'=>"TEXT NOT NULL DEFAULT ''",'profile_bio'=>"TEXT NOT NULL DEFAULT ''",'profile_public'=>'INTEGER NOT NULL DEFAULT 0','profile_image'=>'TEXT NULL'];
        foreach ($definitions as $column=>$definition) if (!in_array($column,$columns,true)) $this->connection->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
    }
}
