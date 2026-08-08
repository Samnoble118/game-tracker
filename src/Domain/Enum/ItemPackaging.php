<?php

declare(strict_types=1);

/** Defines packaging and physical-format states shared across collections. */

namespace GameTracker\Domain\Enum;

/** Describes how a game or collectible is packaged or held. */
enum ItemPackaging: string
{
    case Unspecified = 'unspecified';
    case Boxed = 'boxed';
    case Loose = 'loose';
    case Carded = 'carded';
    case Sealed = 'sealed';
    case Built = 'built';
    case Digital = 'digital';

    /** Returns the packaging label shown to collectors. */
    public function label(): string
    {
        return $this === self::Unspecified ? 'Not specified' : ucfirst($this->value);
    }
}
