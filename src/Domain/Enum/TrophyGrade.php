<?php

declare(strict_types=1);

/**
 * Defines the trophy grades supported by PlayStation games.
 */

namespace GameTracker\Domain\Enum;

/**
 * Represents the value category assigned to a trophy.
 */
enum TrophyGrade: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';
}
