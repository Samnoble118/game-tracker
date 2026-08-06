<?php

declare(strict_types=1);

/**
 * Verifies dashboard appearance preferences on a user account.
 */

namespace GameTracker\Tests\Unit;

use GameTracker\Domain\Entity\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserDashboardAppearanceTest extends TestCase
{
    public function test_it_updates_dashboard_appearance(): void
    {
        $user = new User('player@example.com', 'hash');

        $user->updateDashboardAppearance('custom.webp', 'wallpaper', 65);

        self::assertSame('custom.webp', $user->dashboardImage());
        self::assertSame('wallpaper', $user->dashboardImageMode());
        self::assertSame(65, $user->dashboardOverlay());
    }

    public function test_it_rejects_an_unknown_display_mode(): void
    {
        $user = new User('player@example.com', 'hash');

        $this->expectException(InvalidArgumentException::class);
        $user->updateDashboardAppearance(null, 'unknown', 55);
    }
}
