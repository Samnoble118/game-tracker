<?php

declare(strict_types=1);

/** Contains shared catalogue and private ownership details for a collection item. */

namespace GameTracker\Domain\Entity;

use DateTimeImmutable;
use GameTracker\Domain\Enum\CollectionItemType;
use GameTracker\Domain\Enum\ItemCondition;
use GameTracker\Domain\Enum\ItemPackaging;
use InvalidArgumentException;

/** Represents searchable catalogue fields and owner-only financial records. */
final readonly class CollectionMetadata
{
    /** Creates and validates metadata for one user-owned collection record. */
    public function __construct(
        private CollectionItemType $itemType,
        private int $itemId,
        private int $userId,
        private string $franchise = '',
        private string $characters = '',
        private string $barcode = '',
        private string $location = '',
        private ItemCondition $condition = ItemCondition::Unspecified,
        private ItemPackaging $packaging = ItemPackaging::Unspecified,
        private ?int $purchasePricePence = null,
        private string $currency = 'GBP',
        private ?DateTimeImmutable $purchasedOn = null,
        private string $retailer = '',
        private string $serialNumber = '',
        private string $receiptReference = '',
        private string $privateNotes = '',
    ) {
        if ($itemId < 1 || $userId < 1) throw new InvalidArgumentException('A valid item and user are required.');
        if ($purchasePricePence !== null && ($purchasePricePence < 0 || $purchasePricePence > 999_999_999)) throw new InvalidArgumentException('Enter a valid purchase price.');
        if (!preg_match('/^[A-Z]{3}$/', $currency)) throw new InvalidArgumentException('Currency must use a three-letter code.');
        if ($barcode !== '' && preg_match('/^\d{8,14}$/', $barcode) !== 1) throw new InvalidArgumentException('Barcodes must contain 8 to 14 digits.');
        foreach ([$franchise, $characters, $location, $retailer, $serialNumber, $receiptReference] as $value) {
            if (strlen($value) > 255) throw new InvalidArgumentException('Collection details cannot exceed 255 characters per field.');
        }
        if (strlen($privateNotes) > 4000) throw new InvalidArgumentException('Private notes cannot exceed 4,000 characters.');
    }

    /** Returns the collection record type. */ public function itemType(): CollectionItemType { return $this->itemType; }
    /** Returns the collection record ID. */ public function itemId(): int { return $this->itemId; }
    /** Returns the owning user ID. */ public function userId(): int { return $this->userId; }
    /** Returns the shared franchise name. */ public function franchise(): string { return $this->franchise; }
    /** Returns comma-separated characters. */ public function characters(): string { return $this->characters; }
    /** Returns the UPC, EAN, or GTIN barcode digits. */ public function barcode(): string { return $this->barcode; }
    /** Returns the shelf, cabinet, room, or storage location. */ public function location(): string { return $this->location; }
    /** Returns the recorded physical condition. */ public function condition(): ItemCondition { return $this->condition; }
    /** Returns how the item is packaged or held. */ public function packaging(): ItemPackaging { return $this->packaging; }
    /** Returns the private purchase price in pence. */ public function purchasePricePence(): ?int { return $this->purchasePricePence; }
    /** Returns the ISO-style currency code. */ public function currency(): string { return $this->currency; }
    /** Returns the private purchase date. */ public function purchasedOn(): ?DateTimeImmutable { return $this->purchasedOn; }
    /** Returns the private retailer or seller. */ public function retailer(): string { return $this->retailer; }
    /** Returns the private serial number. */ public function serialNumber(): string { return $this->serialNumber; }
    /** Returns the private receipt reference. */ public function receiptReference(): string { return $this->receiptReference; }
    /** Returns owner-only notes. */ public function privateNotes(): string { return $this->privateNotes; }
}
