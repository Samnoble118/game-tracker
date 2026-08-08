<?php

declare(strict_types=1);

/**
 * Handles HTTP requests for listing, creating, and editing games.
 */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\GameLibrary;
use GameTracker\Application\Service\GameCoverManager;
use GameTracker\Application\Service\CollectionDetails;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Entity\User;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Domain\Enum\CollectionItemType;
use InvalidArgumentException;
use ValueError;

/**
 * Translates web input into game-library operations and renders the collection.
 */
final readonly class GameController
{
    private const VIEWS = ['all', 'owned', 'wishlist', 'playing', 'completed'];
    private const PER_PAGE = 12;
    private const PLATFORM_GROUPS = [
        'all' => 'All platforms',
        'playstation' => 'PlayStation',
        'nintendo' => 'Nintendo',
        'sega' => 'Sega',
        'xbox' => 'Xbox',
        'pc' => 'PC',
        'mobile' => 'Mobile',
        'other' => 'Other',
    ];

    /**
     * Creates the controller with its use-case, security, and view dependencies.
     */
    public function __construct(
        private GameLibrary $library,
        private CsrfToken $csrf,
        private string $templatePath,
        private User $currentUser,
        private GameCoverManager $covers,
        private CollectionDetails $details,
    ) {
    }

    /**
     * Dispatches a page view or form submission and renders the resulting state.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed> $input
     * @param array<string, mixed> $files
     */
    public function handle(array $server, array $query, array $input, array $files = []): void
    {
        $errors = [];
        $form = $this->emptyForm();
        $editingGame = null;
        $activeView = $this->activeView($query);

        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            [$errors, $form] = $this->save($input, $files);
            $activeView = $this->activeView(['view' => $input['view'] ?? 'all']);

            if ($errors === []) {
                header('Location: /?route=games&view=' . $activeView . '&saved=1', true, 303);
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
                    'barcode' => $this->details->details(CollectionItemType::Game, (int)$editingGame->id())->barcode(),
                ];
            }
        }

        $search = trim((string) ($query['q'] ?? ''));
        $activePlatform = $this->activePlatform($query);
        $statusFilter = $this->statusFilter($query);
        $totalResults = $this->library->count($activeView, $search, $activePlatform, $statusFilter);
        $totalPages = max(1, (int) ceil($totalResults / self::PER_PAGE));
        $currentPage = min(max(1, (int) ($query['page'] ?? 1)), $totalPages);
        $games = $this->library->page(
            $activeView,
            $search,
            $activePlatform,
            $statusFilter,
            self::PER_PAGE,
            ($currentPage - 1) * self::PER_PAGE,
        );
        $counts = $this->library->viewCounts();
        $totalGames = $counts['all'];
        $viewTitles = [
            'all' => 'All games',
            'owned' => 'Owned games',
            'wishlist' => 'Wishlist',
            'playing' => 'Currently playing',
            'completed' => 'Completed games',
        ];
        $statuses = GameStatus::cases();
        $platformGroups = self::PLATFORM_GROUPS;
        $platformCounts = $this->library->platformCounts($activeView);
        $filterQuery = ['route'=>'games','view' => $activeView, 'q' => $search, 'platform' => $activePlatform, 'status' => $statusFilter];
        $collectionTypes = CollectionType::cases();
        $csrfToken = $this->csrf->value();
        $saved = isset($query['saved']);
        $currentUser = $this->currentUser;

        require $this->templatePath;
    }

    /**
     * Validates form input and creates or updates the requested game.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $files
     * @return array{list<string>, array{id: string, title: string, platform: string, collection_type: string, status: string, progress: string}}
     */
    private function save(array $input, array $files = []): array
    {
        $form = [
            'id' => trim((string) ($input['id'] ?? '')),
            'title' => trim((string) ($input['title'] ?? '')),
            'platform' => trim((string) ($input['platform'] ?? '')),
            'collection_type' => trim((string) ($input['collection_type'] ?? CollectionType::Owned->value)),
            'status' => trim((string) ($input['status'] ?? GameStatus::Backlog->value)),
            'progress' => trim((string) ($input['progress'] ?? '0')),
            'barcode' => preg_replace('/\D+/', '', (string)($input['barcode'] ?? '')) ?? '',
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
            $currentId = $form['id'] === '' ? 0 : (int)$form['id'];
            if ($form['barcode'] !== '' && $this->details->duplicates($form['barcode'], CollectionItemType::Game, $currentId) !== [] && !isset($input['allow_duplicate'])) {
                throw new InvalidArgumentException('That barcode already exists in your collection. Tick the confirmation box to add an intentional duplicate.');
            }

            if ($form['id'] === '') {
                $game = $this->library->add(
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

                if (($input['cover_action'] ?? '') === 'remove') {
                    $this->covers->remove($game);
                } elseif (isset($files['cover_image']) && is_array($files['cover_image'])) {
                    $this->covers->upload($game, $files['cover_image']);
                }
            }
            $this->details->saveBarcode(CollectionItemType::Game, (int)$game->id(), $form['barcode']);
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
            'barcode' => '',
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

    /** @param array<string, mixed> $query */
    private function activePlatform(array $query): string
    {
        $platform = (string) ($query['platform'] ?? 'all');

        return array_key_exists($platform, self::PLATFORM_GROUPS) ? $platform : 'all';
    }

    /** @param array<string, mixed> $query */
    private function statusFilter(array $query): string
    {
        $status = (string) ($query['status'] ?? 'all');
        $allowed = array_map(static fn (GameStatus $case): string => $case->value, GameStatus::cases());

        return $status === 'all' || in_array($status, $allowed, true) ? $status : 'all';
    }

}
