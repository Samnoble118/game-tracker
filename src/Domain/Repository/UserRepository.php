<?php

declare(strict_types=1);

/**
 * Defines persistence operations for registered users.
 */

namespace GameTracker\Domain\Repository;

use GameTracker\Domain\Entity\User;

/** Defines storage operations required for registered users. */
interface UserRepository
{
    /** Creates or updates a user. */
    public function save(User $user): void;

    /** Finds a user by persisted ID. */
    public function find(int $id): ?User;

    /** Finds a user by email address. */
    public function findByEmail(string $email): ?User;

    /** Finds a user by username. */
    public function findByUsername(string $username): ?User;
}
