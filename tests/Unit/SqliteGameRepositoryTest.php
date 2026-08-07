<?php

declare(strict_types=1);

/**
 * Verifies SQLite persistence and compatibility with existing databases.
 */

namespace GameTracker\Tests\Unit;

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Infrastructure\Persistence\SqliteGameRepository;
use GameTracker\Infrastructure\Persistence\SqliteTrophyRepository;
use GameTracker\Domain\Entity\Trophy;
use GameTracker\Domain\Enum\TrophyGrade;
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
        $repository->claimUnowned(1);
        $game = $repository->find(1, 1);

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
            1,
            collectionType: CollectionType::Wishlist,
        );

        $repository->save($game);
        $game->updateCoverImage('metroid.webp');
        $repository->save($game);
        $stored = $repository->find($game->id(), 1);

        self::assertNotNull($stored);
        self::assertSame(CollectionType::Wishlist, $stored->collectionType());
        self::assertSame('metroid.webp', $stored->coverImage());
    }

    /**
     * Confirms trophies are stored against their game with earned state intact.
     */
    public function test_it_persists_trophies_for_a_game(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $games = new SqliteGameRepository($connection);
        $game = new Game('Astro Bot', 'PS5', 1);
        $games->save($game);
        $trophies = new SqliteTrophyRepository($connection);
        $trophy = new Trophy($game->id(), 'A New Beginning', TrophyGrade::Bronze);
        $trophy->setEarned(true);

        $trophies->save($trophy);
        $stored = $trophies->forGame($game->id());

        self::assertCount(1, $stored);
        self::assertTrue($stored[0]->isEarned());
        self::assertSame(TrophyGrade::Bronze, $stored[0]->grade());
    }

    /**
     * Confirms repository queries never expose another user's games.
     */
    public function test_it_isolates_games_by_user(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $repository = new SqliteGameRepository($connection);
        $repository->save(new Game('Astro Bot', 'PS5', 1));
        $repository->save(new Game('Hades', 'PC', 2));

        self::assertCount(1, $repository->all(1));
        self::assertSame('Astro Bot', $repository->all(1)[0]->title());
        self::assertNull($repository->find(2, 1));
    }

    /** Confirms filtering, counting, and pagination are performed by the repository. */
    public function test_it_queries_a_bounded_filtered_page(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $repository = new SqliteGameRepository($connection);
        $repository->save(new Game('Astro Bot', 'PS5', 1, GameStatus::Completed, 100));
        $repository->save(new Game('God of War', 'PS4', 1, GameStatus::Playing, 25));
        $repository->save(new Game('Zelda', 'Nintendo Switch', 1, GameStatus::Completed, 100));
        $repository->save(new Game('Private Game', 'PS5', 2, GameStatus::Completed, 100));

        $page = $repository->page(1, 'completed', '', 'playstation', 'all', 1, 0);

        self::assertCount(1, $page);
        self::assertSame('Astro Bot', $page[0]->title());
        self::assertSame(1, $repository->count(1, 'completed', '', 'playstation', 'all'));
        self::assertSame(2, $repository->viewCounts(1)['completed']);
        self::assertSame(2, $repository->platformCounts(1, 'all')['playstation']);
    }
}
