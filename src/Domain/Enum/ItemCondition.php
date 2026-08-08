<?php

declare(strict_types=1);

/** Defines supported collector condition grades. */

namespace GameTracker\Domain\Enum;

/** Describes the physical condition of a collection item. */
enum ItemCondition: string
{
    case Unspecified = 'unspecified';
    case New = 'new';
    case LikeNew = 'like-new';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';

    /** Returns the condition label shown in forms. */
    public function label(): string
    {
        return match ($this) {
            self::LikeNew => 'Like new',
            self::Unspecified => 'Not specified',
            default => ucfirst($this->value),
        };
    }
}
