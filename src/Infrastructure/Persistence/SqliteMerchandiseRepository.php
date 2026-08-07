<?php

declare(strict_types=1);

/**
 * Implements physical merchandise persistence using SQLite.
 */

namespace GameTracker\Infrastructure\Persistence;

use GameTracker\Domain\Entity\MerchandiseItem;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\MerchandiseCategory;
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
                collection_type TEXT NOT NULL DEFAULT 'owned',
                quantity INTEGER NOT NULL DEFAULT 1,
                notes TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    /** Inserts a new item or updates an existing user-owned item. */
    public function save(MerchandiseItem $item): void
    {
        $parameters = [
            'user_id' => $item->userId(), 'name' => $item->name(),
            'franchise' => $item->franchise(), 'category' => $item->category()->value,
            'collection_type' => $item->collectionType()->value,
            'quantity' => $item->quantity(), 'notes' => $item->notes(),
        ];
        if ($item->id() === null) {
            $statement = $this->connection->prepare(
                'INSERT INTO merchandise (user_id, name, franchise, category, collection_type, quantity, notes)
                 VALUES (:user_id, :name, :franchise, :category, :collection_type, :quantity, :notes)'
            );
            $statement->execute($parameters);
            $item->assignId((int) $this->connection->lastInsertId());
            return;
        }

        $statement = $this->connection->prepare(
            'UPDATE merchandise SET name=:name, franchise=:franchise, category=:category,
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
            collectionType: CollectionType::from((string) $row['collection_type']),
            quantity: (int) $row['quantity'], notes: (string) $row['notes'], id: (int) $row['id'],
        );
    }
}
