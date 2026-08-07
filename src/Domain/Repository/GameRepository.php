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
    public function all(int $userId): array;

    /**
     * Returns one bounded page matching the dashboard filters.
     *
     * @return list<Game>
     */
    public function page(
        int $userId,
        string $view,
        string $search,
        string $platform,
        string $status,
        int $limit,
        int $offset,
    ): array;

    /** Counts games matching the supplied dashboard filters. */
    public function count(int $userId, string $view, string $search, string $platform, string $status): int;

    /** @return array{all: int, owned: int, wishlist: int, playing: int, completed: int} */
    public function viewCounts(int $userId): array;

    /** @return array<string, int> */
    public function platformCounts(int $userId, string $view): array;

    /**
     * Finds a game by ID or returns null when it does not exist.
     */
    public function find(int $id, int $userId): ?Game;

    /**
     * Assigns legacy unowned games to the first registered account.
     */
    public function claimUnowned(int $userId): void;
}
