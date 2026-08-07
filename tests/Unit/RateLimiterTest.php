<?php

declare(strict_types=1);

/**
 * Verifies database-backed throttling for sensitive requests.
 */

namespace GameTracker\Tests\Unit;

use GameTracker\Core\Security\RateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

/** Covers rate-limit thresholds and successful-attempt clearing. */
final class RateLimiterTest extends TestCase
{
    /** Confirms attempts become blocked at the configured threshold. */
    public function test_it_limits_and_clears_repeated_attempts(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $limiter = new RateLimiter($connection);

        self::assertFalse($limiter->tooManyAttempts('login|user', 2, 900));
        $limiter->hit('login|user', 900);
        $limiter->hit('login|user', 900);
        self::assertTrue($limiter->tooManyAttempts('login|user', 2, 900));

        $limiter->clear('login|user');
        self::assertFalse($limiter->tooManyAttempts('login|user', 2, 900));
    }
}
