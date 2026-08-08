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

    /** Confirms a readable custom palette and compact cards can be stored. */
    public function test_it_updates_theme_colours_and_density(): void
    {
        $user = new User('player@example.com','hash');
        $user->updateTheme('custom','#00aaff','#05080d','#101923','#ffffff','compact');

        self::assertSame('custom',$user->themePreset());
        self::assertSame('#00aaff',$user->themeAccent());
        self::assertSame('compact',$user->layoutDensity());
    }

    /** Confirms unreadable custom colour combinations are rejected. */
    public function test_it_rejects_low_contrast_theme_colours(): void
    {
        $user = new User('player@example.com','hash');

        $this->expectException(InvalidArgumentException::class);
        $user->updateTheme('custom','#777777','#ffffff','#eeeeee','#dddddd','spacious');
    }
}
