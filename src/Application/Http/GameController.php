<?php

declare(strict_types=1);

/**
 * Handles HTTP requests for listing, creating, and editing games.
 */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\GameLibrary;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;
use InvalidArgumentException;
use ValueError;

/**
 * Translates web input into game-library operations and renders the collection.
 */
final readonly class GameController
{
    private const VIEWS = ['all', 'owned', 'wishlist', 'playing', 'completed'];

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
        $activeView = $this->activeView($query);

        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            [$errors, $form] = $this->save($input);
            $activeView = $this->activeView(['view' => $input['view'] ?? 'all']);

            if ($errors === []) {
                header('Location: /?view=' . $activeView . '&saved=1', true, 303);
                return;
            }
        } elseif (isset($query['edit'])) {
            $editingGame = $this->library->find((int) $query['edit']);

            if ($editingGame !== null) {
                $form = [
                    'id' => (string) $editingGame->id(),
                    'title' => $editingGame->title(),
                    'platform' => $editingGame->platform(),
                    'collection_type' => $editingGame->collectionType()->value,
                    'status' => $editingGame->status()->value,
                    'progress' => (string) $editingGame->progress(),
                ];
            }
        }

        $allGames = $this->library->collection();
        $games = $this->filterGames($allGames, $activeView);
        $counts = $this->viewCounts($allGames);
        $viewTitles = [
            'all' => 'All games',
            'owned' => 'Owned games',
            'wishlist' => 'Wishlist',
            'playing' => 'Currently playing',
            'completed' => 'Completed games',
        ];
        $statuses = GameStatus::cases();
        $collectionTypes = CollectionType::cases();
        $csrfToken = $this->csrf->value();
        $saved = isset($query['saved']);

        require $this->templatePath;
    }

    /**
     * Validates form input and creates or updates the requested game.
     *
     * @param array<string, mixed> $input
     * @return array{list<string>, array{id: string, title: string, platform: string, collection_type: string, status: string, progress: string}}
     */
    private function save(array $input): array
    {
        $form = [
            'id' => trim((string) ($input['id'] ?? '')),
            'title' => trim((string) ($input['title'] ?? '')),
            'platform' => trim((string) ($input['platform'] ?? '')),
            'collection_type' => trim((string) ($input['collection_type'] ?? CollectionType::Owned->value)),
            'status' => trim((string) ($input['status'] ?? GameStatus::Backlog->value)),
            'progress' => trim((string) ($input['progress'] ?? '0')),
        ];

        if (!$this->csrf->isValid(isset($input['_token']) ? (string) $input['_token'] : null)) {
            return [['Your session expired. Refresh the page and try again.'], $form];
        }

        try {
            $collectionType = CollectionType::from($form['collection_type']);
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
                $this->library->add(
                    $form['title'],
                    $form['platform'],
                    $status,
                    $progress,
                    $collectionType,
                );
            } else {
                $game = $this->library->update(
                    (int) $form['id'],
                    $form['title'],
                    $form['platform'],
                    $status,
                    $progress,
                    $collectionType,
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
     * @return array{id: string, title: string, platform: string, collection_type: string, status: string, progress: string}
     */
    private function emptyForm(): array
    {
        return [
            'id' => '',
            'title' => '',
            'platform' => '',
            'collection_type' => CollectionType::Owned->value,
            'status' => GameStatus::Backlog->value,
            'progress' => '0',
        ];
    }

    /**
     * Resolves a supported dashboard view from query parameters.
     *
     * @param array<string, mixed> $query
     */
    private function activeView(array $query): string
    {
        $view = (string) ($query['view'] ?? 'all');

        return in_array($view, self::VIEWS, true) ? $view : 'all';
    }

    /**
     * Selects the games that belong in the active dashboard view.
     *
     * @param list<Game> $games
     * @return list<Game>
     */
    private function filterGames(array $games, string $view): array
    {
        return array_values(array_filter(
            $games,
            static fn (Game $game): bool => match ($view) {
                'owned' => $game->collectionType() === CollectionType::Owned,
                'wishlist' => $game->collectionType() === CollectionType::Wishlist,
                'playing' => $game->status() === GameStatus::Playing,
                'completed' => $game->status() === GameStatus::Completed,
                default => true,
            },
        ));
    }

    /**
     * Calculates the badge count displayed for each dashboard view.
     *
     * @param list<Game> $games
     * @return array{all: int, owned: int, wishlist: int, playing: int, completed: int}
     */
    private function viewCounts(array $games): array
    {
        return [
            'all' => count($games),
            'owned' => count($this->filterGames($games, 'owned')),
            'wishlist' => count($this->filterGames($games, 'wishlist')),
            'playing' => count($this->filterGames($games, 'playing')),
            'completed' => count($this->filterGames($games, 'completed')),
        ];
    }
}
