<?php

declare(strict_types=1);

/** Implements shared collection metadata persistence with SQLite. */

namespace GameTracker\Infrastructure\Persistence;

use DateTimeImmutable;
use GameTracker\Domain\Entity\CollectionMetadata;
use GameTracker\Domain\Enum\CollectionItemType;
use GameTracker\Domain\Enum\ItemCondition;
use GameTracker\Domain\Enum\ItemPackaging;
use GameTracker\Domain\Repository\CollectionMetadataRepository;
use InvalidArgumentException;
use PDO;

/** Stores public catalogue fields and private ownership fields per authenticated user. */
final readonly class SqliteCollectionMetadataRepository implements CollectionMetadataRepository
{
    /** Creates the metadata table and its lookup indexes. */
    public function __construct(private PDO $connection)
    {
        $this->connection->exec("CREATE TABLE IF NOT EXISTS collection_metadata (
            item_type TEXT NOT NULL, item_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
            franchise TEXT NOT NULL DEFAULT '', characters TEXT NOT NULL DEFAULT '', barcode TEXT NOT NULL DEFAULT '', location TEXT NOT NULL DEFAULT '',
            item_condition TEXT NOT NULL DEFAULT 'unspecified', item_packaging TEXT NOT NULL DEFAULT 'unspecified', purchase_price_pence INTEGER NULL,
            currency TEXT NOT NULL DEFAULT 'GBP', purchased_on TEXT NULL, retailer TEXT NOT NULL DEFAULT '',
            serial_number TEXT NOT NULL DEFAULT '', receipt_reference TEXT NOT NULL DEFAULT '', private_notes TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(item_type, item_id, user_id)
        )");
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_collection_metadata_user_franchise ON collection_metadata(user_id, franchise)');
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_collection_metadata_user_location ON collection_metadata(user_id, location)');
        $this->migratePackaging();
        $this->migrateBarcode();
        $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_collection_metadata_user_barcode ON collection_metadata(user_id, barcode) WHERE barcode <> ''");
    }

    /** Upserts metadata after confirming ownership in the appropriate collection table. */
    public function save(CollectionMetadata $metadata): void
    {
        $table = $metadata->itemType() === CollectionItemType::Game ? 'games' : 'merchandise';
        $ownership = $this->connection->prepare("SELECT 1 FROM {$table} WHERE id=:item_id AND user_id=:user_id");
        $ownership->execute(['item_id' => $metadata->itemId(), 'user_id' => $metadata->userId()]);
        if ($ownership->fetchColumn() === false) throw new InvalidArgumentException('That collection item could not be found.');

        $statement = $this->connection->prepare("INSERT INTO collection_metadata
            (item_type,item_id,user_id,franchise,characters,barcode,location,item_condition,item_packaging,purchase_price_pence,currency,purchased_on,retailer,serial_number,receipt_reference,private_notes,updated_at)
            VALUES (:item_type,:item_id,:user_id,:franchise,:characters,:barcode,:location,:item_condition,:item_packaging,:purchase_price_pence,:currency,:purchased_on,:retailer,:serial_number,:receipt_reference,:private_notes,CURRENT_TIMESTAMP)
            ON CONFLICT(item_type,item_id,user_id) DO UPDATE SET franchise=excluded.franchise,characters=excluded.characters,
            barcode=excluded.barcode,location=excluded.location,item_condition=excluded.item_condition,item_packaging=excluded.item_packaging,purchase_price_pence=excluded.purchase_price_pence,
            currency=excluded.currency,purchased_on=excluded.purchased_on,retailer=excluded.retailer,serial_number=excluded.serial_number,
            receipt_reference=excluded.receipt_reference,private_notes=excluded.private_notes,updated_at=CURRENT_TIMESTAMP");
        $statement->execute($this->parameters($metadata));
    }

    /** Returns metadata only for the supplied user. */
    public function find(CollectionItemType $type, int $itemId, int $userId): ?CollectionMetadata
    {
        $statement = $this->connection->prepare('SELECT * FROM collection_metadata WHERE item_type=:item_type AND item_id=:item_id AND user_id=:user_id');
        $statement->execute(['item_type' => $type->value, 'item_id' => $itemId, 'user_id' => $userId]);
        $row = $statement->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    /** Finds barcode matches without crossing the authenticated user's boundary. */
    public function findByBarcode(string $barcode, int $userId): array
    {
        $statement = $this->connection->prepare('SELECT * FROM collection_metadata WHERE barcode=:barcode AND user_id=:user_id ORDER BY item_type,item_id');
        $statement->execute(['barcode'=>$barcode,'user_id'=>$userId]);
        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /** Returns all metadata for building user-scoped collection indexes. */
    public function all(int $userId): array
    {
        $statement=$this->connection->prepare('SELECT * FROM collection_metadata WHERE user_id=:user_id ORDER BY franchise,item_type,item_id');
        $statement->execute(['user_id'=>$userId]);
        return array_map($this->hydrate(...),$statement->fetchAll());
    }

    /** Converts metadata into named SQL parameters. @return array<string,int|string|null> */
    private function parameters(CollectionMetadata $metadata): array
    {
        return [
            'item_type'=>$metadata->itemType()->value,'item_id'=>$metadata->itemId(),'user_id'=>$metadata->userId(),
            'franchise'=>$metadata->franchise(),'characters'=>$metadata->characters(),'barcode'=>$metadata->barcode(),'location'=>$metadata->location(),
            'item_condition'=>$metadata->condition()->value,'item_packaging'=>$metadata->packaging()->value,'purchase_price_pence'=>$metadata->purchasePricePence(),
            'currency'=>$metadata->currency(),'purchased_on'=>$metadata->purchasedOn()?->format('Y-m-d'),'retailer'=>$metadata->retailer(),
            'serial_number'=>$metadata->serialNumber(),'receipt_reference'=>$metadata->receiptReference(),'private_notes'=>$metadata->privateNotes(),
        ];
    }

    /** Reconstitutes metadata from a database row. */
    private function hydrate(array $row): CollectionMetadata
    {
        return new CollectionMetadata(
            CollectionItemType::from((string)$row['item_type']),(int)$row['item_id'],(int)$row['user_id'],
            (string)$row['franchise'],(string)$row['characters'],(string)$row['barcode'],(string)$row['location'],ItemCondition::from((string)$row['item_condition']),ItemPackaging::from((string)$row['item_packaging']),
            $row['purchase_price_pence'] === null ? null : (int)$row['purchase_price_pence'],(string)$row['currency'],
            $row['purchased_on'] === null ? null : new DateTimeImmutable((string)$row['purchased_on']),
            (string)$row['retailer'],(string)$row['serial_number'],(string)$row['receipt_reference'],(string)$row['private_notes'],
        );
    }

    /** Adds packaging when upgrading a metadata table created by an earlier build. */
    private function migratePackaging(): void
    {
        $columns = $this->connection->query('PRAGMA table_info(collection_metadata)')->fetchAll();
        if (!in_array('item_packaging', array_column($columns, 'name'), true)) {
            $this->connection->exec("ALTER TABLE collection_metadata ADD COLUMN item_packaging TEXT NOT NULL DEFAULT 'unspecified'");
        }
    }

    /** Adds barcode storage when upgrading an existing metadata table. */
    private function migrateBarcode(): void
    {
        $columns = $this->connection->query('PRAGMA table_info(collection_metadata)')->fetchAll();
        if (!in_array('barcode', array_column($columns, 'name'), true)) {
            $this->connection->exec("ALTER TABLE collection_metadata ADD COLUMN barcode TEXT NOT NULL DEFAULT ''");
        }
    }
}
