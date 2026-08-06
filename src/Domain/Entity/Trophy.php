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

    public function id(): ?int
    {
        return $this->id;
    }

    public function gameId(): int
    {
        return $this->gameId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function grade(): TrophyGrade
    {
        return $this->grade;
    }

    public function isEarned(): bool
    {
        return $this->earned;
    }

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
