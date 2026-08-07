<?php

declare(strict_types=1);

/**
 * Boots the application, wires its dependencies, and dispatches the web request.
 */

use GameTracker\Application\Http\AuthController;
use GameTracker\Application\Http\AccountController;
use GameTracker\Application\Http\GameController;
use GameTracker\Application\Http\MerchandiseController;
use GameTracker\Application\Http\TrophyController;
use GameTracker\Application\Service\Authenticator;
use GameTracker\Application\Service\DashboardCustomizer;
use GameTracker\Application\Service\GameLibrary;
use GameTracker\Application\Service\GameCoverManager;
use GameTracker\Application\Service\MerchandiseCollection;
use GameTracker\Application\Service\TrophyCabinet;
use GameTracker\Core\Database;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Infrastructure\Persistence\SqliteGameRepository;
use GameTracker\Infrastructure\Persistence\SqliteMerchandiseRepository;
use GameTracker\Infrastructure\Persistence\SqliteTrophyRepository;
use GameTracker\Infrastructure\Persistence\SqliteUserRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

$root = dirname(__DIR__);
$config = require $root . '/config/app.php';
$database = Database::instance(
    $config['database_path'],
    $config['query_log_path'],
    $config['slow_query_threshold_ms'],
);
$connection = $database->connection();
$userRepository = new SqliteUserRepository($connection);
$gameRepository = new SqliteGameRepository($connection);
$auth = new Authenticator($userRepository);
$customizer = new DashboardCustomizer($userRepository, $root . '/storage/uploads');
$coverManager = new GameCoverManager($gameRepository, $root . '/storage/covers');
$csrf = new CsrfToken();
$authController = new AuthController($auth, $gameRepository, $csrf, $root . '/templates/auth/form.php');
$route = (string) ($_GET['route'] ?? '');

if ($route === 'login' || $route === 'register') {
    if ($auth->currentUser() !== null) {
        header('Location: /', true, 303);
        return;
    }

    $authController->form($route, $_SERVER, $_POST);
    return;
}

if ($route === 'logout' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $authController->logout($_POST);
    return;
}

$currentUser = $auth->currentUser();

if ($currentUser === null) {
    header('Location: /?route=login', true, 303);
    return;
}

$library = new GameLibrary($gameRepository, $currentUser->id());

if ($route === 'merchandise') {
    $merchandiseController = new MerchandiseController(
        new MerchandiseCollection(new SqliteMerchandiseRepository($connection), $currentUser->id()),
        $csrf,
        $root . '/templates/merchandise/index.php',
        $currentUser,
    );
    $merchandiseController->handle($_SERVER, $_GET, $_POST);
    return;
}

if ($route === 'account') {
    $accountController = new AccountController(
        $auth,
        $customizer,
        $csrf,
        $root . '/templates/account/index.php',
    );
    $accountController->handle($currentUser, $_SERVER, $_GET, $_POST, $_FILES);
    return;
}

if ($route === 'dashboard-image') {
    $imagePath = $customizer->pathFor($currentUser);
    if ($imagePath === null) {
        http_response_code(404);
        return;
    }

    header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($imagePath));
    header('Cache-Control: private, no-cache');
    header('X-Content-Type-Options: nosniff');
    readfile($imagePath);
    return;
}

if ($route === 'merchandise-image') {
    $imagePath = $customizer->merchandisePathFor($currentUser);
    if ($imagePath === null) {
        http_response_code(404);
        return;
    }

    header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($imagePath));
    header('Cache-Control: private, no-cache');
    header('X-Content-Type-Options: nosniff');
    readfile($imagePath);
    return;
}

if ($route === 'game-cover') {
    $coverGame = $library->find((int) ($_GET['id'] ?? 0));
    $coverPath = $coverGame === null ? null : $coverManager->pathFor($coverGame);
    if ($coverPath === null) {
        http_response_code(404);
        return;
    }

    header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($coverPath));
    header('Cache-Control: private, no-cache');
    header('X-Content-Type-Options: nosniff');
    readfile($coverPath);
    return;
}

if (isset($_GET['trophies']) || isset($_POST['game_id'])) {
    $trophyController = new TrophyController(
        $library,
        new TrophyCabinet(new SqliteTrophyRepository($connection)),
        $csrf,
        $root . '/templates/trophies/index.php',
        $currentUser,
    );
    $trophyController->handle($_SERVER, $_GET, $_POST);
    return;
}

$controller = new GameController(
    $library,
    $csrf,
    $root . '/templates/games/index.php',
    $currentUser,
    $coverManager,
);

$controller->handle($_SERVER, $_GET, $_POST, $_FILES);
