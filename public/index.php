<?php

declare(strict_types=1);

use GameTracker\Application\Service\GameLibrary;
use GameTracker\Core\Database;
use GameTracker\Infrastructure\Persistence\SqliteGameRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/config/app.php';
$database = Database::instance($config['database_path']);
$library = new GameLibrary(new SqliteGameRepository($database->connection()));
$games = $library->collection();

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Game Tracker</title>
</head>
<body>
    <main>
        <h1>Game Tracker</h1>
        <p>Track your collection, platforms, play status, and progress.</p>

        <?php if ($games === []): ?>
            <p>Your collection is empty. The add-game screen is next on the roadmap.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($games as $game): ?>
                    <li>
                        <?= htmlspecialchars($game->title(), ENT_QUOTES, 'UTF-8') ?>
                        — <?= htmlspecialchars($game->platform(), ENT_QUOTES, 'UTF-8') ?>
                        (<?= htmlspecialchars($game->status()->value, ENT_QUOTES, 'UTF-8') ?>,
                        <?= $game->progress() ?>%)
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>
</body>
</html>
