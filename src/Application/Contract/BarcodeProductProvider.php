<?php

declare(strict_types=1);

/** Defines the future boundary for optional barcode catalogue APIs. */

namespace GameTracker\Application\Contract;

/** Allows product data providers to be added without coupling collection code to one API. */
interface BarcodeProductProvider
{
    /** Returns normalized product data or null when the barcode is unknown. @return array{name:string,brand:string,category:string}|null */
    public function lookup(string $barcode): ?array;
}
