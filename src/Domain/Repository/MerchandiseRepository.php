<?php

declare(strict_types=1);

/**
 * Defines persistence operations for physical merchandise.
 */

namespace GameTracker\Domain\Repository;

use GameTracker\Domain\Entity\MerchandiseItem;

/** Allows merchandise use cases to remain independent from SQLite. */
interface MerchandiseRepository
{
    /** Creates or updates a merchandise item. */
    public function save(MerchandiseItem $item): void;

    /** Returns every merchandise item belonging to a user. @return list<MerchandiseItem> */
    public function all(int $userId): array;

    /** Finds a merchandise item by ID for the supplied user. */
    public function find(int $id, int $userId): ?MerchandiseItem;
}
