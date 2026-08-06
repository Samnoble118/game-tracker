<?php

declare(strict_types=1);

/**
 * Defines the persistence boundary for manually tracked trophies.
 */

namespace GameTracker\Domain\Repository;

use GameTracker\Domain\Entity\Trophy;

interface TrophyRepository
{
    public function save(Trophy $trophy): void;

    /** @return list<Trophy> */
    public function forGame(int $gameId): array;

    public function find(int $id): ?Trophy;
}
