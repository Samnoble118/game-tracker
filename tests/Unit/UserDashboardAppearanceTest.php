<?php

declare(strict_types=1);

/**
 * Verifies dashboard appearance preferences on a user account.
 */

namespace GameTracker\Tests\Unit;

use GameTracker\Domain\Entity\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Covers validation and storage of dashboard appearance preferences. */
final class UserDashboardAppearanceTest extends TestCase
{
    /** Confirms valid artwork preferences update the user entity. */
    public function test_it_updates_dashboard_appearance(): void
    {
        $user = new User('player@example.com', 'hash');

        $user->updateDashboardAppearance('custom.webp', 'wallpaper', 65);

        self::assertSame('custom.webp', $user->dashboardImage());
        self::assertSame('wallpaper', $user->dashboardImageMode());
        self::assertSame(65, $user->dashboardOverlay());
    }

    /** Confirms unsupported artwork display modes are rejected. */
    public function test_it_rejects_an_unknown_display_mode(): void
    {
        $user = new User('player@example.com', 'hash');

        $this->expectException(InvalidArgumentException::class);
        $user->updateDashboardAppearance(null, 'unknown', 55);
    }

    /** Confirms merchandise artwork remains independent from game artwork. */
    public function test_it_updates_merchandise_appearance_independently(): void
    {
        $user = new User('player@example.com', 'hash', dashboardImage: 'games.webp');

        $user->updateMerchandiseAppearance('merchandise.webp', 'banner', 60);

        self::assertSame('games.webp', $user->dashboardImage());
        self::assertSame('merchandise.webp', $user->merchandiseImage());
        self::assertSame('banner', $user->merchandiseImageMode());
        self::assertSame(60, $user->merchandiseOverlay());
    }
}
