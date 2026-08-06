<?php

declare(strict_types=1);

/**
 * Defines persistence operations for registered users.
 */

namespace GameTracker\Domain\Repository;

use GameTracker\Domain\Entity\User;

interface UserRepository
{
    public function save(User $user): void;

    public function find(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByUsername(string $username): ?User;
}
