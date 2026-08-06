<?php

declare(strict_types=1);

namespace GameTracker\Tests\Unit\Application;

use GameTracker\Application\Service\GameLibrary;
use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Tests\Support\InMemoryGameRepository;
use PHPUnit\Framework\TestCase;

final class GameLibraryTest extends TestCase
{
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
}
