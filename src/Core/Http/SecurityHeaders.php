<?php

declare(strict_types=1);

/**
 * Applies browser security headers before a response is rendered.
 */

namespace GameTracker\Core\Http;

/** Centralizes response headers that reduce common browser-side attacks. */
final class SecurityHeaders
{
    /** Sends the application's baseline browser security policy. */
    public function apply(bool $https): void
    {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Cross-Origin-Opener-Policy: same-origin');

        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
