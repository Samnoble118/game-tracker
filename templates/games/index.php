<?php

declare(strict_types=1);

/**
 * Renders the add/edit form and the current game collection.
 */

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;

/** @var list<Game> $games Games available for display. */
/** @var int $totalGames Total number of games owned by the current user. */
/** @var list<CollectionType> $collectionTypes Collection options for the form. */
/** @var list<GameStatus> $statuses Status options for the form. */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$themeStyle = "--accent:{$currentUser->themeAccent()};--bg:{$currentUser->themeBackground()};--panel:{$currentUser->themePanel()};--text:{$currentUser->themeText()};";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ArchiveXP — Games</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
    <script src="/assets/barcode.js" defer></script>
</head>
<body class="theme-<?= $escape($currentUser->themePreset()) ?> density-<?= $escape($currentUser->layoutDensity()) ?> <?= $currentUser->dashboardImage() !== null ? 'has-dashboard-image image-mode-' . $escape($currentUser->dashboardImageMode()) : '' ?>" style="<?= $escape($themeStyle) ?><?= $currentUser->dashboardImage() !== null ? '--dashboard-image:url(\'/?route=dashboard-image\');--dashboard-overlay:' . ($currentUser->dashboardOverlay()/100) : '' ?>">
    <header class="site-header dashboard-header">
        <div>
            <p class="eyebrow">Your personal library</p>
            <h1>ArchiveXP</h1>
            <p class="lede">Keep every platform, playthrough, and percentage in one place.</p>
        </div>
        <div class="summary" aria-label="Collection summary">
            <strong><?= $totalGames ?></strong>
            <span><?= $totalGames === 1 ? 'game' : 'games' ?></span>
        </div>
    </header>

    <div class="account-bar">
        <div class="account-identity">
            <span class="account-avatar" aria-hidden="true"><?= strtoupper(substr($currentUser->displayName(), 0, 1)) ?></span>
            <span><small>Signed in as</small><strong><?= $escape($currentUser->displayName()) ?></strong></span>
        </div>
        <a class="account-button" href="/?route=account">My Account</a>
        <a class="account-button" href="/?route=appearance">Appearance</a>
        <form method="post" action="/?route=logout">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
            <button class="logout-button" type="submit">Sign out</button>
        </form>
    </div>

    <nav class="section-switcher" aria-label="Tracker sections">
        <a class="is-active" href="/" aria-current="page">Games</a>
        <a href="/?route=merchandise">Merchandise</a>
    </nav>

    <nav class="collection-tabs" aria-label="Game collections">
        <?php foreach ($viewTitles as $view => $title): ?>
            <?php $viewUrl = '/?' . http_build_query([...$filterQuery, 'view' => $view]); ?>
            <a href="<?= $escape($viewUrl) ?>" class="collection-tab <?= $activeView === $view ? 'is-active' : '' ?>" <?= $activeView === $view ? 'aria-current="page"' : '' ?>>
                <span><?= $escape($title) ?></span>
                <strong><?= $counts[$view] ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="library-tools" aria-label="Filter game collection">
        <form class="search-form" method="get" action="/" data-live-search>
            <input type="hidden" name="view" value="<?= $escape($activeView) ?>">
            <input type="hidden" name="platform" value="<?= $escape($activePlatform) ?>">
            <label class="search-field"><span class="visually-hidden">Search games</span><input type="search" name="q" value="<?= $escape($search) ?>" placeholder="Search by game or platform…"></label>
            <label class="status-filter"><span class="visually-hidden">Filter by status</span><select name="status">
                <option value="all">Any status</option>
                <?php foreach ($statuses as $status): ?><option value="<?= $status->value ?>" <?= $statusFilter === $status->value ? 'selected' : '' ?>><?= ucfirst($status->value) ?></option><?php endforeach; ?>
            </select></label>
            <button class="filter-button" type="submit">Search</button>
            <button class="reset-filter" type="button" data-reset-filters>Reset</button>
        </form>
        <nav class="platform-tabs" aria-label="Console families">
            <?php foreach ($platformGroups as $platformKey => $platformTitle): ?>
                <?php $platformUrl = '/?' . http_build_query([...$filterQuery, 'platform' => $platformKey]); ?>
                <a href="<?= $escape($platformUrl) ?>" class="platform-tab <?= $activePlatform === $platformKey ? 'is-active' : '' ?>" <?= $activePlatform === $platformKey ? 'aria-current="page"' : '' ?>><?= $escape($platformTitle) ?><strong><?= $platformCounts[$platformKey] ?></strong></a>
            <?php endforeach; ?>
        </nav>
    </section>

    <main class="layout">
        <section class="panel form-panel" aria-labelledby="game-form-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow"><?= $form['id'] === '' ? 'New entry' : 'Update entry' ?></p>
                    <h2 id="game-form-title"><?= $form['id'] === '' ? 'Add a game' : 'Edit game' ?></h2>
                </div>
                <?php if ($form['id'] !== ''): ?>
                    <a class="text-link" href="/?view=<?= $activeView ?>">Cancel</a>
                <?php endif; ?>
            </div>

            <?php if ($errors !== []): ?>
                <div class="alert alert-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= $escape($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($saved): ?>
                <div class="alert alert-success" role="status">Game saved successfully.</div>
            <?php endif; ?>

            <form method="post" action="/" class="game-form" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= $escape($form['id']) ?>">
                <input type="hidden" name="view" value="<?= $escape($activeView) ?>">

                <label>
                    <span>Game title</span>
                    <input name="title" value="<?= $escape($form['title']) ?>" required maxlength="150" placeholder="e.g. Hades">
                </label>

                <label>
                    <span>Platform</span>
                    <input name="platform" value="<?= $escape($form['platform']) ?>" required maxlength="100" placeholder="e.g. PC, Switch, PS5">
                </label>

                <div class="barcode-field"><label><span>Barcode (optional)</span><input id="barcode-input" name="barcode" inputmode="numeric" pattern="[0-9 ]{8,18}" value="<?= $escape($form['barcode']) ?>" placeholder="Enter or scan 8–14 digits"></label><button class="filter-button" id="scan-barcode" type="button">Scan</button></div>
                <div class="barcode-scanner" id="barcode-scanner" hidden><video id="barcode-video" playsinline muted></video><div><p id="barcode-status">Point the camera at a barcode.</p><button class="reset-filter" id="stop-barcode" type="button">Stop camera</button></div></div>
                <label class="checkbox-label"><input type="checkbox" name="allow_duplicate" value="1"><span>Allow this barcode if it is an intentional duplicate</span></label>

                <div class="form-row form-row-three">
                    <label>
                        <span>Collection</span>
                        <select name="collection_type">
                            <?php foreach ($collectionTypes as $collectionType): ?>
                                <option value="<?= $collectionType->value ?>" <?= $form['collection_type'] === $collectionType->value ? 'selected' : '' ?>>
                                    <?= ucfirst($collectionType->value) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Status</span>
                        <select name="status">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= $status->value ?>" <?= $form['status'] === $status->value ? 'selected' : '' ?>>
                                    <?= ucfirst($status->value) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Progress (%)</span>
                        <input type="number" name="progress" value="<?= $escape($form['progress']) ?>" min="0" max="100" required>
                    </label>
                </div>

                <?php if ($editingGame !== null): ?>
                    <div class="cover-editor">
                        <label><span>Game cover</span><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"></label>
                        <p class="field-help">JPEG, PNG, or WebP, up to 5 MB.</p>
                        <?php if ($editingGame->coverImage() !== null): ?>
                            <label class="checkbox-label"><input type="checkbox" name="cover_action" value="remove"><span>Remove current cover</span></label>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <button class="primary-button" type="submit">
                    <?= $form['id'] === '' ? 'Add to collection' : 'Save changes' ?>
                </button>
            </form>
        </section>

        <section class="panel collection-panel" aria-labelledby="collection-title" aria-live="polite">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Dashboard view</p>
                    <h2 id="collection-title"><?= $escape($viewTitles[$activeView]) ?><?php if ($activePlatform !== 'all'): ?> · <?= $escape($platformGroups[$activePlatform]) ?><?php endif; ?></h2>
                    <p class="result-count"><?= $totalResults ?> <?= $totalResults === 1 ? 'game' : 'games' ?> found · page <?= $currentPage ?> of <?= $totalPages ?></p>
                </div>
            </div>

            <?php if ($games === []): ?>
                <div class="empty-state">
                    <span aria-hidden="true">＋</span>
                    <h3>No games here yet</h3>
                    <p><?= $search !== '' || $activePlatform !== 'all' || $statusFilter !== 'all' ? 'Try adjusting or clearing your filters.' : 'Add a game or choose another collection tab.' ?></p>
                </div>
            <?php else: ?>
                <div class="game-list">
                    <?php foreach ($games as $game): ?>
                        <article class="game-card">
                            <div class="game-cover <?= $game->coverImage() === null ? 'is-placeholder' : '' ?>">
                                <?php if ($game->coverImage() !== null): ?>
                                    <img src="/?route=game-cover&amp;id=<?= $game->id() ?>" alt="<?= $escape($game->title()) ?> cover">
                                <?php else: ?>
                                    <span aria-hidden="true"><?= strtoupper(substr($game->title(), 0, 1)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="game-card-content">
                                <div class="game-card-topline">
                                    <div class="card-badges">
                                        <span class="collection-badge collection-<?= $game->collectionType()->value ?>">
                                            <?= ucfirst($game->collectionType()->value) ?>
                                        </span>
                                        <span class="status status-<?= $game->status()->value ?>">
                                            <?= ucfirst($game->status()->value) ?>
                                        </span>
                                    </div>
                                    <a class="text-link" href="/?view=<?= $activeView ?>&amp;edit=<?= $game->id() ?>">Edit</a>
                                </div>
                                <h3><?= $escape($game->title()) ?></h3>
                                <p class="platform"><?= $escape($game->platform()) ?></p>
                                <div class="progress-label"><span>Progress</span><strong><?= $game->progress() ?>%</strong></div>
                                <div class="progress-track" aria-label="<?= $game->progress() ?>% complete"><span style="width: <?= $game->progress() ?>%"></span></div>
                                <div class="card-actions">
                                    <a class="journal-link" href="/?route=game&amp;id=<?= $game->id() ?>">Open game journal <span aria-hidden="true">→</span></a>
                                    <?php if ($game->supportsTrophies()): ?><a class="trophy-link" href="/?trophies=<?= $game->id() ?>">Manage trophies <span aria-hidden="true">→</span></a><?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Game pages">
                        <?php if ($currentPage > 1): ?><a href="/?<?= $escape(http_build_query([...$filterQuery, 'page' => $currentPage - 1])) ?>">← Previous</a><?php endif; ?>
                        <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
                        <?php if ($currentPage < $totalPages): ?><a href="/?<?= $escape(http_build_query([...$filterQuery, 'page' => $currentPage + 1])) ?>">Next →</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
