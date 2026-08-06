<?php

declare(strict_types=1);

namespace GameTracker\Domain\Entity;

use GameTracker\Domain\Enum\GameStatus;
use InvalidArgumentException;

final class Game
{
    public function __construct(
        private string $title,
        private string $platform,
        private GameStatus $status = GameStatus::Backlog,
        private int $progress = 0,
        private ?int $id = null,
    ) {
        $this->updateDetails($this->title, $this->platform);
        $this->setProgress($this->progress);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function platform(): string
    {
        return $this->platform;
    }

    public function status(): GameStatus
    {
        return $this->status;
    }

    public function progress(): int
    {
        return $this->progress;
    }

    public function updateStatus(GameStatus $status): void
    {
        $this->status = $status;
    }

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

    public function setProgress(int $progress): void
    {
        if ($progress < 0 || $progress > 100) {
            throw new InvalidArgumentException('Progress must be between 0 and 100.');
        }

        $this->progress = $progress;
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new InvalidArgumentException('The game already has an ID.');
        }

        $this->id = $id;
    }
}
