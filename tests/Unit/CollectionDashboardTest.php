<?php

declare(strict_types=1);

/** Verifies the signed-in Collection HQ overview. */

namespace GameTracker\Tests\Unit;

use GameTracker\Application\Service\CollectionDashboard;
use GameTracker\Application\Service\FranchiseAtlas;
use GameTracker\Application\Service\GameLibrary;
use GameTracker\Application\Service\MerchandiseCollection;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Domain\Enum\MerchandiseCategory;
use GameTracker\Domain\Enum\MerchandisePackaging;
use GameTracker\Infrastructure\Persistence\SqliteCollectionMetadataRepository;
use GameTracker\Infrastructure\Persistence\SqliteFranchiseGoalRepository;
use GameTracker\Infrastructure\Persistence\SqliteGameRepository;
use GameTracker\Infrastructure\Persistence\SqliteMerchandiseRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/** Covers overview counts, active games, and collection insights. */
final class CollectionDashboardTest extends TestCase
{
    /** Confirms the dashboard combines only the signed-in user's collection. */
    public function test_it_builds_a_collection_hq_overview(): void
    {
        $connection=new PDO('sqlite::memory:');$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $gameRepository=new SqliteGameRepository($connection);$merchandiseRepository=new SqliteMerchandiseRepository($connection);
        $games=new GameLibrary($gameRepository,1);$merchandise=new MerchandiseCollection($merchandiseRepository,1);
        $games->add('Sonic Frontiers','PS5',GameStatus::Playing,70);
        $games->add('Sonic Adventure','PS5',GameStatus::Completed,100);
        $games->add('Wanted Game','Switch',collectionType:CollectionType::Wishlist);
        $merchandise->add('Sonic Figure','Sonic the Hedgehog',MerchandiseCategory::ActionFigure,MerchandisePackaging::Loose,CollectionType::Owned,2,'');
        $atlas=new FranchiseAtlas($games,$merchandise,new SqliteCollectionMetadataRepository($connection),new SqliteFranchiseGoalRepository($connection),1);

        $overview=(new CollectionDashboard($games,$merchandise,$atlas))->overview();

        self::assertSame(3,$overview['gameCounts']['all']);
        self::assertSame(2,$overview['merchandiseCount']);
        self::assertSame(1,$overview['wishlistCount']);
        self::assertSame(50,$overview['completionRate']);
        self::assertSame('Sonic Frontiers',$overview['playing'][0]->title());
        self::assertSame('PS5',$overview['favouritePlatform']);
        self::assertSame('Sonic the Hedgehog',$overview['largestFranchise']['name']);
    }
}
