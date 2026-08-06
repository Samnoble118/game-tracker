<?php

declare(strict_types=1);

/**
 * Provides application-level use cases for managing a game collection.
 */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Domain\Repository\GameRepository;

/**
 * Coordinates game entities and persistence for collection operations.
 */
final readonly class GameLibrary
{
    /**
     * Creates the service with a replaceable game repository implementation.
     */
    public function __construct(private GameRepository $games, private int $userId)
    {
    }

    /**
     * Adds a new game to the collection and returns its persisted entity.
     */
    public function add(
        string $title,
        string $platform,
        GameStatus $status = GameStatus::Backlog,
        int $progress = 0,
        CollectionType $collectionType = CollectionType::Owned,
    ): Game
    {
        $game = new Game($title, $platform, $this->userId, $status, $progress, collectionType: $collectionType);
        $this->games->save($game);

        return $game;
    }

    /**
     * Updates only the play status and progress for an existing game.
     */
    public function updateProgress(int $id, GameStatus $status, int $progress): ?Game
    {
        $game = $this->games->find($id, $this->userId);

        if ($game === null) {
            return null;
        }

        $game->updateStatus($status);
        $game->setProgress($progress);
        $this->games->save($game);

        return $game;
    }

    /**
     * Updates all editable details for an existing game.
     */
    public function update(
        int $id,
        string $title,
        string $platform,
        GameStatus $status,
        int $progress,
        CollectionType $collectionType = CollectionType::Owned,
    ): ?Game {
        $game = $this->games->find($id, $this->userId);

        if ($game === null) {
            return null;
        }

        $game->updateDetails($title, $platform);
        $game->updateStatus($status);
        $game->setProgress($progress);
        $game->updateCollectionType($collectionType);
        $this->games->save($game);

        return $game;
    }

    /**
     * Finds a single game for viewing or editing.
     */
    public function find(int $id): ?Game
    {
        return $this->games->find($id, $this->userId);
    }

    /**
     * Returns the complete game collection.
     *
     * @return list<Game>
     */
    public function collection(): array
    {
        return $this->games->all($this->userId);
    }
}
