<?php

declare(strict_types=1);

/**
 * Provides registration, login, logout, and current-user operations.
 */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\User;
use GameTracker\Domain\Repository\UserRepository;
use InvalidArgumentException;

final readonly class Authenticator
{
    private const SESSION_USER_ID = 'authenticated_user_id';

    public function __construct(private UserRepository $users)
    {
    }

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

    public function login(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, $user->passwordHash())) {
            return null;
        }

        $this->startSession($user);
        return $user;
    }

    public function currentUser(): ?User
    {
        $id = $_SESSION[self::SESSION_USER_ID] ?? null;

        return is_int($id) ? $this->users->find($id) : null;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
        }

        session_destroy();
    }

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

    private function startSession(User $user): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $user->id();
    }
}
