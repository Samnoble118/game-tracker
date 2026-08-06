<?php

declare(strict_types=1);

/**
 * Renders the add/edit form and the current game collection.
 */

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;

/** @var list<Game> $games Games available for display. */
/** @var list<Game> $allGames Complete collection used for summary counts. */
/** @var list<CollectionType> $collectionTypes Collection options for the form. */
/** @var list<GameStatus> $statuses Status options for the form. */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Game Tracker</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="<?= $currentUser->dashboardImage() !== null ? 'has-dashboard-image image-mode-' . $escape($currentUser->dashboardImageMode()) : '' ?>" style="<?= $currentUser->dashboardImage() !== null ? '--dashboard-image: url(\'/?route=dashboard-image\'); --dashboard-overlay: ' . ($currentUser->dashboardOverlay() / 100) : '' ?>">
    <header class="site-header dashboard-header">
        <div>
            <p class="eyebrow">Your personal library</p>
            <h1>Game Tracker</h1>
            <p class="lede">Keep every platform, playthrough, and percentage in one place.</p>
        </div>
        <div class="summary" aria-label="Collection summary">
            <strong><?= count($allGames) ?></strong>
            <span><?= count($allGames) === 1 ? 'game' : 'games' ?></span>
        </div>
    </header>

    <div class="account-bar">
        <div class="account-identity">
            <span class="account-avatar" aria-hidden="true"><?= strtoupper(substr($currentUser->displayName(), 0, 1)) ?></span>
            <span><small>Signed in as</small><strong><?= $escape($currentUser->displayName()) ?></strong></span>
        </div>
        <a class="account-button" href="/?route=account">My Account</a>
        <form method="post" action="/?route=logout">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
            <button class="logout-button" type="submit">Sign out</button>
        </form>
    </div>

    <nav class="collection-tabs" aria-label="Game collections">
        <?php foreach ($viewTitles as $view => $title): ?>
            <a href="/?view=<?= $view ?>" class="collection-tab <?= $activeView === $view ? 'is-active' : '' ?>" <?= $activeView === $view ? 'aria-current="page"' : '' ?>>
                <span><?= $escape($title) ?></span>
                <strong><?= $counts[$view] ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

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

            <form method="post" action="/" class="game-form">
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

                <button class="primary-button" type="submit">
                    <?= $form['id'] === '' ? 'Add to collection' : 'Save changes' ?>
                </button>
            </form>
        </section>

        <section class="panel collection-panel" aria-labelledby="collection-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Dashboard view</p>
                    <h2 id="collection-title"><?= $escape($viewTitles[$activeView]) ?></h2>
                </div>
            </div>

            <?php if ($games === []): ?>
                <div class="empty-state">
                    <span aria-hidden="true">＋</span>
                    <h3>No games here yet</h3>
                    <p>Add a game or choose another collection tab.</p>
                </div>
            <?php else: ?>
                <div class="game-list">
                    <?php foreach ($games as $game): ?>
                        <article class="game-card">
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
                            <div class="progress-label">
                                <span>Progress</span>
                                <strong><?= $game->progress() ?>%</strong>
                            </div>
                            <div class="progress-track" aria-label="<?= $game->progress() ?>% complete">
                                <span style="width: <?= $game->progress() ?>%"></span>
                            </div>
                            <?php if ($game->supportsTrophies()): ?>
                                <a class="trophy-link" href="/?trophies=<?= $game->id() ?>">Manage trophies <span aria-hidden="true">→</span></a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
