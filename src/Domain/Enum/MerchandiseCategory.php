<?php

declare(strict_types=1);

/**
 * Defines the supported types of physical merchandise.
 */

namespace GameTracker\Domain\Enum;

/** Represents a merchandise category shown in the physical collection. */
enum MerchandiseCategory: string
{
    case ActionFigure = 'action-figure';
    case Statue = 'statue';
    case PopVinyl = 'pop-vinyl';
    case Lego = 'lego';
    case Other = 'other';

    /** Returns the category label displayed in forms and filters. */
    public function label(): string
    {
        return match ($this) {
            self::ActionFigure => 'Action figures',
            self::Statue => 'Statues',
            self::PopVinyl => 'Pop Vinyls',
            self::Lego => 'LEGO',
            self::Other => 'Other',
        };
    }
}
