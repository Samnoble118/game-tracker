<?php

declare(strict_types=1);

/**
 * Generates and validates session-backed CSRF tokens for form submissions.
 */

namespace GameTracker\Core\Http;

/**
 * Protects state-changing requests from cross-site request forgery.
 */
final class CsrfToken
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Returns the current session token, generating it when absent.
     */
    public function value(): string
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Safely compares a submitted token with the current session token.
     */
    public function isValid(?string $token): bool
    {
        return is_string($token) && hash_equals($this->value(), $token);
    }
}
