<?php

declare(strict_types=1);

/**
 * Verifies the validation and state transitions of the core game entity.
 */

namespace GameTracker\Tests\Unit;

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\GameStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Covers game progress and status behavior independently of persistence.
 */
final class GameTest extends TestCase
{
    /**
     * Confirms a game can move into progress and retain its percentage.
     */
    public function test_it_tracks_status_and_progress(): void
    {
        $game = new Game('Hades', 'PC', 1);

        $game->updateStatus(GameStatus::Playing);
        $game->setProgress(40);

        self::assertSame(GameStatus::Playing, $game->status());
        self::assertSame(40, $game->progress());
    }

    /**
     * Confirms percentages outside the supported range are rejected.
     */
    public function test_progress_must_be_a_percentage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Game('Hades', 'PC', 1, progress: 101);
    }

    /**
     * Confirms common PlayStation platform names enable trophy management.
     */
    public function test_it_recognises_playstation_platforms(): void
    {
        self::assertTrue((new Game('Astro Bot', 'PS5', 1))->supportsTrophies());
        self::assertTrue((new Game('Bloodborne', 'PlayStation 4', 1))->supportsTrophies());
        self::assertFalse((new Game('Hades', 'PC', 1))->supportsTrophies());
    }
}
