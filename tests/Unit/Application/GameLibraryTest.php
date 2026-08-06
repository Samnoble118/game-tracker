<?php

declare(strict_types=1);

/**
 * Verifies collection use cases through an in-memory repository.
 */

namespace GameTracker\Tests\Unit\Application;

use GameTracker\Application\Service\GameLibrary;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Tests\Support\InMemoryGameRepository;
use PHPUnit\Framework\TestCase;

/**
 * Covers adding and editing games through the application service.
 */
final class GameLibraryTest extends TestCase
{
    /**
     * Confirms persisted game details can be updated as one operation.
     */
    public function test_it_adds_and_updates_a_game(): void
    {
        $library = new GameLibrary(new InMemoryGameRepository());
        $game = $library->add('Hades', 'PC');

        $updated = $library->update(
            $game->id(),
            'Hades II',
            'Steam Deck',
            GameStatus::Playing,
            35,
        );

        self::assertNotNull($updated);
        self::assertSame('Hades II', $updated->title());
        self::assertSame('Steam Deck', $updated->platform());
        self::assertSame(GameStatus::Playing, $updated->status());
        self::assertSame(35, $updated->progress());
    }

    /**
     * Confirms games can be added directly to the wishlist.
     */
    public function test_it_adds_a_game_to_the_wishlist(): void
    {
        $library = new GameLibrary(new InMemoryGameRepository());

        $game = $library->add(
            'Metroid Prime 4',
            'Nintendo Switch 2',
            collectionType: CollectionType::Wishlist,
        );

        self::assertSame(CollectionType::Wishlist, $game->collectionType());
    }
}
