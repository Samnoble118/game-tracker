<?php

declare(strict_types=1);

/** Defines persistence for shared collection metadata. */

namespace GameTracker\Domain\Repository;

use GameTracker\Domain\Entity\CollectionMetadata;
use GameTracker\Domain\Enum\CollectionItemType;

/** Keeps collection-detail use cases independent from SQLite. */
interface CollectionMetadataRepository
{
    /** Saves metadata only when its collection record belongs to the user. */
    public function save(CollectionMetadata $metadata): void;

    /** Finds user-scoped metadata for one collection record. */
    public function find(CollectionItemType $type, int $itemId, int $userId): ?CollectionMetadata;

    /** Returns metadata records sharing a barcode for one user. @return list<CollectionMetadata> */
    public function findByBarcode(string $barcode, int $userId): array;

    /** Returns every metadata record belonging to one user. @return list<CollectionMetadata> */
    public function all(int $userId): array;
}
