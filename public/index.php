<?php

declare(strict_types=1);

use GameTracker\Application\Http\GameController;
use GameTracker\Application\Service\GameLibrary;
use GameTracker\Core\Database;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Infrastructure\Persistence\SqliteGameRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

session_start();

$root = dirname(__DIR__);
$config = require $root . '/config/app.php';
$database = Database::instance($config['database_path']);
$library = new GameLibrary(new SqliteGameRepository($database->connection()));
$controller = new GameController($library, new CsrfToken(), $root . '/templates/games/index.php');

$controller->handle($_SERVER, $_GET, $_POST);
