<?php

declare(strict_types=1);

/** Defines persistence for ratings, play logs, and custom lists. */

namespace GameTracker\Domain\Repository;

use GameTracker\Domain\Entity\GameList;
use GameTracker\Domain\Entity\PlayLog;

/** Keeps journal use cases independent from a specific database. */
interface GameJournalRepository
{
    /** Returns a user's rating for a game. */ public function rating(int $gameId, int $userId): ?int;
    /** Creates or replaces a user's rating. */ public function saveRating(int $gameId, int $userId, int $rating): void;
    /** Persists one play session. */ public function addLog(PlayLog $log): void;
    /** Returns newest sessions first. @return list<PlayLog> */ public function logs(int $gameId, int $userId): array;
    /** Creates a custom list and returns its ID. */ public function createList(int $userId, string $name): int;
    /** Returns lists with membership for the selected game. @return list<GameList> */ public function lists(int $gameId, int $userId): array;
    /** Adds or removes a game from a user-owned list. */ public function setListMembership(int $listId, int $gameId, int $userId, bool $included): void;
}
