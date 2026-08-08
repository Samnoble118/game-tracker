<?php

declare(strict_types=1);

/** Renders one user's private game journal, play history, rating, and custom lists. */

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Entity\GameList;
use GameTracker\Domain\Entity\PlayLog;
use GameTracker\Domain\Entity\User;

/** @var Game $game */
/** @var list<PlayLog> $logs */
/** @var list<GameList> $lists */
/** @var User $currentUser */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$themeStyle="--accent:{$currentUser->themeAccent()};--bg:{$currentUser->themeBackground()};--panel:{$currentUser->themePanel()};--text:{$currentUser->themeText()}";
$hours = intdiv($totalMinutes, 60);
$remainingMinutes = $totalMinutes % 60;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($game->title()) ?> Journal — ArchiveXP</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="theme-<?= $escape($currentUser->themePreset()) ?> density-<?= $escape($currentUser->layoutDensity()) ?>" style="<?= $escape($themeStyle) ?>">
    <header class="site-header journal-header">
        <div>
            <a class="back-link" href="/?route=games">← Back to games</a>
            <p class="eyebrow">Private game journal</p>
            <h1><?= $escape($game->title()) ?></h1>
            <p class="lede"><?= $escape($game->platform()) ?> · <?= ucfirst($game->status()->value) ?> · <?= $game->progress() ?>% complete</p>
        </div>
        <div class="summary" aria-label="Recorded playtime">
            <strong><?= $hours ?>h <?= $remainingMinutes ?>m</strong>
            <span>recorded</span>
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

    <nav class="section-switcher journal-switcher" aria-label="Game record sections">
        <a class="is-active" href="/?route=game&amp;id=<?= $game->id() ?>">Journal</a>
        <a href="/?route=collection-details&amp;type=game&amp;id=<?= $game->id() ?>">Collection details</a>
        <?php if ($game->supportsTrophies()): ?><a href="/?trophies=<?= $game->id() ?>">Trophies</a><?php endif; ?>
    </nav>

    <?php if ($errors !== []): ?>
        <div class="journal-alert alert alert-error" role="alert">
            <?php foreach ($errors as $error): ?><p><?= $escape($error) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($saved): ?><div class="journal-alert alert alert-success" role="status">Your game journal was updated.</div><?php endif; ?>

    <main class="journal-layout">
        <div class="journal-sidebar">
            <section class="panel" aria-labelledby="rating-title">
                <div class="panel-heading">
                    <div><p class="eyebrow">Your verdict</p><h2 id="rating-title">Game rating</h2></div>
                    <strong class="rating-value"><?= $rating === null ? '—' : $rating . '/5' ?></strong>
                </div>
                <form class="game-form" method="post" action="/?route=game&amp;id=<?= $game->id() ?>">
                    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="game_id" value="<?= $game->id() ?>">
                    <input type="hidden" name="action" value="rate">
                    <label><span>Rating from 1 to 5</span><select name="rating" required>
                        <option value="">Choose a rating</option>
                        <?php for ($score = 1; $score <= 5; $score++): ?><option value="<?= $score ?>" <?= $rating === $score ? 'selected' : '' ?>><?= str_repeat('★', $score) ?><?= str_repeat('☆', 5 - $score) ?> — <?= $score ?>/5</option><?php endfor; ?>
                    </select></label>
                    <button class="primary-button" type="submit">Save rating</button>
                </form>
            </section>

            <section class="panel" aria-labelledby="lists-title">
                <div class="panel-heading"><div><p class="eyebrow">Organise</p><h2 id="lists-title">Custom lists</h2></div></div>
                <?php if ($lists === []): ?><p class="field-help journal-help">Create themed lists such as Comfort Games, Couch Co-op, or Replay Soon.</p><?php endif; ?>
                <div class="custom-list-stack">
                    <?php foreach ($lists as $list): ?>
                        <form class="list-membership" method="post" action="/?route=game&amp;id=<?= $game->id() ?>">
                            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="game_id" value="<?= $game->id() ?>">
                            <input type="hidden" name="list_id" value="<?= $list->id() ?>">
                            <input type="hidden" name="action" value="list_membership">
                            <label class="checkbox-label"><input type="checkbox" name="included" value="1" <?= $list->containsGame() ? 'checked' : '' ?>><span><?= $escape($list->name()) ?></span></label>
                            <button class="earned-button" type="submit">Update</button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <form class="game-form list-create-form" method="post" action="/?route=game&amp;id=<?= $game->id() ?>">
                    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="game_id" value="<?= $game->id() ?>">
                    <input type="hidden" name="action" value="create_list">
                    <label><span>New list name</span><input name="list_name" minlength="2" maxlength="60" required placeholder="e.g. Replay soon"></label>
                    <button class="primary-button" type="submit">Create and add</button>
                </form>
            </section>
        </div>

        <div class="journal-main">
            <section class="panel" aria-labelledby="session-title">
                <div class="panel-heading"><div><p class="eyebrow">New entry</p><h2 id="session-title">Log a play session</h2></div></div>
                <form class="game-form" method="post" action="/?route=game&amp;id=<?= $game->id() ?>">
                    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="game_id" value="<?= $game->id() ?>">
                    <input type="hidden" name="action" value="log">
                    <div class="form-row form-row-three">
                        <label><span>Date played</span><input type="date" name="played_on" value="<?= date('Y-m-d') ?>" required></label>
                        <label><span>Minutes played</span><input type="number" name="minutes" min="0" max="1440" value="0" required></label>
                        <label><span>Progress (%)</span><input type="number" name="progress" min="0" max="100" value="<?= $game->progress() ?>" required></label>
                    </div>
                    <label><span>Private notes</span><textarea name="notes" rows="5" maxlength="2000" placeholder="What happened, where you stopped, or what to try next..."></textarea></label>
                    <p class="field-help">Progress above 0% moves a backlog game to Playing. Reaching 100% marks it Completed.</p>
                    <button class="primary-button" type="submit">Save session</button>
                </form>
            </section>

            <section class="panel" aria-labelledby="history-title">
                <div class="panel-heading"><div><p class="eyebrow">Timeline</p><h2 id="history-title">Play history</h2></div><span class="result-count"><?= count($logs) ?> <?= count($logs) === 1 ? 'session' : 'sessions' ?></span></div>
                <?php if ($logs === []): ?>
                    <div class="empty-state"><span aria-hidden="true">◇</span><h3>No sessions yet</h3><p>Record your next play session to begin this game's history.</p></div>
                <?php else: ?>
                    <div class="play-log-list">
                        <?php foreach ($logs as $log): ?>
                            <article class="play-log-item">
                                <div class="play-log-date"><strong><?= $log->playedOn()->format('j') ?></strong><span><?= $log->playedOn()->format('M Y') ?></span></div>
                                <div class="play-log-details">
                                    <h3><?= $log->minutes() ?> minutes played <span>· <?= $log->progress() ?>% progress</span></h3>
                                    <?php if ($log->notes() !== ''): ?><p><?= nl2br($escape($log->notes())) ?></p><?php else: ?><p class="muted-note">No notes recorded.</p><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
