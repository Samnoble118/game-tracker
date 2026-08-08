<?php

declare(strict_types=1);

/** Contains one dated play-session record. */

namespace GameTracker\Domain\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

/** Records time, progress, and private notes for a game session. */
final readonly class PlayLog
{
    /** Creates a validated play log, optionally restored from persistence. */
    public function __construct(
        private int $gameId,
        private int $userId,
        private DateTimeImmutable $playedOn,
        private int $minutes,
        private int $progress,
        private string $notes = '',
        private ?int $id = null,
    ) {
        if ($gameId < 1 || $userId < 1) throw new InvalidArgumentException('A valid game and user are required.');
        if ($minutes < 0 || $minutes > 1440) throw new InvalidArgumentException('Session time must be between 0 and 1,440 minutes.');
        if ($progress < 0 || $progress > 100) throw new InvalidArgumentException('Progress must be between 0 and 100.');
        if (strlen($notes) > 2000) throw new InvalidArgumentException('Session notes cannot exceed 2,000 characters.');
    }

    /** Returns the persisted ID. */ public function id(): ?int { return $this->id; }
    /** Returns the related game ID. */ public function gameId(): int { return $this->gameId; }
    /** Returns the owning user ID. */ public function userId(): int { return $this->userId; }
    /** Returns the play date. */ public function playedOn(): DateTimeImmutable { return $this->playedOn; }
    /** Returns session duration in minutes. */ public function minutes(): int { return $this->minutes; }
    /** Returns the progress snapshot. */ public function progress(): int { return $this->progress; }
    /** Returns private session notes. */ public function notes(): string { return $this->notes; }
}
