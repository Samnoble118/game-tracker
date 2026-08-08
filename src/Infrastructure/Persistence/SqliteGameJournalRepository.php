<?php

declare(strict_types=1);

/** Implements private game journals with SQLite. */

namespace GameTracker\Infrastructure\Persistence;

use DateTimeImmutable;
use GameTracker\Domain\Entity\GameList;
use GameTracker\Domain\Entity\PlayLog;
use GameTracker\Domain\Repository\GameJournalRepository;
use InvalidArgumentException;
use PDO;

/** Stores ratings, sessions, and list membership with user isolation. */
final readonly class SqliteGameJournalRepository implements GameJournalRepository
{
    /** Creates journal tables and lookup indexes. */
    public function __construct(private PDO $connection)
    {
        $this->connection->exec('CREATE TABLE IF NOT EXISTS game_ratings (game_id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5), FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE CASCADE)');
        $this->connection->exec('CREATE TABLE IF NOT EXISTS play_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, game_id INTEGER NOT NULL, user_id INTEGER NOT NULL, played_on TEXT NOT NULL, minutes INTEGER NOT NULL DEFAULT 0, progress INTEGER NOT NULL, notes TEXT NOT NULL DEFAULT \'\', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE CASCADE)');
        $this->connection->exec('CREATE TABLE IF NOT EXISTS game_lists (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, name TEXT NOT NULL COLLATE NOCASE, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, name))');
        $this->connection->exec('CREATE TABLE IF NOT EXISTS game_list_items (list_id INTEGER NOT NULL, game_id INTEGER NOT NULL, PRIMARY KEY(list_id, game_id), FOREIGN KEY(list_id) REFERENCES game_lists(id) ON DELETE CASCADE, FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE CASCADE)');
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_play_logs_user_game_date ON play_logs(user_id, game_id, played_on DESC)');
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_game_lists_user_name ON game_lists(user_id, name)');
    }

    /** Returns the rating only when both game and rating belong to the user. */
    public function rating(int $gameId, int $userId): ?int
    {
        $statement = $this->connection->prepare('SELECT rating FROM game_ratings WHERE game_id=:game_id AND user_id=:user_id');
        $statement->execute(['game_id' => $gameId, 'user_id' => $userId]);
        $rating = $statement->fetchColumn();
        return $rating === false ? null : (int) $rating;
    }

    /** Upserts a rating after verifying ownership in SQL. */
    public function saveRating(int $gameId, int $userId, int $rating): void
    {
        $statement = $this->connection->prepare('INSERT INTO game_ratings(game_id,user_id,rating) SELECT id,user_id,:rating FROM games WHERE id=:game_id AND user_id=:user_id AND 1=1 ON CONFLICT(game_id) DO UPDATE SET rating=excluded.rating, user_id=excluded.user_id');
        $statement->execute(['rating' => $rating, 'game_id' => $gameId, 'user_id' => $userId]);
        if ($statement->rowCount() !== 1) throw new InvalidArgumentException('That game could not be found.');
    }

    /** Inserts a play log only for a game owned by the same user. */
    public function addLog(PlayLog $log): void
    {
        $statement = $this->connection->prepare('INSERT INTO play_logs(game_id,user_id,played_on,minutes,progress,notes) SELECT id,user_id,:played_on,:minutes,:progress,:notes FROM games WHERE id=:game_id AND user_id=:user_id');
        $statement->execute(['played_on'=>$log->playedOn()->format('Y-m-d'),'minutes'=>$log->minutes(),'progress'=>$log->progress(),'notes'=>$log->notes(),'game_id'=>$log->gameId(),'user_id'=>$log->userId()]);
        if ($statement->rowCount() !== 1) throw new InvalidArgumentException('That game could not be found.');
    }

    /** Hydrates the user's session history. */
    public function logs(int $gameId, int $userId): array
    {
        $statement = $this->connection->prepare('SELECT id,game_id,user_id,played_on,minutes,progress,notes FROM play_logs WHERE game_id=:game_id AND user_id=:user_id ORDER BY played_on DESC,id DESC');
        $statement->execute(['game_id'=>$gameId,'user_id'=>$userId]);
        return array_map(static fn(array $row): PlayLog => new PlayLog((int)$row['game_id'],(int)$row['user_id'],new DateTimeImmutable($row['played_on']),(int)$row['minutes'],(int)$row['progress'],(string)$row['notes'],(int)$row['id']), $statement->fetchAll());
    }

    /** Creates a list or returns the matching existing list ID. */
    public function createList(int $userId, string $name): int
    {
        $statement = $this->connection->prepare('INSERT INTO game_lists(user_id,name) VALUES(:user_id,:name) ON CONFLICT(user_id,name) DO NOTHING');
        $statement->execute(['user_id'=>$userId,'name'=>$name]);
        $find = $this->connection->prepare('SELECT id FROM game_lists WHERE user_id=:user_id AND name=:name COLLATE NOCASE');
        $find->execute(['user_id'=>$userId,'name'=>$name]);
        return (int) $find->fetchColumn();
    }

    /** Returns all user lists with membership for one game. */
    public function lists(int $gameId, int $userId): array
    {
        $statement = $this->connection->prepare('SELECT l.id,l.user_id,l.name,CASE WHEN i.game_id IS NULL THEN 0 ELSE 1 END AS contains_game FROM game_lists l LEFT JOIN game_list_items i ON i.list_id=l.id AND i.game_id=:game_id WHERE l.user_id=:user_id ORDER BY l.name');
        $statement->execute(['game_id'=>$gameId,'user_id'=>$userId]);
        return array_map(static fn(array $row): GameList => new GameList((int)$row['id'],(int)$row['user_id'],(string)$row['name'],(bool)$row['contains_game']), $statement->fetchAll());
    }

    /** Changes list membership only when both list and game belong to the user. */
    public function setListMembership(int $listId, int $gameId, int $userId, bool $included): void
    {
        if ($included) {
            $statement = $this->connection->prepare('INSERT OR IGNORE INTO game_list_items(list_id,game_id) SELECT l.id,g.id FROM game_lists l JOIN games g ON g.id=:game_id AND g.user_id=:user_id WHERE l.id=:list_id AND l.user_id=:user_id');
        } else {
            $statement = $this->connection->prepare('DELETE FROM game_list_items WHERE list_id IN (SELECT id FROM game_lists WHERE id=:list_id AND user_id=:user_id) AND game_id IN (SELECT id FROM games WHERE id=:game_id AND user_id=:user_id)');
        }
        $statement->execute(['list_id'=>$listId,'game_id'=>$gameId,'user_id'=>$userId]);
    }
}
