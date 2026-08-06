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
        private readonly string $email,
        private readonly string $passwordHash,
        private ?int $id = null,
    ) {
        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email address is required.');
        }
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

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new InvalidArgumentException('The user already has an ID.');
        }

        $this->id = $id;
    }
}
