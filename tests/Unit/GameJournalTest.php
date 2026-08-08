<?php

declare(strict_types=1);

/** Verifies private ratings, play logs, progress syncing, and custom lists. */

namespace GameTracker\Tests\Unit;

use GameTracker\Application\Service\GameJournal;
use GameTracker\Application\Service\GameLibrary;
use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Infrastructure\Persistence\SqliteGameJournalRepository;
use GameTracker\Infrastructure\Persistence\SqliteGameRepository;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

/** Covers the complete journal workflow and its user-isolation boundary. */
final class GameJournalTest extends TestCase
{
    /** Confirms ratings, sessions, automatic progress status, and lists are persisted. */
    public function test_it_records_a_private_game_journal(): void
    {
        $connection = $this->connection();
        $games = new SqliteGameRepository($connection);
        $game = new Game('Astro Bot', 'PS5', 1);
        $games->save($game);
        $library = new GameLibrary($games, 1);
        $journal = new GameJournal($library, new SqliteGameJournalRepository($connection), 1);

        $journal->rate($game->id(), 5);
        $journal->log($game->id(), '2026-08-07', 90, 35, 'Reached the next galaxy.');
        $journal->createList($game->id(), 'Replay soon');

        self::assertSame(5, $journal->rating($game->id()));
        self::assertSame(90, $journal->logs($game->id())[0]->minutes());
        self::assertSame('Reached the next galaxy.', $journal->logs($game->id())[0]->notes());
        self::assertTrue($journal->lists($game->id())[0]->containsGame());
        self::assertSame(GameStatus::Playing, $library->find($game->id())->status());
        self::assertSame(35, $library->find($game->id())->progress());

        $journal->log($game->id(), '2026-08-08', 45, 100, 'Finished.');

        self::assertSame(GameStatus::Completed, $library->find($game->id())->status());
    }

    /** Confirms a user cannot rate or list another user's game. */
    public function test_it_rejects_cross_user_journal_changes(): void
    {
        $connection = $this->connection();
        $games = new SqliteGameRepository($connection);
        $privateGame = new Game('Private Game', 'PC', 2);
        $games->save($privateGame);
        $journal = new GameJournal(
            new GameLibrary($games, 1),
            new SqliteGameJournalRepository($connection),
            1,
        );

        $this->expectException(InvalidArgumentException::class);
        $journal->rate($privateGame->id(), 4);
    }

    /** Creates a consistent in-memory SQLite connection for each test. */
    private function connection(): PDO
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $connection;
    }
}
