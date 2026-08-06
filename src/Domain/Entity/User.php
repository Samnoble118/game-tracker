<?php

declare(strict_types=1);

/**
 * Contains the authenticated user identity.
 */

namespace GameTracker\Domain\Entity;

use InvalidArgumentException;

final class User
{
    public function __construct(
        private string $email,
        private string $passwordHash,
        private ?int $id = null,
        private ?string $username = null,
    ) {
        $this->updateEmail($this->email);
        $this->updateUsername($this->username);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function username(): ?string
    {
        return $this->username;
    }

    public function displayName(): string
    {
        return $this->username ?? $this->email;
    }

    public function updateEmail(string $email): void
    {
        $email = strtolower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        $this->email = $email;
    }

    public function updateUsername(?string $username): void
    {
        $username = $username === null ? null : trim($username);

        if ($username === '') {
            $username = null;
        }

        if ($username !== null && preg_match('/^[A-Za-z0-9_-]{3,30}$/', $username) !== 1) {
            throw new InvalidArgumentException(
                'The username must be 3–30 characters using letters, numbers, underscores, or hyphens.'
            );
        }

        $this->username = $username;
    }

    public function updatePasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new InvalidArgumentException('The user already has an ID.');
        }

        $this->id = $id;
    }
}
