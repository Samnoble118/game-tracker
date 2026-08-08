<?php

declare(strict_types=1);

/**
 * Verifies account persistence and username migration behavior.
 */

namespace GameTracker\Tests\Unit;

use GameTracker\Domain\Entity\User;
use GameTracker\Infrastructure\Persistence\SqliteUserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/** Covers user schema migration and account persistence. */
final class SqliteUserRepositoryTest extends TestCase
{
    /** Confirms legacy tables migrate and retain updated account settings. */
    public function test_it_migrates_and_updates_an_existing_user_table(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $repository = new SqliteUserRepository($connection);
        $user = new User('player@example.com', password_hash('strong-passphrase', PASSWORD_DEFAULT));
        $repository->save($user);
        $user->updateUsername('player_one');
        $user->updateEmail('updated@example.com');
        $user->updateDashboardAppearance('banner.webp', 'wallpaper', 70);
        $user->updateTheme('sonic-blue','#2788f5','#07111f','#102239','#f2f8ff','compact');
        $repository->save($user);

        $stored = $repository->findByUsername('PLAYER_ONE');

        self::assertNotNull($stored);
        self::assertSame('updated@example.com', $stored->email());
        self::assertSame('player_one', $stored->username());
        self::assertSame('banner.webp', $stored->dashboardImage());
        self::assertSame('wallpaper', $stored->dashboardImageMode());
        self::assertSame(70, $stored->dashboardOverlay());
        self::assertSame('sonic-blue',$stored->themePreset());
        self::assertSame('compact',$stored->layoutDensity());
    }
}
