<?php

declare(strict_types=1);

/**
 * Provides application-level use cases for manual trophy management.
 */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\Trophy;
use GameTracker\Domain\Enum\TrophyGrade;
use GameTracker\Domain\Repository\TrophyRepository;

/**
 * Coordinates trophy entities and persistence for manual tracking operations.
 */
final readonly class TrophyCabinet
{
    /**
     * Creates the service with a replaceable trophy repository.
     */
    public function __construct(private TrophyRepository $trophies)
    {
    }

    /**
     * Adds a trophy to a game and optionally marks it already earned.
     */
    public function add(int $gameId, string $name, TrophyGrade $grade, bool $earned): Trophy
    {
        $trophy = new Trophy($gameId, $name, $grade);
        $trophy->setEarned($earned);
        $this->trophies->save($trophy);

        return $trophy;
    }

    /**
     * Toggles earned state after confirming the trophy belongs to the game.
     */
    public function toggle(int $id, int $gameId): ?Trophy
    {
        $trophy = $this->trophies->find($id);

        if ($trophy === null || $trophy->gameId() !== $gameId) {
            return null;
        }

        $trophy->setEarned(!$trophy->isEarned());
        $this->trophies->save($trophy);

        return $trophy;
    }

    /**
     * Returns all trophies belonging to one game.
     *
     * @return list<Trophy>
     */
    public function forGame(int $gameId): array
    {
        return $this->trophies->forGame($gameId);
    }
}
