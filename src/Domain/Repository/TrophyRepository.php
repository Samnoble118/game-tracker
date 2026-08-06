<?php

declare(strict_types=1);

/**
 * Defines the persistence boundary for manually tracked trophies.
 */

namespace GameTracker\Domain\Repository;

use GameTracker\Domain\Entity\Trophy;

interface TrophyRepository
{
    /**
     * Creates or updates a trophy based on whether it has an ID.
     */
    public function save(Trophy $trophy): void;

    /**
     * Returns every trophy belonging to one game.
     *
     * @return list<Trophy>
     */
    public function forGame(int $gameId): array;

    /**
     * Finds a trophy by ID or returns null when it does not exist.
     */
    public function find(int $id): ?Trophy;
}
