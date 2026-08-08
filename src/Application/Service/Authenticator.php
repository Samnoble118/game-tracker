<?php

declare(strict_types=1);

/**
 * Provides registration, login, logout, and current-user operations.
 */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\User;
use GameTracker\Domain\Repository\UserRepository;
use InvalidArgumentException;

/** Coordinates secure registration, sessions, and account updates. */
final readonly class Authenticator
{
    private const SESSION_USER_ID = 'authenticated_user_id';
    private const SESSION_CREATED_AT = 'authenticated_at';
    private const SESSION_LAST_ACTIVITY = 'last_activity_at';

    /** Creates the service with registered-user persistence. */
    public function __construct(
        private UserRepository $users,
        private int $idleTimeout = 7200,
        private int $absoluteTimeout = 86400,
    ) {
    }

    /** Registers a unique user and starts their authenticated session. */
    public function register(string $email, string $password, string $confirmation): User
    {
        $email = strtolower(trim($email));

        if ($password !== $confirmation) {
            throw new InvalidArgumentException('The password confirmation does not match.');
        }

        $this->validatePassword($password);

        if ($this->users->findByEmail($email) !== null) {
            throw new InvalidArgumentException('An account already exists for that email address.');
        }

        $user = new User($email, password_hash($password, PASSWORD_DEFAULT));
        $this->users->save($user);
        $this->startSession($user);

        return $user;
    }

    /** Authenticates valid credentials or returns null when they do not match. */
    public function login(string $identifier, string $password): ?User
    {
        $identifier = trim($identifier);
        $user = $this->users->findByEmail($identifier) ?? $this->users->findByUsername($identifier);
        $passwordHash = $user?->passwordHash()
            ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

        if (!password_verify($password, $passwordHash) || $user === null) {
            return null;
        }

        if (password_needs_rehash($user->passwordHash(), PASSWORD_DEFAULT)) {
            $user->updatePasswordHash(password_hash($password, PASSWORD_DEFAULT));
            $this->users->save($user);
        }

        $this->startSession($user);
        return $user;
    }

    /** Restores the currently authenticated user from the session. */
    public function currentUser(): ?User
    {
        $id = $_SESSION[self::SESSION_USER_ID] ?? null;

        if (!is_int($id) || $this->sessionExpired()) {
            if (is_int($id)) {
                $this->logout();
            }
            return null;
        }

        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();

        return $this->users->find($id);
    }

    /** Clears the current authentication session and its cookie. */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
        }

        session_destroy();
    }

    /** Updates profile details after confirming the current password. */
    public function updateProfile(User $user, ?string $username, string $email, string $currentPassword): void
    {
        if (!password_verify($currentPassword, $user->passwordHash())) {
            throw new InvalidArgumentException('The current password is incorrect.');
        }

        $emailOwner = $this->users->findByEmail($email);
        if ($emailOwner !== null && $emailOwner->id() !== $user->id()) {
            throw new InvalidArgumentException('That email address is already registered.');
        }

        if ($username !== null && trim($username) !== '') {
            $usernameOwner = $this->users->findByUsername($username);
            if ($usernameOwner !== null && $usernameOwner->id() !== $user->id()) {
                throw new InvalidArgumentException('That username is already taken.');
            }
        }

        $user->updateEmail($email);
        $user->updateUsername($username);
        $this->users->save($user);
    }

    /** Replaces a password after validating the current password and confirmation. */
    public function updatePassword(
        User $user,
        string $currentPassword,
        string $newPassword,
        string $confirmation,
    ): void {
        if (!password_verify($currentPassword, $user->passwordHash())) {
            throw new InvalidArgumentException('The current password is incorrect.');
        }

        if ($newPassword !== $confirmation) {
            throw new InvalidArgumentException('The new password confirmation does not match.');
        }

        $this->validatePassword($newPassword, 'new password');

        $user->updatePasswordHash(password_hash($newPassword, PASSWORD_DEFAULT));
        $this->users->save($user);
        session_regenerate_id(true);
    }

    /** Regenerates the session and records the authenticated user ID. */
    private function startSession(User $user): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $user->id();
        $_SESSION[self::SESSION_CREATED_AT] = time();
        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
    }

    /** Reports whether the authenticated session exceeded either lifetime. */
    private function sessionExpired(): bool
    {
        $createdAt = $_SESSION[self::SESSION_CREATED_AT] ?? 0;
        $lastActivity = $_SESSION[self::SESSION_LAST_ACTIVITY] ?? 0;

        return !is_int($createdAt) || !is_int($lastActivity)
            || $createdAt < time() - $this->absoluteTimeout
            || $lastActivity < time() - $this->idleTimeout;
    }

    /** Enforces bounded password input and the minimum account requirement. */
    private function validatePassword(string $password, string $label = 'password'): void
    {
        if (strlen($password) < 10) {
            throw new InvalidArgumentException(sprintf('The %s must contain at least 10 characters.', $label));
        }
        if (strlen($password) > 4096) {
            throw new InvalidArgumentException(sprintf('The %s is too long.', ucfirst($label)));
        }
    }
}
