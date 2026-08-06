<?php

declare(strict_types=1);

/**
 * Contains the core game entity and its collection-related business rules.
 */

namespace GameTracker\Domain\Entity;

use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Domain\Enum\CollectionType;
use InvalidArgumentException;

/**
 * Represents one tracked game, including its collection, platform, status, and progress.
 */
final class Game
{
    /**
     * Creates a validated game entity, optionally restored with a persisted ID.
     */
    public function __construct(
        private string $title,
        private string $platform,
        private GameStatus $status = GameStatus::Backlog,
        private int $progress = 0,
        private ?int $id = null,
        private CollectionType $collectionType = CollectionType::Owned,
    ) {
        $this->updateDetails($this->title, $this->platform);
        $this->setProgress($this->progress);
    }

    /**
     * Returns the persisted identifier, or null before the first save.
     */
    public function id(): ?int
    {
        return $this->id;
    }

    /**
     * Returns the game's display title.
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Returns the platform on which the game is owned.
     */
    public function platform(): string
    {
        return $this->platform;
    }

    /**
     * Returns the game's current play status.
     */
    public function status(): GameStatus
    {
        return $this->status;
    }

    /**
     * Returns completion progress as a percentage from 0 to 100.
     */
    public function progress(): int
    {
        return $this->progress;
    }

    /**
     * Returns whether the game is owned or on the wishlist.
     */
    public function collectionType(): CollectionType
    {
        return $this->collectionType;
    }

    /**
     * Reports whether the platform can have PlayStation trophies.
     */
    public function supportsTrophies(): bool
    {
        return preg_match('/playstation|\bps[345]\b/i', $this->platform) === 1;
    }

    /**
     * Changes the current play status.
     */
    public function updateStatus(GameStatus $status): void
    {
        $this->status = $status;
    }

    /**
     * Moves the game between the owned collection and wishlist.
     */
    public function updateCollectionType(CollectionType $collectionType): void
    {
        $this->collectionType = $collectionType;
    }

    /**
     * Changes the title and platform after validating both values.
     */
    public function updateDetails(string $title, string $platform): void
    {
        $title = trim($title);
        $platform = trim($platform);

        if ($title === '') {
            throw new InvalidArgumentException('A game title is required.');
        }

        if ($platform === '') {
            throw new InvalidArgumentException('A platform is required.');
        }

        $this->title = $title;
        $this->platform = $platform;
    }

    /**
     * Changes completion progress, enforcing the valid percentage range.
     */
    public function setProgress(int $progress): void
    {
        if ($progress < 0 || $progress > 100) {
            throw new InvalidArgumentException('Progress must be between 0 and 100.');
        }

        $this->progress = $progress;
    }

    /**
     * Assigns the database identifier once after the entity is first saved.
     */
    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new InvalidArgumentException('The game already has an ID.');
        }

        $this->id = $id;
    }
}
