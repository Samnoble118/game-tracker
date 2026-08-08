<?php

declare(strict_types=1);

/** Supplies the no-external-API barcode provider used by default. */

namespace GameTracker\Infrastructure\Barcode;

use GameTracker\Application\Contract\BarcodeProductProvider;

/** Keeps manual and camera barcode capture functional without API credentials. */
final readonly class NullBarcodeProductProvider implements BarcodeProductProvider
{
    /** Returns no catalogue match because external lookup is disabled. */
    public function lookup(string $barcode): ?array
    {
        return null;
    }
}
