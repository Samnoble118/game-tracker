<?php

declare(strict_types=1);

namespace GameTracker\Tests\Support;

use GameTracker\Domain\Entity\Trophy;
use GameTracker\Domain\Repository\TrophyRepository;

final class InMemoryTrophyRepository implements TrophyRepository
{
    /** @var array<int, Trophy> */
    private array $trophies = [];

    public function save(Trophy $trophy): void
    {
        if ($trophy->id() === null) {
            $trophy->assignId(count($this->trophies) + 1);
        }

        $this->trophies[$trophy->id()] = $trophy;
    }

    public function forGame(int $gameId): array
    {
        return array_values(array_filter(
            $this->trophies,
            static fn (Trophy $trophy): bool => $trophy->gameId() === $gameId,
        ));
    }

    public function find(int $id): ?Trophy
    {
        return $this->trophies[$id] ?? null;
    }
}
