<?php

declare(strict_types=1);

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Domain\Repository\GameRepository;

final readonly class GameLibrary
{
    public function __construct(private GameRepository $games)
    {
    }

    public function add(
        string $title,
        string $platform,
        GameStatus $status = GameStatus::Backlog,
        int $progress = 0,
    ): Game
    {
        $game = new Game($title, $platform, $status, $progress);
        $this->games->save($game);

        return $game;
    }

    public function updateProgress(int $id, GameStatus $status, int $progress): ?Game
    {
        $game = $this->games->find($id);

        if ($game === null) {
            return null;
        }

        $game->updateStatus($status);
        $game->setProgress($progress);
        $this->games->save($game);

        return $game;
    }

    public function update(
        int $id,
        string $title,
        string $platform,
        GameStatus $status,
        int $progress,
    ): ?Game {
        $game = $this->games->find($id);

        if ($game === null) {
            return null;
        }

        $game->updateDetails($title, $platform);
        $game->updateStatus($status);
        $game->setProgress($progress);
        $this->games->save($game);

        return $game;
    }

    public function find(int $id): ?Game
    {
        return $this->games->find($id);
    }

    /** @return list<Game> */
    public function collection(): array
    {
        return $this->games->all();
    }
}
