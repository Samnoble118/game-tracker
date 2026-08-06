<?php

declare(strict_types=1);

namespace GameTracker\Tests\Unit;

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\GameStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GameTest extends TestCase
{
    public function test_it_tracks_status_and_progress(): void
    {
        $game = new Game('Hades', 'PC');

        $game->updateStatus(GameStatus::Playing);
        $game->setProgress(40);

        self::assertSame(GameStatus::Playing, $game->status());
        self::assertSame(40, $game->progress());
    }

    public function test_progress_must_be_a_percentage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Game('Hades', 'PC', progress: 101);
    }
}
