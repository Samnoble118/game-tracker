<?php

declare(strict_types=1);

/** Coordinates shared catalogue and private ownership details. */

namespace GameTracker\Application\Service;

use DateTimeImmutable;
use GameTracker\Domain\Entity\CollectionMetadata;
use GameTracker\Domain\Enum\CollectionItemType;
use GameTracker\Domain\Enum\ItemCondition;
use GameTracker\Domain\Enum\ItemPackaging;
use GameTracker\Domain\Repository\CollectionMetadataRepository;
use InvalidArgumentException;

/** Provides authenticated metadata operations across games and merchandise. */
final readonly class CollectionDetails
{
    /** Creates the service with both collections and metadata persistence. */
    public function __construct(
        private GameLibrary $games,
        private MerchandiseCollection $merchandise,
        private CollectionMetadataRepository $metadata,
        private int $userId,
    ) {}

    /** Returns a display subject only when it belongs to the current user. @return array{type:CollectionItemType,id:int,name:string,subtitle:string}|null */
    public function subject(CollectionItemType $type, int $itemId): ?array
    {
        if ($type === CollectionItemType::Game) {
            $game = $this->games->find($itemId);
            return $game === null ? null : ['type'=>$type,'id'=>$itemId,'name'=>$game->title(),'subtitle'=>$game->platform()];
        }
        $item = $this->merchandise->find($itemId);
        return $item === null ? null : ['type'=>$type,'id'=>$itemId,'name'=>$item->name(),'subtitle'=>$item->category()->label()];
    }

    /** Returns saved details or blank defaults for an owned subject. */
    public function details(CollectionItemType $type, int $itemId): CollectionMetadata
    {
        $subject = $this->subject($type, $itemId);
        if ($subject === null) throw new InvalidArgumentException('That collection item could not be found.');
        $saved = $this->metadata->find($type, $itemId, $this->userId);
        if ($saved !== null) return $saved;
        $franchise = '';
        if ($type === CollectionItemType::Merchandise) $franchise = $this->merchandise->find($itemId)?->franchise() ?? '';
        $packaging = $type === CollectionItemType::Merchandise
            ? ItemPackaging::from($this->merchandise->find($itemId)?->packaging()->value ?? 'unspecified')
            : ItemPackaging::Unspecified;
        return new CollectionMetadata($type, $itemId, $this->userId, $franchise, packaging: $packaging);
    }

    /** Validates submitted fields and persists private collection details. @param array<string,mixed> $input */
    public function save(CollectionItemType $type, int $itemId, array $input): void
    {
        if ($this->subject($type, $itemId) === null) throw new InvalidArgumentException('That collection item could not be found.');
        $price = trim((string)($input['purchase_price'] ?? ''));
        $pricePence = null;
        if ($price !== '') {
            if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/', $price)) throw new InvalidArgumentException('Enter a valid purchase price.');
            $pricePence = (int) round((float)$price * 100);
        }
        $dateValue = trim((string)($input['purchased_on'] ?? ''));
        $purchasedOn = $dateValue === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
        if ($purchasedOn === false) throw new InvalidArgumentException('Choose a valid purchase date.');

        $metadata = new CollectionMetadata(
            $type,$itemId,$this->userId,
            trim((string)($input['franchise'] ?? '')),trim((string)($input['characters'] ?? '')),trim((string)($input['location'] ?? '')),
            ItemCondition::from((string)($input['condition'] ?? 'unspecified')),ItemPackaging::from((string)($input['packaging'] ?? 'unspecified')),$pricePence,
            strtoupper(trim((string)($input['currency'] ?? 'GBP'))),$purchasedOn,
            trim((string)($input['retailer'] ?? '')),trim((string)($input['serial_number'] ?? '')),
            trim((string)($input['receipt_reference'] ?? '')),trim((string)($input['private_notes'] ?? '')),
        );
        $this->metadata->save($metadata);
    }

    /** Returns games and merchandise connected by an exact franchise name. @return array{games:list<\GameTracker\Domain\Entity\Game>,merchandise:list<\GameTracker\Domain\Entity\MerchandiseItem>} */
    public function related(string $franchise, CollectionItemType $currentType, int $currentId): array
    {
        if ($franchise === '') return ['games'=>[],'merchandise'=>[]];
        $games = array_values(array_filter($this->games->collection(), function ($game) use ($franchise, $currentType, $currentId): bool {
            if ($currentType === CollectionItemType::Game && $game->id() === $currentId) return false;
            return strcasecmp($this->metadata->find(CollectionItemType::Game, (int)$game->id(), $this->userId)?->franchise() ?? '', $franchise) === 0;
        }));
        $merchandise = array_values(array_filter($this->merchandise->collection(), static function ($item) use ($franchise, $currentType, $currentId): bool {
            if ($currentType === CollectionItemType::Merchandise && $item->id() === $currentId) return false;
            return strcasecmp($item->franchise(), $franchise) === 0;
        }));
        return ['games'=>$games,'merchandise'=>$merchandise];
    }
}
