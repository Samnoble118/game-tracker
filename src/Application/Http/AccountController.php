<?php

declare(strict_types=1);

/**
 * Handles authenticated profile and password changes.
 */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\Authenticator;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Entity\User;
use InvalidArgumentException;

/** Handles authenticated account, security, and appearance requests. */
final readonly class AccountController
{
    /** Creates the controller with account services and its template. */
    public function __construct(
        private Authenticator $auth,
        private CsrfToken $csrf,
        private string $templatePath,
    ) {
    }

    /**
     * Displays the account page and processes profile or password updates.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed> $input
     * @param array<string, mixed> $files
     */
    public function handle(User $user, array $server, array $query, array $input, array $files = []): void
    {
        $errors = [];
        $section = in_array(($input['section'] ?? 'profile'), ['password'], true)
            ? (string) $input['section'] : 'profile';

        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->csrf->isValid(isset($input['_token']) ? (string) $input['_token'] : null)) {
                $errors[] = 'Your session expired. Refresh the page and try again.';
            } else {
                try {
                    if ($section === 'password') {
                        $this->auth->updatePassword(
                            $user,
                            (string) ($input['current_password'] ?? ''),
                            (string) ($input['new_password'] ?? ''),
                            (string) ($input['new_password_confirmation'] ?? ''),
                        );
                    } else {
                        $this->auth->updateProfile(
                            $user,
                            (string) ($input['username'] ?? ''),
                            (string) ($input['email'] ?? ''),
                            (string) ($input['current_password'] ?? ''),
                        );
                    }
                } catch (InvalidArgumentException $exception) {
                    $errors[] = $exception->getMessage();
                }

                if ($errors === []) {
                    header('Location: /?route=account&saved=' . $section, true, 303);
                    return;
                }
            }
        }

        $csrfToken = $this->csrf->value();
        $saved = (string) ($query['saved'] ?? '');
        require $this->templatePath;
    }
}
