<?php

declare(strict_types=1);

/**
 * Handles registration, login, and logout requests.
 */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\Authenticator;
use GameTracker\Core\Http\CsrfToken;
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

        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->csrf->isValid(isset($input['_token']) ? (string) $input['_token'] : null)) {
                $errors[] = 'Your session expired. Refresh the page and try again.';
            } else {
                try {
                    if ($mode === 'register') {
                        $user = $this->auth->register(
                            $email,
                            (string) ($input['password'] ?? ''),
                            (string) ($input['password_confirmation'] ?? ''),
                        );
                        $this->games->claimUnowned($user->id());
                    } elseif ($this->auth->login($email, (string) ($input['password'] ?? '')) === null) {
                        $errors[] = 'The email address or password is incorrect.';
                    }
                } catch (InvalidArgumentException $exception) {
                    $errors[] = $exception->getMessage();
                }

                if ($errors === []) {
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
