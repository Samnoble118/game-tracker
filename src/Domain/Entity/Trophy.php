<?php

declare(strict_types=1);

/**
 * Contains the trophy entity and its earned-state rules.
 */

namespace GameTracker\Domain\Entity;

use DateTimeImmutable;
use GameTracker\Domain\Enum\TrophyGrade;
use InvalidArgumentException;

/**
 * Represents one manually tracked trophy belonging to a game.
 */
final class Trophy
{
    /**
     * Creates a validated trophy, optionally restored from persistence.
     */
    public function __construct(
        private readonly int $gameId,
        private string $name,
        private TrophyGrade $grade,
        private bool $earned = false,
        private ?DateTimeImmutable $earnedAt = null,
        private ?int $id = null,
    ) {
        $this->rename($this->name);

        if (!$this->earned) {
            $this->earnedAt = null;
        }
    }

    /**
     * Returns the persisted identifier, or null before the first save.
     */
    public function id(): ?int
    {
        return $this->id;
    }

    /**
     * Returns the game to which this trophy belongs.
     */
    public function gameId(): int
    {
        return $this->gameId;
    }

    /**
     * Returns the trophy's display name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the bronze, silver, gold, or platinum grade.
     */
    public function grade(): TrophyGrade
    {
        return $this->grade;
    }

    /**
     * Reports whether the player has earned this trophy.
     */
    public function isEarned(): bool
    {
        return $this->earned;
    }

    /**
     * Returns when the trophy was marked earned, when available.
     */
    public function earnedAt(): ?DateTimeImmutable
    {
        return $this->earnedAt;
    }

    /**
     * Marks the trophy earned now, or clears its earned state.
     */
    public function setEarned(bool $earned): void
    {
        $this->earned = $earned;
        $this->earnedAt = $earned ? new DateTimeImmutable() : null;
    }

    /**
     * Changes the trophy name after validation.
     */
    public function rename(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('A trophy name is required.');
        }

        $this->name = $name;
    }

    /**
     * Assigns the database identifier once after the first save.
     */
    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new InvalidArgumentException('The trophy already has an ID.');
        }

        $this->id = $id;
    }
}
