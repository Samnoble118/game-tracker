<?php

declare(strict_types=1);

namespace GameTracker\Tests\Unit\Application;

use GameTracker\Application\Service\TrophyCabinet;
use GameTracker\Domain\Enum\TrophyGrade;
use GameTracker\Tests\Support\InMemoryTrophyRepository;
use PHPUnit\Framework\TestCase;

final class TrophyCabinetTest extends TestCase
{
    public function test_it_adds_and_marks_a_trophy_earned(): void
    {
        $cabinet = new TrophyCabinet(new InMemoryTrophyRepository());
        $trophy = $cabinet->add(42, 'A New Beginning', TrophyGrade::Bronze, false);

        $updated = $cabinet->toggle($trophy->id(), 42);

        self::assertNotNull($updated);
        self::assertTrue($updated->isEarned());
        self::assertNotNull($updated->earnedAt());
    }

    public function test_it_does_not_toggle_a_trophy_for_another_game(): void
    {
        $cabinet = new TrophyCabinet(new InMemoryTrophyRepository());
        $trophy = $cabinet->add(42, 'A New Beginning', TrophyGrade::Bronze, false);

        self::assertNull($cabinet->toggle($trophy->id(), 99));
    }
}
