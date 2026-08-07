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

    /** Creates the service with registered-user persistence. */
    public function __construct(private UserRepository $users)
    {
    }

    /** Registers a unique user and starts their authenticated session. */
    public function register(string $email, string $password, string $confirmation): User
    {
        $email = strtolower(trim($email));

        if ($password !== $confirmation) {
            throw new InvalidArgumentException('The password confirmation does not match.');
        }

        if (strlen($password) < 10) {
            throw new InvalidArgumentException('The password must contain at least 10 characters.');
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new InvalidArgumentException('An account already exists for that email address.');
        }

        $user = new User($email, password_hash($password, PASSWORD_DEFAULT));
        $this->users->save($user);
        $this->startSession($user);

        return $user;
    }

    /** Authenticates valid credentials or returns null when they do not match. */
    public function login(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, $user->passwordHash())) {
            return null;
        }

        $this->startSession($user);
        return $user;
    }

    /** Restores the currently authenticated user from the session. */
    public function currentUser(): ?User
    {
        $id = $_SESSION[self::SESSION_USER_ID] ?? null;

        return is_int($id) ? $this->users->find($id) : null;
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

        if (strlen($newPassword) < 10) {
            throw new InvalidArgumentException('The new password must contain at least 10 characters.');
        }

        $user->updatePasswordHash(password_hash($newPassword, PASSWORD_DEFAULT));
        $this->users->save($user);
        session_regenerate_id(true);
    }

    /** Regenerates the session and records the authenticated user ID. */
    private function startSession(User $user): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $user->id();
    }
}
