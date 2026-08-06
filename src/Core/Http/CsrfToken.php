<?php

declare(strict_types=1);

namespace GameTracker\Core\Http;

final class CsrfToken
{
    private const SESSION_KEY = 'csrf_token';

    public function value(): string
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public function isValid(?string $token): bool
    {
        return is_string($token) && hash_equals($this->value(), $token);
    }
}
