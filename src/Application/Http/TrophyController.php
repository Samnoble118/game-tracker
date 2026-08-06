<?php

declare(strict_types=1);

/**
 * Handles trophy pages and manual trophy form submissions.
 */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\GameLibrary;
use GameTracker\Application\Service\TrophyCabinet;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Enum\TrophyGrade;
use GameTracker\Domain\Entity\User;
use InvalidArgumentException;
use ValueError;

/**
 * Translates trophy form input into use cases and renders one game's checklist.
 */
final readonly class TrophyController
{
    /**
     * Creates the controller with game, trophy, security, and view dependencies.
     */
    public function __construct(
        private GameLibrary $games,
        private TrophyCabinet $trophies,
        private CsrfToken $csrf,
        private string $templatePath,
        private User $currentUser,
    ) {
    }

    /**
     * Handles a trophy page request for one PlayStation game.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed> $input
     */
    public function handle(array $server, array $query, array $input): void
    {
        $gameId = (int) ($query['trophies'] ?? $input['game_id'] ?? 0);
        $game = $this->games->find($gameId);

        if ($game === null || !$game->supportsTrophies()) {
            http_response_code(404);
            echo 'PlayStation game not found.';
            return;
        }

        $errors = [];
        $form = ['name' => '', 'grade' => TrophyGrade::Bronze->value, 'earned' => false];

        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            [$errors, $form] = $this->save($gameId, $input);

            if ($errors === []) {
                header('Location: /?trophies=' . $gameId . '&saved=1', true, 303);
                return;
            }
        }

        $trophies = $this->trophies->forGame($gameId);
        $earnedCount = count(array_filter($trophies, static fn ($trophy): bool => $trophy->isEarned()));
        $completion = $trophies === [] ? 0 : (int) round(($earnedCount / count($trophies)) * 100);
        $grades = TrophyGrade::cases();
        $csrfToken = $this->csrf->value();
        $saved = isset($query['saved']);
        $currentUser = $this->currentUser;

        require $this->templatePath;
    }

    /**
     * Validates and performs an add or toggle trophy action.
     *
     * @param array<string, mixed> $input
     * @return array{list<string>, array{name: string, grade: string, earned: bool}}
     */
    private function save(int $gameId, array $input): array
    {
        $form = [
            'name' => trim((string) ($input['name'] ?? '')),
            'grade' => trim((string) ($input['grade'] ?? TrophyGrade::Bronze->value)),
            'earned' => isset($input['earned']),
        ];

        if (!$this->csrf->isValid(isset($input['_token']) ? (string) $input['_token'] : null)) {
            return [['Your session expired. Refresh the page and try again.'], $form];
        }

        try {
            if (($input['action'] ?? 'add') === 'toggle') {
                $trophy = $this->trophies->toggle((int) ($input['trophy_id'] ?? 0), $gameId);

                if ($trophy === null) {
                    throw new InvalidArgumentException('That trophy could not be found.');
                }
            } else {
                $this->trophies->add(
                    $gameId,
                    $form['name'],
                    TrophyGrade::from($form['grade']),
                    $form['earned'],
                );
            }
        } catch (InvalidArgumentException|ValueError $exception) {
            return [[$exception->getMessage()], $form];
        }

        return [[], $form];
    }
}
