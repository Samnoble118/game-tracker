<?php

declare(strict_types=1);

/**
 * Handles registration, login, and logout requests.
 */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\Authenticator;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Core\Security\RateLimiter;
use GameTracker\Domain\Repository\GameRepository;
use InvalidArgumentException;

/** Handles registration, login, and logout HTTP requests. */
final readonly class AuthController
{
    /** Creates the controller with authentication and security dependencies. */
    public function __construct(
        private Authenticator $auth,
        private GameRepository $games,
        private CsrfToken $csrf,
        private RateLimiter $rateLimiter,
        private string $templatePath,
    ) {
    }

    /**
     * Renders and processes a login or registration form.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $input
     */
    public function form(string $mode, array $server, array $input): void
    {
        $mode = $mode === 'register' ? 'register' : 'login';
        $errors = [];
        $email = trim((string) ($input['email'] ?? ''));
        $identifier = trim((string) ($input['identifier'] ?? $email));

        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $remoteAddress = (string) ($server['REMOTE_ADDR'] ?? 'unknown');
            $rateLimitKey = $mode . '|' . $remoteAddress . '|' . strtolower($mode === 'register' ? $email : $identifier);
            $maximumAttempts = $mode === 'register' ? 3 : 5;
            $windowSeconds = $mode === 'register' ? 3600 : 900;

            if ($this->rateLimiter->tooManyAttempts($rateLimitKey, $maximumAttempts, $windowSeconds)) {
                http_response_code(429);
                header('Retry-After: ' . $windowSeconds);
                $errors[] = 'Too many attempts. Please wait before trying again.';
            } elseif (!$this->csrf->isValid(isset($input['_token']) ? (string) $input['_token'] : null)) {
                $errors[] = 'Your session expired. Refresh the page and try again.';
            } else {
                $this->rateLimiter->hit($rateLimitKey, $windowSeconds);
                try {
                    if ($mode === 'register') {
                        $user = $this->auth->register(
                            $email,
                            (string) ($input['password'] ?? ''),
                            (string) ($input['password_confirmation'] ?? ''),
                        );
                        $this->games->claimUnowned($user->id());
                    } elseif ($this->auth->login($identifier, (string) ($input['password'] ?? '')) === null) {
                        $errors[] = 'The username, email address, or password is incorrect.';
                    }
                } catch (InvalidArgumentException $exception) {
                    $errors[] = $exception->getMessage();
                }

                if ($errors === []) {
                    $this->rateLimiter->clear($rateLimitKey);
                    header('Location: /', true, 303);
                    return;
                }
            }
        }

        $csrfToken = $this->csrf->value();
        require $this->templatePath;
    }

    /**
     * Ends the current authenticated session after CSRF validation.
     *
     * @param array<string, mixed> $input
     */
    public function logout(array $input): void
    {
        if ($this->csrf->isValid(isset($input['_token']) ? (string) $input['_token'] : null)) {
            $this->auth->logout();
        }

        header('Location: /?route=login', true, 303);
    }
}
