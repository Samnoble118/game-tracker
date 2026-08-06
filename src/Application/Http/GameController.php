<?php

declare(strict_types=1);

/**
 * Handles HTTP requests for listing, creating, and editing games.
 */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\GameLibrary;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Enum\GameStatus;
use InvalidArgumentException;
use ValueError;

/**
 * Translates web input into game-library operations and renders the collection.
 */
final readonly class GameController
{
    /**
     * Creates the controller with its use-case, security, and view dependencies.
     */
    public function __construct(
        private GameLibrary $library,
        private CsrfToken $csrf,
        private string $templatePath,
    ) {
    }

    /**
     * Dispatches a page view or form submission and renders the resulting state.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed> $input
     */
    public function handle(array $server, array $query, array $input): void
    {
        $errors = [];
        $form = $this->emptyForm();
        $editingGame = null;

        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            [$errors, $form] = $this->save($input);

            if ($errors === []) {
                header('Location: /?saved=1', true, 303);
                return;
            }
        } elseif (isset($query['edit'])) {
            $editingGame = $this->library->find((int) $query['edit']);

            if ($editingGame !== null) {
                $form = [
                    'id' => (string) $editingGame->id(),
                    'title' => $editingGame->title(),
                    'platform' => $editingGame->platform(),
                    'status' => $editingGame->status()->value,
                    'progress' => (string) $editingGame->progress(),
                ];
            }
        }

        $games = $this->library->collection();
        $statuses = GameStatus::cases();
        $csrfToken = $this->csrf->value();
        $saved = isset($query['saved']);

        require $this->templatePath;
    }

    /**
     * Validates form input and creates or updates the requested game.
     *
     * @param array<string, mixed> $input
     * @return array{list<string>, array{id: string, title: string, platform: string, status: string, progress: string}}
     */
    private function save(array $input): array
    {
        $form = [
            'id' => trim((string) ($input['id'] ?? '')),
            'title' => trim((string) ($input['title'] ?? '')),
            'platform' => trim((string) ($input['platform'] ?? '')),
            'status' => trim((string) ($input['status'] ?? GameStatus::Backlog->value)),
            'progress' => trim((string) ($input['progress'] ?? '0')),
        ];

        if (!$this->csrf->isValid(isset($input['_token']) ? (string) $input['_token'] : null)) {
            return [['Your session expired. Refresh the page and try again.'], $form];
        }

        try {
            $status = GameStatus::from($form['status']);
            $progress = filter_var(
                $form['progress'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 100]],
            );

            if ($progress === false) {
                throw new InvalidArgumentException('Progress must be a whole number from 0 to 100.');
            }

            if ($form['id'] === '') {
                $this->library->add($form['title'], $form['platform'], $status, $progress);
            } else {
                $game = $this->library->update(
                    (int) $form['id'],
                    $form['title'],
                    $form['platform'],
                    $status,
                    $progress,
                );

                if ($game === null) {
                    throw new InvalidArgumentException('That game could not be found.');
                }
            }
        } catch (InvalidArgumentException|ValueError $exception) {
            return [[$exception->getMessage()], $form];
        }

        return [[], $form];
    }

    /**
     * Returns default values for a blank add-game form.
     *
     * @return array{id: string, title: string, platform: string, status: string, progress: string}
     */
    private function emptyForm(): array
    {
        return [
            'id' => '',
            'title' => '',
            'platform' => '',
            'status' => GameStatus::Backlog->value,
            'progress' => '0',
        ];
    }
}
