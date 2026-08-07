<?php

declare(strict_types=1);

/**
 * Implements physical merchandise persistence using SQLite.
 */

namespace GameTracker\Infrastructure\Persistence;

use GameTracker\Domain\Entity\MerchandiseItem;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\MerchandiseCategory;
use GameTracker\Domain\Enum\MerchandisePackaging;
use GameTracker\Domain\Repository\MerchandiseRepository;
use PDO;

/** Stores and hydrates user-isolated merchandise records. */
final readonly class SqliteMerchandiseRepository implements MerchandiseRepository
{
    /** Creates the repository and ensures the merchandise table exists. */
    public function __construct(private PDO $connection)
    {
        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS merchandise (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                franchise TEXT NOT NULL DEFAULT '',
                category TEXT NOT NULL,
                packaging TEXT NOT NULL DEFAULT 'loose',
                collection_type TEXT NOT NULL DEFAULT 'owned',
                quantity INTEGER NOT NULL DEFAULT 1,
                notes TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->migratePackagingColumn();
    }

    /** Inserts a new item or updates an existing user-owned item. */
    public function save(MerchandiseItem $item): void
    {
        $parameters = [
            'user_id' => $item->userId(), 'name' => $item->name(),
            'franchise' => $item->franchise(), 'category' => $item->category()->value,
            'packaging' => $item->packaging()->value,
            'collection_type' => $item->collectionType()->value,
            'quantity' => $item->quantity(), 'notes' => $item->notes(),
        ];
        if ($item->id() === null) {
            $statement = $this->connection->prepare(
                'INSERT INTO merchandise (user_id, name, franchise, category, packaging, collection_type, quantity, notes)
                 VALUES (:user_id, :name, :franchise, :category, :packaging, :collection_type, :quantity, :notes)'
            );
            $statement->execute($parameters);
            $item->assignId((int) $this->connection->lastInsertId());
            return;
        }

        $statement = $this->connection->prepare(
            'UPDATE merchandise SET name=:name, franchise=:franchise, category=:category, packaging=:packaging,
             collection_type=:collection_type, quantity=:quantity, notes=:notes
             WHERE id=:id AND user_id=:user_id'
        );
        $statement->execute([...$parameters, 'id' => $item->id()]);
    }

    /** Returns all merchandise belonging to a user in alphabetical order. */
    public function all(int $userId): array
    {
        $statement = $this->connection->prepare('SELECT * FROM merchandise WHERE user_id=:user_id ORDER BY name');
        $statement->execute(['user_id' => $userId]);
        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /** Finds one merchandise item without exposing another user's collection. */
    public function find(int $id, int $userId): ?MerchandiseItem
    {
        $statement = $this->connection->prepare('SELECT * FROM merchandise WHERE id=:id AND user_id=:user_id');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        $row = $statement->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    /** Converts a database row into a merchandise entity. */
    private function hydrate(array $row): MerchandiseItem
    {
        return new MerchandiseItem(
            name: (string) $row['name'], franchise: (string) $row['franchise'],
            category: MerchandiseCategory::from((string) $row['category']), userId: (int) $row['user_id'],
            packaging: MerchandisePackaging::from((string) $row['packaging']),
            collectionType: CollectionType::from((string) $row['collection_type']),
            quantity: (int) $row['quantity'], notes: (string) $row['notes'], id: (int) $row['id'],
        );
    }

    /** Adds and populates the packaging column for databases created by earlier versions. */
    private function migratePackagingColumn(): void
    {
        $columns = $this->connection->query('PRAGMA table_info(merchandise)')->fetchAll();
        $hasPackaging = array_filter($columns, static fn (array $column): bool => $column['name'] === 'packaging') !== [];
        if ($hasPackaging) {
            return;
        }

        $this->connection->exec("ALTER TABLE merchandise ADD COLUMN packaging TEXT NOT NULL DEFAULT 'loose'");
        $this->connection->exec(
            "UPDATE merchandise SET packaging = CASE
                WHEN lower(notes) LIKE '%sealed%' THEN 'sealed'
                WHEN lower(notes) LIKE '%boxed%' THEN 'boxed'
                WHEN lower(notes) LIKE '%carded%' THEN 'carded'
                WHEN lower(notes) LIKE '%built%' THEN 'built'
                ELSE 'loose'
            END"
        );
    }
}
