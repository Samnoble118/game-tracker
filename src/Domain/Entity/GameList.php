<?php

declare(strict_types=1);

/** Contains a user-created game list. */

namespace GameTracker\Domain\Entity;

use InvalidArgumentException;

/** Represents a named list and whether the current game belongs to it. */
final readonly class GameList
{
    /** Creates a validated custom list. */
    public function __construct(private int $id, private int $userId, private string $name, private bool $containsGame = false)
    {
        if ($id < 1 || $userId < 1 || trim($name) === '') throw new InvalidArgumentException('A valid custom list is required.');
    }

    /** Returns the list ID. */ public function id(): int { return $this->id; }
    /** Returns the owner ID. */ public function userId(): int { return $this->userId; }
    /** Returns the list name. */ public function name(): string { return $this->name; }
    /** Reports whether the selected game belongs to the list. */ public function containsGame(): bool { return $this->containsGame; }
}
