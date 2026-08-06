<?php

declare(strict_types=1);

/**
 * Provides fast in-memory game persistence for isolated unit tests.
 */

namespace GameTracker\Tests\Support;

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Repository\GameRepository;

/**
 * Mimics repository behavior without opening a database connection.
 */
final class InMemoryGameRepository implements GameRepository
{
    /** @var array<int, Game> */
    private array $games = [];

    /**
     * Stores a game and assigns an ID when it is new.
     */
    public function save(Game $game): void
    {
        if ($game->id() === null) {
            $game->assignId(count($this->games) + 1);
        }

        $this->games[$game->id()] = $game;
    }

    /**
     * Returns every game currently held in memory.
     *
     * @return list<Game>
     */
    public function all(): array
    {
        return array_values($this->games);
    }

    /**
     * Finds an in-memory game by ID.
     */
    public function find(int $id): ?Game
    {
        return $this->games[$id] ?? null;
    }
}
