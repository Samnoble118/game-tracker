<?php

declare(strict_types=1);

/** Defines persistence for user-owned franchise collection goals. */

namespace GameTracker\Domain\Repository;

use GameTracker\Domain\Entity\FranchiseGoal;

/** Keeps franchise goal use cases independent from SQLite. */
interface FranchiseGoalRepository
{
    /** Inserts or updates a goal. */ public function save(FranchiseGoal $goal): void;
    /** Returns goals for one franchise and user. @return list<FranchiseGoal> */ public function forFranchise(string $franchise,int $userId): array;
    /** Deletes a goal only when it belongs to the user. */ public function delete(int $id,int $userId): void;
}
