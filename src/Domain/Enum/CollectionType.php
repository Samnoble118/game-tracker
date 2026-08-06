<?php

declare(strict_types=1);

/**
 * Defines whether a game is owned or saved for future purchase.
 */

namespace GameTracker\Domain\Enum;

/**
 * Represents the library section in which a game belongs.
 */
enum CollectionType: string
{
    case Owned = 'owned';
    case Wishlist = 'wishlist';
}
