<?php

declare(strict_types=1);

/**
 * Verifies SQLite persistence and compatibility with existing databases.
 */

namespace GameTracker\Tests\Unit;

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Infrastructure\Persistence\SqliteGameRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Covers collection persistence and the wishlist schema migration.
 */
final class SqliteGameRepositoryTest extends TestCase
{
    /**
     * Confirms older game tables gain the collection column without losing data.
     */
    public function test_it_migrates_existing_games_to_the_owned_collection(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->exec(
            'CREATE TABLE games (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                platform TEXT NOT NULL,
                status TEXT NOT NULL,
                progress INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $connection->exec(
            "INSERT INTO games (title, platform, status, progress)
             VALUES ('Hades', 'PC', 'completed', 100)"
        );

        $repository = new SqliteGameRepository($connection);
        $game = $repository->find(1);

        self::assertNotNull($game);
        self::assertSame(CollectionType::Owned, $game->collectionType());
        self::assertSame('Hades', $game->title());
    }

    /**
     * Confirms wishlist selection is stored and restored.
     */
    public function test_it_persists_a_wishlist_game(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $repository = new SqliteGameRepository($connection);
        $game = new Game(
            'Metroid Prime 4',
            'Nintendo Switch 2',
            collectionType: CollectionType::Wishlist,
        );

        $repository->save($game);
        $stored = $repository->find($game->id());

        self::assertNotNull($stored);
        self::assertSame(CollectionType::Wishlist, $stored->collectionType());
    }
}
