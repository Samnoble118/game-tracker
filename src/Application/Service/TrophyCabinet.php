<?php

declare(strict_types=1);

/**
 * Provides application-level use cases for manual trophy management.
 */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\Trophy;
use GameTracker\Domain\Enum\TrophyGrade;
use GameTracker\Domain\Repository\TrophyRepository;

final readonly class TrophyCabinet
{
    public function __construct(private TrophyRepository $trophies)
    {
    }

    public function add(int $gameId, string $name, TrophyGrade $grade, bool $earned): Trophy
    {
        $trophy = new Trophy($gameId, $name, $grade);
        $trophy->setEarned($earned);
        $this->trophies->save($trophy);

        return $trophy;
    }

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

    /** @return list<Trophy> */
    public function forGame(int $gameId): array
    {
        return $this->trophies->forGame($gameId);
    }
}
