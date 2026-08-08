<?php

declare(strict_types=1);

/** Verifies connected franchise summaries and measurable collection goals. */

namespace GameTracker\Tests\Unit;

use GameTracker\Application\Service\CollectionDetails;
use GameTracker\Application\Service\FranchiseAtlas;
use GameTracker\Application\Service\GameLibrary;
use GameTracker\Application\Service\MerchandiseCollection;
use GameTracker\Domain\Enum\CollectionItemType;
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

/** Covers cross-collection franchise aggregation without private-field leakage. */
final class FranchiseAtlasTest extends TestCase
{
    /** Confirms games, merchandise, characters, wishlists, and goals share one hub. */
    public function test_it_builds_a_connected_franchise_hub_and_goal_progress(): void
    {
        $connection=new PDO('sqlite::memory:');$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$connection->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $games=new GameLibrary(new SqliteGameRepository($connection),1);$merchandise=new MerchandiseCollection(new SqliteMerchandiseRepository($connection),1);$metadata=new SqliteCollectionMetadataRepository($connection);
        $details=new CollectionDetails($games,$merchandise,$metadata,1);$goals=new SqliteFranchiseGoalRepository($connection);$atlas=new FranchiseAtlas($games,$merchandise,$metadata,$goals,1);
        $completed=$games->add('Sonic Adventure','Dreamcast',GameStatus::Completed,100);$wanted=$games->add('Sonic Heroes','PS2',collectionType:CollectionType::Wishlist);
        $merchandise->add('Sonic Figure','Sonic the Hedgehog',MerchandiseCategory::ActionFigure,MerchandisePackaging::Loose,CollectionType::Owned,1,'Private collection note');
        $details->save(CollectionItemType::Game,(int)$completed->id(),['franchise'=>'Sonic the Hedgehog','characters'=>'Sonic, Tails','condition'=>'good']);
        $details->save(CollectionItemType::Game,(int)$wanted->id(),['franchise'=>'Sonic the Hedgehog','characters'=>'Sonic, Knuckles','condition'=>'good']);
        $atlas->addGoal('Sonic the Hedgehog','Build a ten-item Sonic archive','all',10);

        $hub=$atlas->franchise('sonic the hedgehog');

        self::assertNotNull($hub);self::assertCount(2,$hub['games']);self::assertCount(1,$hub['merchandise']);self::assertSame(['Knuckles','Sonic','Tails'],$hub['characters']);self::assertCount(1,$hub['wishlistGames']);self::assertSame(20,$hub['goals'][0]['percentage']);
    }

    /** Confirms the index filters franchise names without crossing users. */
    public function test_index_search_is_user_scoped(): void
    {
        $connection=new PDO('sqlite::memory:');$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $gameRepository=new SqliteGameRepository($connection);$merchandiseRepository=new SqliteMerchandiseRepository($connection);$metadata=new SqliteCollectionMetadataRepository($connection);$goals=new SqliteFranchiseGoalRepository($connection);
        $gameRepository->save(new \GameTracker\Domain\Entity\Game('Uncatalogued Game','PS2',userId:1));
        $merchandiseRepository->save(new \GameTracker\Domain\Entity\MerchandiseItem('Sonic Figure','Sonic the Hedgehog',MerchandiseCategory::ActionFigure,1));
        $merchandiseRepository->save(new \GameTracker\Domain\Entity\MerchandiseItem('Mario Figure','Super Mario',MerchandiseCategory::ActionFigure,2));
        $atlas=new FranchiseAtlas(new GameLibrary($gameRepository,1),new MerchandiseCollection($merchandiseRepository,1),$metadata,$goals,1);

        self::assertSame('Sonic the Hedgehog',$atlas->index('sonic')[0]['name']);self::assertCount(0,$atlas->index('mario'));
    }

    /** Confirms games without franchise metadata are safely ignored by an atlas summary. */
    public function test_it_ignores_games_without_metadata(): void
    {
        $connection=new PDO('sqlite::memory:');$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $gameRepository=new SqliteGameRepository($connection);$merchandiseRepository=new SqliteMerchandiseRepository($connection);$metadata=new SqliteCollectionMetadataRepository($connection);$goals=new SqliteFranchiseGoalRepository($connection);
        $gameRepository->save(new \GameTracker\Domain\Entity\Game('Uncatalogued Game','GBA',userId:1));
        $merchandiseRepository->save(new \GameTracker\Domain\Entity\MerchandiseItem('Sonic Figure','Sonic the Hedgehog',MerchandiseCategory::ActionFigure,1));
        $atlas=new FranchiseAtlas(new GameLibrary($gameRepository,1),new MerchandiseCollection($merchandiseRepository,1),$metadata,$goals,1);

        $hub=$atlas->franchise('Sonic the Hedgehog');

        self::assertNotNull($hub);self::assertCount(0,$hub['games']);self::assertCount(1,$hub['merchandise']);
    }
}
