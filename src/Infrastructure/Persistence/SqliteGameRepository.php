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
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                platform TEXT NOT NULL,
                collection_type TEXT NOT NULL DEFAULT \'owned\',
                status TEXT NOT NULL,
                progress INTEGER NOT NULL DEFAULT 0,
                cover_image TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $this->migrateUserId();
        $this->migrateCollectionType();
        $this->migrateCoverImage();
        $this->createIndexes();
    }

    /**
     * Inserts new games and updates previously persisted games.
     */
    public function save(Game $game): void
    {
        if ($game->id() === null) {
            $statement = $this->connection->prepare(
                'INSERT INTO games (user_id, title, platform, collection_type, status, progress, cover_image)
                 VALUES (:user_id, :title, :platform, :collection_type, :status, :progress, :cover_image)'
            );
            $statement->execute($this->parameters($game));
            $game->assignId((int) $this->connection->lastInsertId());

            return;
        }

        $statement = $this->connection->prepare(
            'UPDATE games
             SET title = :title, platform = :platform, collection_type = :collection_type,
                 status = :status, progress = :progress, cover_image = :cover_image
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([...$this->parameters($game), 'id' => $game->id()]);
    }

    /**
     * Returns every stored game ordered alphabetically by title.
     *
     * @return list<Game>
     */
    public function all(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, user_id, title, platform, collection_type, status, progress, cover_image
             FROM games WHERE user_id = :user_id ORDER BY title'
        );
        $statement->execute(['user_id' => $userId]);

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /** Returns a filtered and bounded page directly from SQLite. */
    public function page(int $userId, string $view, string $search, string $platform, string $status, int $limit, int $offset): array
    {
        [$where, $parameters] = $this->filters($userId, $view, $search, $platform, $status);
        $statement = $this->connection->prepare(
            "SELECT id, user_id, title, platform, collection_type, status, progress, cover_image
             FROM games WHERE {$where} ORDER BY title LIMIT :limit OFFSET :offset"
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /** Counts records matching the same filters used by page(). */
    public function count(int $userId, string $view, string $search, string $platform, string $status): int
    {
        [$where, $parameters] = $this->filters($userId, $view, $search, $platform, $status);
        $statement = $this->connection->prepare("SELECT COUNT(*) FROM games WHERE {$where}");
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /** Returns all primary dashboard counts in one aggregate query. */
    public function viewCounts(int $userId): array
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(*) AS all_count,
                    SUM(collection_type = 'owned') AS owned_count,
                    SUM(collection_type = 'wishlist') AS wishlist_count,
                    SUM(status = 'playing') AS playing_count,
                    SUM(status = 'completed') AS completed_count
             FROM games WHERE user_id = :user_id"
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        return [
            'all' => (int) $row['all_count'],
            'owned' => (int) $row['owned_count'],
            'wishlist' => (int) $row['wishlist_count'],
            'playing' => (int) $row['playing_count'],
            'completed' => (int) $row['completed_count'],
        ];
    }

    /** Returns platform-family counts without hydrating game entities. */
    public function platformCounts(int $userId, string $view): array
    {
        [$viewWhere, $parameters] = $this->filters($userId, $view, '', 'all', 'all');
        $counts = ['all' => $this->scalarCount($viewWhere, $parameters)];
        foreach (['playstation', 'nintendo', 'sega', 'xbox', 'pc', 'mobile', 'other'] as $platform) {
            $counts[$platform] = $this->scalarCount(
                $viewWhere . ' AND ' . $this->platformCondition($platform),
                $parameters,
            );
        }

        return $counts;
    }

    /**
     * Finds and hydrates one game by ID.
     */
    public function find(int $id, int $userId): ?Game
    {
        $statement = $this->connection->prepare(
            'SELECT id, user_id, title, platform, collection_type, status, progress, cover_image
             FROM games WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * Converts a game entity into named SQL parameters.
     *
     * @return array{user_id: int, title: string, platform: string, collection_type: string, status: string, progress: int}
     */
    private function parameters(Game $game): array
    {
        return [
            'user_id' => $game->userId(),
            'title' => $game->title(),
            'platform' => $game->platform(),
            'collection_type' => $game->collectionType()->value,
            'status' => $game->status()->value,
            'progress' => $game->progress(),
            'cover_image' => $game->coverImage(),
        ];
    }

    /**
     * Reconstitutes a game entity from a database result row.
     *
     * @param array{id: int|string, user_id: int|string, title: string, platform: string, collection_type: string, status: string, progress: int|string} $row
     */
    private function hydrate(array $row): Game
    {
        return new Game(
            title: $row['title'],
            platform: $row['platform'],
            userId: (int) $row['user_id'],
            status: GameStatus::from($row['status']),
            progress: (int) $row['progress'],
            id: (int) $row['id'],
            collectionType: CollectionType::from($row['collection_type']),
            coverImage: $row['cover_image'] === null ? null : (string) $row['cover_image'],
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

    /**
     * Adds nullable ownership to databases created before user accounts.
     */
    private function migrateUserId(): void
    {
        $columns = $this->connection->query('PRAGMA table_info(games)')->fetchAll();
        $columnNames = array_column($columns, 'name');

        if (!in_array('user_id', $columnNames, true)) {
            $this->connection->exec('ALTER TABLE games ADD COLUMN user_id INTEGER NULL');
        }
    }

    /** Adds private cover-image storage to databases created before cover support. */
    private function migrateCoverImage(): void
    {
        $columns = $this->connection->query('PRAGMA table_info(games)')->fetchAll();

        if (!in_array('cover_image', array_column($columns, 'name'), true)) {
            $this->connection->exec('ALTER TABLE games ADD COLUMN cover_image TEXT NULL');
        }
    }

    /** Assigns legacy games without an owner to the supplied user. */
    public function claimUnowned(int $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE games SET user_id = :user_id WHERE user_id IS NULL'
        );
        $statement->execute(['user_id' => $userId]);
    }

    /** Creates indexes used by user isolation, filtering, and ordering. */
    private function createIndexes(): void
    {
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_games_user_title ON games (user_id, title)');
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_games_user_collection ON games (user_id, collection_type)');
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_games_user_status ON games (user_id, status)');
    }

    /** @return array{string, array<string, int|string>} */
    private function filters(int $userId, string $view, string $search, string $platform, string $status): array
    {
        $conditions = ['user_id = :user_id'];
        $parameters = ['user_id' => $userId];
        $conditions[] = match ($view) {
            'owned' => "collection_type = 'owned'",
            'wishlist' => "collection_type = 'wishlist'",
            'playing' => "status = 'playing'",
            'completed' => "status = 'completed'",
            default => '1 = 1',
        };
        if ($search !== '') {
            $conditions[] = "(title LIKE :search ESCAPE '\\' OR platform LIKE :search ESCAPE '\\')";
            $parameters['search'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        }
        if ($platform !== 'all') {
            $conditions[] = $this->platformCondition($platform);
        }
        if ($status !== 'all') {
            $conditions[] = 'status = :status';
            $parameters['status'] = $status;
        }

        return [implode(' AND ', $conditions), $parameters];
    }

    /** Returns the SQL expression for a dashboard platform family. */
    private function platformCondition(string $group): string
    {
        $value = 'lower(platform)';
        $known = [
            'playstation' => "({$value} LIKE '%playstation%' OR {$value} LIKE '%ps%')",
            'nintendo' => "({$value} LIKE '%switch%' OR {$value} LIKE '%nintendo%' OR {$value} LIKE '%gamecube%' OR {$value} LIKE '%gameboy%' OR {$value} LIKE '%gba%' OR {$value} LIKE '%wii%' OR {$value} LIKE '%3ds%' OR {$value} LIKE '%ds%')",
            'sega' => "({$value} LIKE '%sega%' OR {$value} LIKE '%dreamcast%' OR {$value} LIKE '%saturn%' OR {$value} LIKE '%mega drive%' OR {$value} LIKE '%game gear%')",
            'xbox' => "{$value} LIKE '%xbox%'",
            'pc' => "({$value} = 'pc' OR {$value} LIKE '%steam%')",
            'mobile' => "({$value} LIKE '%ios%' OR {$value} LIKE '%android%' OR {$value} LIKE '%mobile%')",
        ];
        if (isset($known[$group])) {
            return $known[$group];
        }

        return 'NOT (' . implode(' OR ', $known) . ')';
    }

    /** Executes a lightweight count for a generated filter expression. */
    private function scalarCount(string $where, array $parameters): int
    {
        $statement = $this->connection->prepare("SELECT COUNT(*) FROM games WHERE {$where}");
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }
}
