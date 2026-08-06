<?php

declare(strict_types=1);

/**
 * Implements manual trophy persistence using SQLite.
 */

namespace GameTracker\Infrastructure\Persistence;

use DateTimeImmutable;
use GameTracker\Domain\Entity\Trophy;
use GameTracker\Domain\Enum\TrophyGrade;
use GameTracker\Domain\Repository\TrophyRepository;
use PDO;

final readonly class SqliteTrophyRepository implements TrophyRepository
{
    public function __construct(private PDO $connection)
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS trophies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                grade TEXT NOT NULL,
                earned INTEGER NOT NULL DEFAULT 0,
                earned_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
            )'
        );
    }

    public function save(Trophy $trophy): void
    {
        $parameters = [
            'game_id' => $trophy->gameId(),
            'name' => $trophy->name(),
            'grade' => $trophy->grade()->value,
            'earned' => $trophy->isEarned() ? 1 : 0,
            'earned_at' => $trophy->earnedAt()?->format(DATE_ATOM),
        ];

        if ($trophy->id() === null) {
            $statement = $this->connection->prepare(
                'INSERT INTO trophies (game_id, name, grade, earned, earned_at)
                 VALUES (:game_id, :name, :grade, :earned, :earned_at)'
            );
            $statement->execute($parameters);
            $trophy->assignId((int) $this->connection->lastInsertId());
            return;
        }

        $statement = $this->connection->prepare(
            'UPDATE trophies
             SET name = :name, grade = :grade, earned = :earned, earned_at = :earned_at
             WHERE id = :id AND game_id = :game_id'
        );
        $statement->execute([...$parameters, 'id' => $trophy->id()]);
    }

    public function forGame(int $gameId): array
    {
        $statement = $this->connection->prepare(
            "SELECT id, game_id, name, grade, earned, earned_at
             FROM trophies
             WHERE game_id = :game_id
             ORDER BY CASE grade
                WHEN 'platinum' THEN 1 WHEN 'gold' THEN 2
                WHEN 'silver' THEN 3 ELSE 4 END, name"
        );
        $statement->execute(['game_id' => $gameId]);

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    public function find(int $id): ?Trophy
    {
        $statement = $this->connection->prepare(
            'SELECT id, game_id, name, grade, earned, earned_at FROM trophies WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /** @param array<string, int|string|null> $row */
    private function hydrate(array $row): Trophy
    {
        return new Trophy(
            gameId: (int) $row['game_id'],
            name: (string) $row['name'],
            grade: TrophyGrade::from((string) $row['grade']),
            earned: (bool) $row['earned'],
            earnedAt: $row['earned_at'] === null ? null : new DateTimeImmutable((string) $row['earned_at']),
            id: (int) $row['id'],
        );
    }
}
