<?php

declare(strict_types=1);

/**
 * Contains the physical merchandise entity and its validation rules.
 */

namespace GameTracker\Domain\Entity;

use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\MerchandiseCategory;
use GameTracker\Domain\Enum\MerchandisePackaging;
use InvalidArgumentException;

/** Represents one owned or wished-for physical collectible. */
final class MerchandiseItem
{
    /** Creates a validated merchandise item, optionally restored with an ID. */
    public function __construct(
        private string $name,
        private string $franchise,
        private MerchandiseCategory $category,
        private readonly int $userId,
        private MerchandisePackaging $packaging = MerchandisePackaging::Loose,
        private CollectionType $collectionType = CollectionType::Owned,
        private int $quantity = 1,
        private string $notes = '',
        private ?int $id = null,
    ) {
        $this->updateDetails($name, $franchise, $category, $packaging, $collectionType, $quantity, $notes);
    }

    /** Returns the persisted identifier or null for a new item. */
    public function id(): ?int { return $this->id; }

    /** Returns the collectible name. */
    public function name(): string { return $this->name; }

    /** Returns the related game or entertainment franchise. */
    public function franchise(): string { return $this->franchise; }

    /** Returns the merchandise category. */
    public function category(): MerchandiseCategory { return $this->category; }

    /** Returns how the collectible is packaged or displayed. */
    public function packaging(): MerchandisePackaging { return $this->packaging; }

    /** Returns the user who owns the collection entry. */
    public function userId(): int { return $this->userId; }

    /** Returns whether the item is owned or on the wishlist. */
    public function collectionType(): CollectionType { return $this->collectionType; }

    /** Returns the number of identical items recorded. */
    public function quantity(): int { return $this->quantity; }

    /** Returns optional collector notes. */
    public function notes(): string { return $this->notes; }

    /** Validates and replaces all editable merchandise details. */
    public function updateDetails(
        string $name,
        string $franchise,
        MerchandiseCategory $category,
        MerchandisePackaging $packaging,
        CollectionType $collectionType,
        int $quantity,
        string $notes,
    ): void {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('An item name is required.');
        }
        if ($quantity < 1 || $quantity > 999) {
            throw new InvalidArgumentException('Quantity must be between 1 and 999.');
        }

        $this->name = $name;
        $this->franchise = trim($franchise);
        $this->category = $category;
        $this->packaging = $packaging;
        $this->collectionType = $collectionType;
        $this->quantity = $quantity;
        $this->notes = trim($notes);
    }

    /** Assigns the persisted identifier once. */
    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new InvalidArgumentException('The merchandise item already has an ID.');
        }
        $this->id = $id;
    }
}
