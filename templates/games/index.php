<?php

declare(strict_types=1);

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Enum\GameStatus;

/** @var list<Game> $games */
/** @var list<GameStatus> $statuses */

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
<body>
    <header class="site-header">
        <div>
            <p class="eyebrow">Your personal library</p>
            <h1>Game Tracker</h1>
            <p class="lede">Keep every platform, playthrough, and percentage in one place.</p>
        </div>
        <div class="summary" aria-label="Collection summary">
            <strong><?= count($games) ?></strong>
            <span><?= count($games) === 1 ? 'game' : 'games' ?></span>
        </div>
    </header>

    <main class="layout">
        <section class="panel form-panel" aria-labelledby="game-form-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow"><?= $form['id'] === '' ? 'New entry' : 'Update entry' ?></p>
                    <h2 id="game-form-title"><?= $form['id'] === '' ? 'Add a game' : 'Edit game' ?></h2>
                </div>
                <?php if ($form['id'] !== ''): ?>
                    <a class="text-link" href="/">Cancel</a>
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

                <label>
                    <span>Game title</span>
                    <input name="title" value="<?= $escape($form['title']) ?>" required maxlength="150" placeholder="e.g. Hades">
                </label>

                <label>
                    <span>Platform</span>
                    <input name="platform" value="<?= $escape($form['platform']) ?>" required maxlength="100" placeholder="e.g. PC, Switch, PS5">
                </label>

                <div class="form-row">
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
                    <p class="eyebrow">Collection</p>
                    <h2 id="collection-title">Your games</h2>
                </div>
            </div>

            <?php if ($games === []): ?>
                <div class="empty-state">
                    <span aria-hidden="true">＋</span>
                    <h3>Start your collection</h3>
                    <p>Add your first game using the form.</p>
                </div>
            <?php else: ?>
                <div class="game-list">
                    <?php foreach ($games as $game): ?>
                        <article class="game-card">
                            <div class="game-card-topline">
                                <span class="status status-<?= $game->status()->value ?>">
                                    <?= ucfirst($game->status()->value) ?>
                                </span>
                                <a class="text-link" href="/?edit=<?= $game->id() ?>">Edit</a>
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
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
