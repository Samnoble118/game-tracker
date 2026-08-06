<?php

declare(strict_types=1);

/**
 * Verifies secure registration and login behavior.
 */

namespace GameTracker\Tests\Unit\Application;

use GameTracker\Application\Service\Authenticator;
use GameTracker\Tests\Support\InMemoryUserRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AuthenticatorTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
    }

    public function test_it_registers_and_authenticates_a_user(): void
    {
        $auth = new Authenticator(new InMemoryUserRepository());
        $user = $auth->register('player@example.com', 'strong-passphrase', 'strong-passphrase');

        $_SESSION = [];
        $loggedIn = $auth->login('player@example.com', 'strong-passphrase');

        self::assertNotNull($loggedIn);
        self::assertSame($user->id(), $auth->currentUser()?->id());
        self::assertNotSame('strong-passphrase', $user->passwordHash());
    }

    public function test_it_rejects_a_short_password(): void
    {
        $auth = new Authenticator(new InMemoryUserRepository());

        $this->expectException(InvalidArgumentException::class);
        $auth->register('player@example.com', 'short', 'short');
    }

    public function test_it_rejects_incorrect_credentials(): void
    {
        $auth = new Authenticator(new InMemoryUserRepository());
        $auth->register('player@example.com', 'strong-passphrase', 'strong-passphrase');

        self::assertNull($auth->login('player@example.com', 'wrong-password'));
    }

    public function test_it_updates_profile_details_after_password_confirmation(): void
    {
        $auth = new Authenticator(new InMemoryUserRepository());
        $user = $auth->register('player@example.com', 'strong-passphrase', 'strong-passphrase');

        $auth->updateProfile($user, 'player_one', 'updated@example.com', 'strong-passphrase');

        self::assertSame('player_one', $user->username());
        self::assertSame('updated@example.com', $user->email());
        self::assertSame('player_one', $user->displayName());
    }

    public function test_it_replaces_the_password_after_verifying_the_current_one(): void
    {
        $auth = new Authenticator(new InMemoryUserRepository());
        $user = $auth->register('player@example.com', 'strong-passphrase', 'strong-passphrase');

        $auth->updatePassword($user, 'strong-passphrase', 'new-strong-passphrase', 'new-strong-passphrase');

        self::assertNull($auth->login('player@example.com', 'strong-passphrase'));
        self::assertNotNull($auth->login('player@example.com', 'new-strong-passphrase'));
    }
}
