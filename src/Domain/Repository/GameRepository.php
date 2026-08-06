<?php

declare(strict_types=1);

/**
 * Defines the persistence boundary for storing and retrieving games.
 */

namespace GameTracker\Domain\Repository;

use GameTracker\Domain\Entity\Game;

/**
 * Allows the domain to work with game storage without depending on a database.
 */
interface GameRepository
{
    /**
     * Creates or updates a game based on whether it already has an ID.
     */
    public function save(Game $game): void;

    /**
     * Returns every stored game in display order.
     *
     * @return list<Game>
     */
    public function all(): array;

    /**
     * Finds a game by ID or returns null when it does not exist.
     */
    public function find(int $id): ?Game;
}
