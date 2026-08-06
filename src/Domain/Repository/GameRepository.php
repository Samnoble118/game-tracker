<?php

declare(strict_types=1);

namespace GameTracker\Domain\Repository;

use GameTracker\Domain\Entity\Game;

interface GameRepository
{
    public function save(Game $game): void;

    /** @return list<Game> */
    public function all(): array;

    public function find(int $id): ?Game;
}
