<?php

declare(strict_types=1);

/**
 * Defines the supported packaging and display states for merchandise.
 */

namespace GameTracker\Domain\Enum;

/** Represents how a physical collectible is packaged or displayed. */
enum MerchandisePackaging: string
{
    case Boxed = 'boxed';
    case Carded = 'carded';
    case Loose = 'loose';
    case Built = 'built';
    case Sealed = 'sealed';

    /** Returns the packaging label displayed in forms and filters. */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
