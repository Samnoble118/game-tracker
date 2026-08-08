<?php

declare(strict_types=1);

/** Handles private game ratings, play logs, and custom lists. */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\GameJournal;
use GameTracker\Application\Service\GameLibrary;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Entity\User;
use InvalidArgumentException;

/** Renders and updates one authenticated user's game journal page. */
final readonly class GameJournalController
{
    /** Creates the controller with journal, security, and view dependencies. */
    public function __construct(private GameLibrary $library, private GameJournal $journal, private CsrfToken $csrf, private User $currentUser, private string $templatePath) {}

    /** Processes journal actions and displays a game page. @param array<string,mixed> $server @param array<string,mixed> $query @param array<string,mixed> $input */
    public function handle(array $server, array $query, array $input): void
    {
        $gameId = (int) ($input['game_id'] ?? $query['id'] ?? 0);
        $game = $this->library->find($gameId);
        if ($game === null) { http_response_code(404); echo 'Game not found.'; return; }
        $errors = [];
        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->csrf->isValid(isset($input['_token']) ? (string)$input['_token'] : null)) {
                $errors[] = 'Your session expired. Refresh the page and try again.';
            } else {
                try {
                    match ((string)($input['action'] ?? '')) {
                        'rate' => $this->journal->rate($gameId, (int)($input['rating'] ?? 0)),
                        'log' => $this->journal->log($gameId, (string)($input['played_on'] ?? ''), (int)($input['minutes'] ?? 0), (int)($input['progress'] ?? 0), (string)($input['notes'] ?? '')),
                        'create_list' => $this->journal->createList($gameId, (string)($input['list_name'] ?? '')),
                        'list_membership' => $this->journal->setListMembership($gameId, (int)($input['list_id'] ?? 0), isset($input['included'])),
                        default => throw new InvalidArgumentException('Choose a valid journal action.'),
                    };
                } catch (InvalidArgumentException $exception) { $errors[] = $exception->getMessage(); }
                if ($errors === []) { header('Location: /?route=game&id=' . $gameId . '&saved=1', true, 303); return; }
            }
        }
        $game = $this->library->find($gameId);
        $rating = $this->journal->rating($gameId);
        $logs = $this->journal->logs($gameId);
        $lists = $this->journal->lists($gameId);
        $totalMinutes = array_sum(array_map(static fn($log): int => $log->minutes(), $logs));
        $csrfToken = $this->csrf->value();
        $saved = isset($query['saved']);
        $currentUser = $this->currentUser;
        require $this->templatePath;
    }
}
