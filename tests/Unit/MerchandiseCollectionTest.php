<?php

declare(strict_types=1);

/**
 * Verifies physical merchandise validation and SQLite persistence.
 */

namespace GameTracker\Tests\Unit;

use GameTracker\Domain\Entity\MerchandiseItem;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\MerchandiseCategory;
use GameTracker\Infrastructure\Persistence\SqliteMerchandiseRepository;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

/** Covers physical collection validation and user isolation. */
final class MerchandiseCollectionTest extends TestCase
{
    /** Confirms merchandise details persist and remain scoped to their owner. */
    public function test_it_persists_merchandise_for_one_user(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $repository = new SqliteMerchandiseRepository($connection);
        $item = new MerchandiseItem(
            'Sonic Anniversary Statue', 'Sonic the Hedgehog',
            MerchandiseCategory::Statue, 1, CollectionType::Owned, 2, 'Limited edition.',
        );

        $repository->save($item);
        $stored = $repository->find($item->id(), 1);

        self::assertNotNull($stored);
        self::assertSame('Sonic Anniversary Statue', $stored->name());
        self::assertSame(MerchandiseCategory::Statue, $stored->category());
        self::assertSame(2, $stored->quantity());
        self::assertNull($repository->find($item->id(), 2));
    }

    /** Confirms a physical collection cannot contain a zero quantity. */
    public function test_it_rejects_an_invalid_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MerchandiseItem('Sonic Figure', 'Sonic', MerchandiseCategory::ActionFigure, 1, quantity: 0);
    }
}
