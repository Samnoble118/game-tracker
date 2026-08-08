<?php

declare(strict_types=1);

/** Defines collection record types supported by shared metadata. */

namespace GameTracker\Domain\Enum;

/** Identifies whether metadata belongs to a game or merchandise item. */
enum CollectionItemType: string
{
    case Game = 'game';
    case Merchandise = 'merchandise';

    /** Returns the human-readable record type. */
    public function label(): string
    {
        return $this === self::Game ? 'Game' : 'Merchandise';
    }
}
