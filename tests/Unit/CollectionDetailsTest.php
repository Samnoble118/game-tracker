<?php

declare(strict_types=1);

/** Verifies shared collection metadata, franchise connections, and privacy boundaries. */

namespace GameTracker\Tests\Unit;

use GameTracker\Application\Service\CollectionDetails;
use GameTracker\Application\Service\GameLibrary;
use GameTracker\Application\Service\MerchandiseCollection;
use GameTracker\Domain\Enum\CollectionItemType;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\ItemCondition;
use GameTracker\Domain\Enum\MerchandiseCategory;
use GameTracker\Domain\Enum\MerchandisePackaging;
use GameTracker\Infrastructure\Persistence\SqliteCollectionMetadataRepository;
use GameTracker\Infrastructure\Persistence\SqliteGameRepository;
use GameTracker\Infrastructure\Persistence\SqliteMerchandiseRepository;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

/** Covers metadata storage for games and merchandise owned by one user. */
final class CollectionDetailsTest extends TestCase
{
    /** Confirms ownership records are stored and related franchise items are connected. */
    public function test_it_saves_private_details_and_connects_a_franchise(): void
    {
        [$details, $games, $merchandise] = $this->services(1);
        $game = $games->add('Sonic Frontiers', 'PS5');
        $figure = $merchandise->add('Sonic Figure', 'Sonic the Hedgehog', MerchandiseCategory::ActionFigure, MerchandisePackaging::Boxed, CollectionType::Owned, 1, '');

        $details->save(CollectionItemType::Game, $game->id(), [
            'franchise'=>'Sonic the Hedgehog','characters'=>'Sonic, Tails','location'=>'Games room · Shelf 2',
            'condition'=>'like-new','packaging'=>'boxed','purchase_price'=>'49.99','currency'=>'gbp','purchased_on'=>'2026-08-01',
            'retailer'=>'GAME','serial_number'=>'ABC123','receipt_reference'=>'Order 42','private_notes'=>'Keep receipt for insurance.',
        ]);

        $stored = $details->details(CollectionItemType::Game, $game->id());
        $related = $details->related($stored->franchise(), CollectionItemType::Game, $game->id());

        self::assertSame(ItemCondition::LikeNew, $stored->condition());
        self::assertSame(4999, $stored->purchasePricePence());
        self::assertSame('ABC123', $stored->serialNumber());
        self::assertCount(1, $related['merchandise']);
        self::assertSame($figure->id(), $related['merchandise'][0]->id());
    }

    /** Confirms metadata cannot be created for another user's collection record. */
    public function test_it_rejects_cross_user_metadata_changes(): void
    {
        $connection = $this->connection();
        $gamesRepository = new SqliteGameRepository($connection);
        $merchandiseRepository = new SqliteMerchandiseRepository($connection);
        $ownerGames = new GameLibrary($gamesRepository, 2);
        $privateGame = $ownerGames->add('Private Game', 'PC');
        $details = new CollectionDetails(
            new GameLibrary($gamesRepository, 1),
            new MerchandiseCollection($merchandiseRepository, 1),
            new SqliteCollectionMetadataRepository($connection),
            1,
        );

        $this->expectException(InvalidArgumentException::class);
        $details->save(CollectionItemType::Game, $privateGame->id(), ['condition'=>'good']);
    }

    /** Confirms barcode matching detects another item across collection types. */
    public function test_it_detects_cross_collection_barcode_duplicates(): void
    {
        [$details, $games, $merchandise] = $this->services(1);
        $game = $games->add('Sonic Adventure', 'Dreamcast');
        $figure = $merchandise->add('Sonic Figure', 'Sonic the Hedgehog', MerchandiseCategory::ActionFigure, MerchandisePackaging::Boxed, CollectionType::Owned, 1, '');
        $details->save(CollectionItemType::Game, $game->id(), ['barcode'=>'5 012345 678900','condition'=>'good']);

        $duplicates = $details->duplicates('5012345678900', CollectionItemType::Merchandise, $figure->id());

        self::assertCount(1, $duplicates);
        self::assertSame('Sonic Adventure', $duplicates[0]['name']);
        self::assertSame(CollectionItemType::Game, $duplicates[0]['type']);
        self::assertSame('5012345678900', $details->details(CollectionItemType::Game, $game->id())->barcode());
    }

    /** Creates all services against one in-memory database. @return array{CollectionDetails,GameLibrary,MerchandiseCollection} */
    private function services(int $userId): array
    {
        $connection = $this->connection();
        $games = new GameLibrary(new SqliteGameRepository($connection), $userId);
        $merchandise = new MerchandiseCollection(new SqliteMerchandiseRepository($connection), $userId);
        return [$details = new CollectionDetails($games, $merchandise, new SqliteCollectionMetadataRepository($connection), $userId), $games, $merchandise];
    }

    /** Creates an exception-reporting in-memory SQLite connection. */
    private function connection(): PDO
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $connection;
    }
}
