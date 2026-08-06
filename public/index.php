<?php

declare(strict_types=1);

/**
 * Boots the application, wires its dependencies, and dispatches the web request.
 */

use GameTracker\Application\Http\GameController;
use GameTracker\Application\Http\TrophyController;
use GameTracker\Application\Service\GameLibrary;
use GameTracker\Application\Service\TrophyCabinet;
use GameTracker\Core\Database;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Infrastructure\Persistence\SqliteGameRepository;
use GameTracker\Infrastructure\Persistence\SqliteTrophyRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

session_start();

$root = dirname(__DIR__);
$config = require $root . '/config/app.php';
$database = Database::instance($config['database_path']);
$connection = $database->connection();
$library = new GameLibrary(new SqliteGameRepository($connection));
$csrf = new CsrfToken();

if (isset($_GET['trophies']) || isset($_POST['game_id'])) {
    $trophyController = new TrophyController(
        $library,
        new TrophyCabinet(new SqliteTrophyRepository($connection)),
        $csrf,
        $root . '/templates/trophies/index.php',
    );
    $trophyController->handle($_SERVER, $_GET, $_POST);
    return;
}

$controller = new GameController($library, new CsrfToken(), $root . '/templates/games/index.php');

$controller->handle($_SERVER, $_GET, $_POST);
