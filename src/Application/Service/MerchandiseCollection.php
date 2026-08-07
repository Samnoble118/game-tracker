<?php

declare(strict_types=1);

/**
 * Provides application-level use cases for physical merchandise.
 */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\MerchandiseItem;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\MerchandiseCategory;
use GameTracker\Domain\Enum\MerchandisePackaging;
use GameTracker\Domain\Repository\MerchandiseRepository;

/** Coordinates merchandise entities and user-scoped persistence. */
final readonly class MerchandiseCollection
{
    /** Creates the service for one authenticated user. */
    public function __construct(private MerchandiseRepository $items, private int $userId) {}

    /** Adds a new physical collectible. */
    public function add(string $name, string $franchise, MerchandiseCategory $category, MerchandisePackaging $packaging, CollectionType $type, int $quantity, string $notes): MerchandiseItem
    {
        $item = new MerchandiseItem($name, $franchise, $category, $this->userId, $packaging, $type, $quantity, $notes);
        $this->items->save($item);
        return $item;
    }

    /** Updates an existing physical collectible when it belongs to the user. */
    public function update(int $id, string $name, string $franchise, MerchandiseCategory $category, MerchandisePackaging $packaging, CollectionType $type, int $quantity, string $notes): ?MerchandiseItem
    {
        $item = $this->find($id);
        if ($item === null) {
            return null;
        }
        $item->updateDetails($name, $franchise, $category, $packaging, $type, $quantity, $notes);
        $this->items->save($item);
        return $item;
    }

    /** Finds one physical collectible for the current user. */
    public function find(int $id): ?MerchandiseItem { return $this->items->find($id, $this->userId); }

    /** Returns the current user's complete physical collection. @return list<MerchandiseItem> */
    public function collection(): array { return $this->items->all($this->userId); }
}
