<?php

declare(strict_types=1);

namespace GameTracker\Tests\Support;

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Repository\GameRepository;

final class InMemoryGameRepository implements GameRepository
{
    /** @var array<int, Game> */
    private array $games = [];

    public function save(Game $game): void
    {
        if ($game->id() === null) {
            $game->assignId(count($this->games) + 1);
        }

        $this->games[$game->id()] = $game;
    }

    public function all(): array
    {
        return array_values($this->games);
    }

    public function find(int $id): ?Game
    {
        return $this->games[$id] ?? null;
    }
}
