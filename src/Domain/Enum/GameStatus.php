<?php

declare(strict_types=1);

namespace GameTracker\Domain\Enum;

enum GameStatus: string
{
    case Backlog = 'backlog';
    case Playing = 'playing';
    case Completed = 'completed';
    case Paused = 'paused';
    case Abandoned = 'abandoned';
}
