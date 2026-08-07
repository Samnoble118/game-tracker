<?php

declare(strict_types=1);

/**
 * Renders manual trophy management for one PlayStation game.
 */

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Entity\Trophy;
use GameTracker\Domain\Enum\TrophyGrade;

/** @var Game $game */
/** @var list<Trophy> $trophies */
/** @var list<TrophyGrade> $grades */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($game->title()) ?> Trophies — ArchiveXP</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <header class="site-header trophy-header">
        <div>
            <a class="back-link" href="/">← Back to collection</a>
            <p class="eyebrow">Manual trophy cabinet</p>
            <h1><?= $escape($game->title()) ?></h1>
            <p class="lede"><?= $escape($game->platform()) ?> · <?= $earnedCount ?> of <?= count($trophies) ?> trophies earned</p>
        </div>
        <div class="summary" aria-label="Trophy completion">
            <strong><?= $completion ?>%</strong>
            <span>complete</span>
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

    <main class="layout trophy-layout">
        <section class="panel form-panel" aria-labelledby="add-trophy-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">New trophy</p>
                    <h2 id="add-trophy-title">Add manually</h2>
                </div>
            </div>

            <?php if ($errors !== []): ?>
                <div class="alert alert-error" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= $escape($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($saved): ?><div class="alert alert-success" role="status">Trophies updated.</div><?php endif; ?>

            <form method="post" action="/?trophies=<?= $game->id() ?>" class="game-form">
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="game_id" value="<?= $game->id() ?>">
                <input type="hidden" name="action" value="add">
                <label>
                    <span>Trophy name</span>
                    <input name="name" value="<?= $escape($form['name']) ?>" required maxlength="180" placeholder="e.g. Master of Hyrule">
                </label>
                <label>
                    <span>Grade</span>
                    <select name="grade">
                        <?php foreach ($grades as $grade): ?>
                            <option value="<?= $grade->value ?>" <?= $form['grade'] === $grade->value ? 'selected' : '' ?>><?= ucfirst($grade->value) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="earned" value="1" <?= $form['earned'] ? 'checked' : '' ?>>
                    <span>Already earned</span>
                </label>
                <button class="primary-button" type="submit">Add trophy</button>
            </form>
        </section>

        <section class="panel collection-panel" aria-labelledby="trophy-list-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Trophy checklist</p>
                    <h2 id="trophy-list-title">Your progress</h2>
                </div>
            </div>

            <div class="progress-track trophy-progress" aria-label="<?= $completion ?>% of trophies earned">
                <span style="width: <?= $completion ?>%"></span>
            </div>

            <?php if ($trophies === []): ?>
                <div class="empty-state">
                    <span aria-hidden="true">◇</span>
                    <h3>No trophies added</h3>
                    <p>Add the game's trophy list manually to begin tracking.</p>
                </div>
            <?php else: ?>
                <div class="trophy-list">
                    <?php foreach ($trophies as $trophy): ?>
                        <article class="trophy-item <?= $trophy->isEarned() ? 'is-earned' : '' ?>">
                            <span class="trophy-medal trophy-<?= $trophy->grade()->value ?>" aria-hidden="true">◆</span>
                            <div class="trophy-details">
                                <h3><?= $escape($trophy->name()) ?></h3>
                                <p><?= ucfirst($trophy->grade()->value) ?><?= $trophy->earnedAt() ? ' · Earned ' . $trophy->earnedAt()->format('j M Y') : '' ?></p>
                            </div>
                            <form method="post" action="/?trophies=<?= $game->id() ?>">
                                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="game_id" value="<?= $game->id() ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="trophy_id" value="<?= $trophy->id() ?>">
                                <button class="earned-button" type="submit"><?= $trophy->isEarned() ? 'Earned ✓' : 'Mark earned' ?></button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
