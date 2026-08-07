<?php

declare(strict_types=1);

/**
 * Provides fast in-memory game persistence for isolated unit tests.
 */

namespace GameTracker\Tests\Support;

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;
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
    public function all(int $userId): array
    {
        return array_values(array_filter(
            $this->games,
            static fn (Game $game): bool => $game->userId() === $userId,
        ));
    }

    /** Returns a filtered page for tests exercising the scalable query contract. */
    public function page(int $userId, string $view, string $search, string $platform, string $status, int $limit, int $offset): array
    {
        return array_slice($this->filtered($userId, $view, $search, $status), $offset, $limit);
    }

    /** Counts in-memory games matching the supplied filters. */
    public function count(int $userId, string $view, string $search, string $platform, string $status): int
    {
        return count($this->filtered($userId, $view, $search, $status));
    }

    /** Returns primary collection counts for the test user. */
    public function viewCounts(int $userId): array
    {
        return [
            'all' => $this->count($userId, 'all', '', 'all', 'all'),
            'owned' => $this->count($userId, 'owned', '', 'all', 'all'),
            'wishlist' => $this->count($userId, 'wishlist', '', 'all', 'all'),
            'playing' => $this->count($userId, 'playing', '', 'all', 'all'),
            'completed' => $this->count($userId, 'completed', '', 'all', 'all'),
        ];
    }

    /** Returns minimal platform totals required by the repository contract. */
    public function platformCounts(int $userId, string $view): array
    {
        return ['all' => $this->count($userId, $view, '', 'all', 'all')];
    }

    /**
     * Finds an in-memory game by ID.
     */
    public function find(int $id, int $userId): ?Game
    {
        $game = $this->games[$id] ?? null;

        return $game?->userId() === $userId ? $game : null;
    }

    /**
     * Does nothing because test games always have explicit ownership.
     */
    public function claimUnowned(int $userId): void
    {
    }

    /** @return list<Game> */
    private function filtered(int $userId, string $view, string $search, string $status): array
    {
        return array_values(array_filter($this->all($userId), static fn (Game $game): bool =>
            match ($view) {
                'owned' => $game->collectionType() === CollectionType::Owned,
                'wishlist' => $game->collectionType() === CollectionType::Wishlist,
                'playing' => $game->status() === GameStatus::Playing,
                'completed' => $game->status() === GameStatus::Completed,
                default => true,
            }
            && ($search === '' || stripos($game->title(), $search) !== false || stripos($game->platform(), $search) !== false)
            && ($status === 'all' || $game->status()->value === $status)
        ));
    }
}
