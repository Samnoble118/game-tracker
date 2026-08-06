<?php

declare(strict_types=1);

/**
 * Implements game persistence using a local SQLite database.
 */

namespace GameTracker\Infrastructure\Persistence;

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Domain\Repository\GameRepository;
use PDO;

/**
 * Stores and hydrates game entities through PDO-backed SQLite queries.
 */
final readonly class SqliteGameRepository implements GameRepository
{
    /**
     * Accepts a PDO connection and ensures the games table exists.
     */
    public function __construct(private PDO $connection)
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS games (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                platform TEXT NOT NULL,
                collection_type TEXT NOT NULL DEFAULT \'owned\',
                status TEXT NOT NULL,
                progress INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $this->migrateCollectionType();
    }

    /**
     * Inserts new games and updates previously persisted games.
     */
    public function save(Game $game): void
    {
        if ($game->id() === null) {
            $statement = $this->connection->prepare(
                'INSERT INTO games (title, platform, collection_type, status, progress)
                 VALUES (:title, :platform, :collection_type, :status, :progress)'
            );
            $statement->execute($this->parameters($game));
            $game->assignId((int) $this->connection->lastInsertId());

            return;
        }

        $statement = $this->connection->prepare(
            'UPDATE games
             SET title = :title, platform = :platform, collection_type = :collection_type,
                 status = :status, progress = :progress
             WHERE id = :id'
        );
        $statement->execute([...$this->parameters($game), 'id' => $game->id()]);
    }

    /**
     * Returns every stored game ordered alphabetically by title.
     *
     * @return list<Game>
     */
    public function all(): array
    {
        $rows = $this->connection
            ->query('SELECT id, title, platform, collection_type, status, progress FROM games ORDER BY title')
            ->fetchAll();

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * Finds and hydrates one game by ID.
     */
    public function find(int $id): ?Game
    {
        $statement = $this->connection->prepare(
            'SELECT id, title, platform, collection_type, status, progress FROM games WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * Converts a game entity into named SQL parameters.
     *
     * @return array{title: string, platform: string, collection_type: string, status: string, progress: int}
     */
    private function parameters(Game $game): array
    {
        return [
            'title' => $game->title(),
            'platform' => $game->platform(),
            'collection_type' => $game->collectionType()->value,
            'status' => $game->status()->value,
            'progress' => $game->progress(),
        ];
    }

    /**
     * Reconstitutes a game entity from a database result row.
     *
     * @param array{id: int|string, title: string, platform: string, collection_type: string, status: string, progress: int|string} $row
     */
    private function hydrate(array $row): Game
    {
        return new Game(
            title: $row['title'],
            platform: $row['platform'],
            status: GameStatus::from($row['status']),
            progress: (int) $row['progress'],
            id: (int) $row['id'],
            collectionType: CollectionType::from($row['collection_type']),
        );
    }

    /**
     * Adds the collection column to databases created before wishlist support.
     */
    private function migrateCollectionType(): void
    {
        $columns = $this->connection->query('PRAGMA table_info(games)')->fetchAll();
        $columnNames = array_column($columns, 'name');

        if (!in_array('collection_type', $columnNames, true)) {
            $this->connection->exec(
                "ALTER TABLE games ADD COLUMN collection_type TEXT NOT NULL DEFAULT 'owned'"
            );
        }
    }
}
