<?php

declare(strict_types=1);

/**
 * Provides in-memory registered users for authentication tests.
 */

namespace GameTracker\Tests\Support;

use GameTracker\Domain\Entity\User;
use GameTracker\Domain\Repository\UserRepository;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, User> */
    private array $users = [];

    public function save(User $user): void
    {
        $user->assignId(count($this->users) + 1);
        $this->users[$user->id()] = $user;
    }

    public function find(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if (strcasecmp($user->email(), trim($email)) === 0) {
                return $user;
            }
        }

        return null;
    }
}
