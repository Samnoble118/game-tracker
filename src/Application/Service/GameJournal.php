<?php

declare(strict_types=1);

/** Coordinates ratings, play sessions, and custom game lists. */

namespace GameTracker\Application\Service;

use DateTimeImmutable;
use GameTracker\Domain\Entity\GameList;
use GameTracker\Domain\Entity\PlayLog;
use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Domain\Repository\GameJournalRepository;
use InvalidArgumentException;

/** Provides private journal operations for one authenticated user. */
final readonly class GameJournal
{
    /** Creates the journal with collection and persistence dependencies. */
    public function __construct(private GameLibrary $library, private GameJournalRepository $journals, private int $userId) {}

    /** Returns the current rating. */ public function rating(int $gameId): ?int { return $this->journals->rating($gameId, $this->userId); }
    /** Saves a 1–5 rating after verifying game ownership. */
    public function rate(int $gameId, int $rating): void
    {
        if ($this->library->find($gameId) === null) throw new InvalidArgumentException('That game could not be found.');
        if ($rating < 1 || $rating > 5) throw new InvalidArgumentException('Rating must be between 1 and 5.');
        $this->journals->saveRating($gameId, $this->userId, $rating);
    }

    /** Adds a session and synchronizes the game's latest progress. */
    public function log(int $gameId, string $playedOn, int $minutes, int $progress, string $notes): void
    {
        $game = $this->library->find($gameId);
        if ($game === null) throw new InvalidArgumentException('That game could not be found.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $playedOn);
        if ($date === false) throw new InvalidArgumentException('Choose a valid play date.');
        $this->journals->addLog(new PlayLog($gameId, $this->userId, $date, $minutes, $progress, trim($notes)));
        $status = $progress === 100 ? GameStatus::Completed
            : ($progress > 0 && $game->status() === GameStatus::Backlog ? GameStatus::Playing : $game->status());
        $this->library->updateProgress($gameId, $status, $progress);
    }

    /** Returns session history. @return list<PlayLog> */ public function logs(int $gameId): array { return $this->journals->logs($gameId, $this->userId); }
    /** Returns custom lists for the game. @return list<GameList> */ public function lists(int $gameId): array { return $this->journals->lists($gameId, $this->userId); }
    /** Creates a list and immediately adds the current game. */
    public function createList(int $gameId, string $name): void
    {
        if ($this->library->find($gameId) === null) throw new InvalidArgumentException('That game could not be found.');
        $name = trim($name);
        if (strlen($name) < 2 || strlen($name) > 60) throw new InvalidArgumentException('List names must be 2–60 characters.');
        $id = $this->journals->createList($this->userId, $name);
        $this->journals->setListMembership($id, $gameId, $this->userId, true);
    }

    /** Toggles membership in a user-owned list. */
    public function setListMembership(int $gameId, int $listId, bool $included): void
    {
        if ($this->library->find($gameId) === null) throw new InvalidArgumentException('That game could not be found.');
        $this->journals->setListMembership($listId, $gameId, $this->userId, $included);
    }
}
