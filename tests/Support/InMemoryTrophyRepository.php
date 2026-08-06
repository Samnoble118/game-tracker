<?php

declare(strict_types=1);

/**
 * Provides fast in-memory trophy persistence for isolated unit tests.
 */

namespace GameTracker\Tests\Support;

use GameTracker\Domain\Entity\Trophy;
use GameTracker\Domain\Repository\TrophyRepository;

/**
 * Mimics trophy repository behavior without opening a database connection.
 */
final class InMemoryTrophyRepository implements TrophyRepository
{
    /** @var array<int, Trophy> */
    private array $trophies = [];

    /**
     * Stores a trophy and assigns an ID when it is new.
     */
    public function save(Trophy $trophy): void
    {
        if ($trophy->id() === null) {
            $trophy->assignId(count($this->trophies) + 1);
        }

        $this->trophies[$trophy->id()] = $trophy;
    }

    /**
     * Returns trophies belonging to one game.
     *
     * @return list<Trophy>
     */
    public function forGame(int $gameId): array
    {
        return array_values(array_filter(
            $this->trophies,
            static fn (Trophy $trophy): bool => $trophy->gameId() === $gameId,
        ));
    }

    /**
     * Finds an in-memory trophy by ID.
     */
    public function find(int $id): ?Trophy
    {
        return $this->trophies[$id] ?? null;
    }
}
