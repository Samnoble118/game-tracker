<?php

declare(strict_types=1);

/**
 * Defines the lifecycle states available to games in a collection.
 */

namespace GameTracker\Domain\Enum;

/**
 * Represents a player's current relationship with a game.
 */
enum GameStatus: string
{
    case Backlog = 'backlog';
    case Playing = 'playing';
    case Completed = 'completed';
    case Paused = 'paused';
    case Abandoned = 'abandoned';
}
