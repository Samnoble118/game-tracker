<?php

declare(strict_types=1);

/**
 * Boots the application, wires its dependencies, and dispatches the web request.
 */

use GameTracker\Application\Http\AuthController;
use GameTracker\Application\Http\AccountController;
use GameTracker\Application\Http\AppearanceController;
use GameTracker\Application\Http\CollectionDetailsController;
use GameTracker\Application\Http\DataTransferController;
use GameTracker\Application\Http\GameController;
use GameTracker\Application\Http\GameJournalController;
use GameTracker\Application\Http\FranchiseAtlasController;
use GameTracker\Application\Http\MerchandiseController;
use GameTracker\Application\Http\PublicProfileController;
use GameTracker\Application\Http\TrophyController;
use GameTracker\Application\Service\Authenticator;
use GameTracker\Application\Service\CollectionDetails;
use GameTracker\Application\Service\DashboardCustomizer;
use GameTracker\Application\Service\CollectionCsvTransfer;
use GameTracker\Application\Service\GameLibrary;
use GameTracker\Application\Service\FranchiseAtlas;
use GameTracker\Application\Service\GameJournal;
use GameTracker\Application\Service\GameCoverManager;
use GameTracker\Application\Service\MerchandiseCollection;
use GameTracker\Application\Service\PublicProfileManager;
use GameTracker\Application\Service\TrophyCabinet;
use GameTracker\Core\Database;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Core\Http\SecurityHeaders;
use GameTracker\Core\Security\RateLimiter;
use GameTracker\Infrastructure\Persistence\SqliteGameRepository;
use GameTracker\Infrastructure\Persistence\SqliteFranchiseGoalRepository;
use GameTracker\Infrastructure\Persistence\SqliteImportHistoryRepository;
use GameTracker\Infrastructure\Persistence\SqliteGameJournalRepository;
use GameTracker\Infrastructure\Persistence\SqliteCollectionMetadataRepository;
use GameTracker\Infrastructure\Persistence\SqliteMerchandiseRepository;
use GameTracker\Infrastructure\Persistence\SqliteTrophyRepository;
use GameTracker\Infrastructure\Persistence\SqliteUserRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$config = require $root . '/config/app.php';
$isHttps = str_starts_with($config['app_url'], 'https://')
    || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

ini_set('display_errors', $config['environment'] === 'local' ? '1' : '0');
ini_set('log_errors', '1');
(new SecurityHeaders())->apply($isHttps);
header_remove('X-Powered-By');
header('Cache-Control: private, no-store');

set_exception_handler(static function (Throwable $exception) use ($config): void {
    error_log(sprintf(
        '[%s] %s in %s:%d',
        gmdate('c'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
    ));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo $config['environment'] === 'local'
        ? 'Application error: ' . $exception->getMessage()
        : 'The application could not complete your request.';
});

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);

$database = Database::instance(
    $config['database_path'],
    $config['query_log_path'],
    $config['slow_query_threshold_ms'],
);
$connection = $database->connection();
$route = (string) ($_GET['route'] ?? '');

if ($route === 'health') {
    header('Content-Type: application/json; charset=utf-8');
    $healthy = $connection->query('SELECT 1')->fetchColumn() === 1;
    http_response_code($healthy ? 200 : 503);
    echo json_encode(['status' => $healthy ? 'ok' : 'unavailable'], JSON_THROW_ON_ERROR);
    return;
}

session_start();

$userRepository = new SqliteUserRepository($connection);
$gameRepository = new SqliteGameRepository($connection);
$merchandiseRepository = new SqliteMerchandiseRepository($connection);
$metadataRepository = new SqliteCollectionMetadataRepository($connection);
$franchiseGoalRepository = new SqliteFranchiseGoalRepository($connection);
$auth = new Authenticator(
    $userRepository,
    $config['session_idle_timeout'],
    $config['session_absolute_timeout'],
);
$customizer = new DashboardCustomizer($userRepository, $root . '/storage/uploads');
$profileManager = new PublicProfileManager($userRepository, $root . '/storage/uploads');
$coverManager = new GameCoverManager($gameRepository, $root . '/storage/covers');
$csrf = new CsrfToken();
$authController = new AuthController(
    $auth,
    $gameRepository,
    $csrf,
    new RateLimiter($connection),
    $root . '/templates/auth/form.php',
);
$profileController = new PublicProfileController(
    $userRepository,
    $gameRepository,
    $merchandiseRepository,
    $profileManager,
    $csrf,
    $root . '/templates/account/public-profile.php',
    $root . '/templates/public/cabinet.php',
);

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

if ($route === 'display-cabinet') {
    $profileController->cabinet($_GET);
    return;
}

if ($route === 'profile-image') {
    $owner = $userRepository->findByUsername((string)($_GET['user'] ?? ''));
    if ($owner === null) { http_response_code(404); return; }
    $profileController->image($owner, $currentUser);
    return;
}

if ($route === 'public-franchise') {
    $owner=$userRepository->findByUsername((string)($_GET['user']??''));
    if($owner===null){http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Franchise unavailable.';return;}
    $ownerGames=new GameLibrary($gameRepository,(int)$owner->id());$ownerMerchandise=new MerchandiseCollection($merchandiseRepository,(int)$owner->id());
    (new FranchiseAtlasController(new FranchiseAtlas($ownerGames,$ownerMerchandise,$metadataRepository,$franchiseGoalRepository,(int)$owner->id()),$csrf,$owner,$root.'/templates/franchises/index.php',$root.'/templates/franchises/details.php'))->publicDetails($_GET);
    return;
}

if ($currentUser === null) {
    header('Location: /?route=login', true, 303);
    return;
}

$library = new GameLibrary($gameRepository, $currentUser->id());
$merchandiseCollection = new MerchandiseCollection($merchandiseRepository, $currentUser->id());
$collectionDetails = new CollectionDetails($library, $merchandiseCollection, $metadataRepository, $currentUser->id());
$franchiseAtlasController=new FranchiseAtlasController(new FranchiseAtlas($library,$merchandiseCollection,$metadataRepository,$franchiseGoalRepository,(int)$currentUser->id()),$csrf,$currentUser,$root.'/templates/franchises/index.php',$root.'/templates/franchises/details.php');

if($route==='franchises'){$franchiseAtlasController->index($_GET);return;}
if($route==='franchise'){$franchiseAtlasController->details($_SERVER,$_GET,$_POST);return;}

if ($route === 'collection-details') {
    $detailsController = new CollectionDetailsController(
        $collectionDetails,
        $csrf,
        $currentUser,
        $root . '/templates/collection/details.php',
    );
    $detailsController->handle($_SERVER, $_GET, $_POST);
    return;
}

if ($route === 'game') {
    $journalController = new GameJournalController(
        $library,
        new GameJournal($library, new SqliteGameJournalRepository($connection), $currentUser->id()),
        $csrf,
        $currentUser,
        $root . '/templates/games/journal.php',
    );
    $journalController->handle($_SERVER, $_GET, $_POST);
    return;
}

if ($route === 'merchandise') {
    $merchandiseController = new MerchandiseController(
        $merchandiseCollection,
        $collectionDetails,
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
        $csrf,
        $root . '/templates/account/index.php',
    );
    $accountController->handle($currentUser, $_SERVER, $_GET, $_POST, $_FILES);
    return;
}

if ($route === 'appearance') {
    (new AppearanceController($customizer,$csrf,$root.'/templates/appearance/index.php'))->handle($currentUser,$_SERVER,$_GET,$_POST,$_FILES);
    return;
}

if ($route === 'profile-settings') {
    $profileController->settings($currentUser,$_SERVER,$_GET,$_POST,$_FILES);
    return;
}

if ($route === 'data-transfer') {
    (new DataTransferController(
        new CollectionCsvTransfer($library,$merchandiseCollection,$collectionDetails),
        new SqliteImportHistoryRepository($connection),
        $csrf,
        $root.'/templates/account/data-transfer.php',
    ))->handle($currentUser,$_SERVER,$_GET,$_POST,$_FILES);
    return;
}

if ($route === 'dashboard-image') {
    $imagePath = $customizer->pathFor($currentUser);
    if ($imagePath === null) {
        http_response_code(404);
        return;
    }

    header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($imagePath));
    header('Cache-Control: private, no-store');
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
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($imagePath);
    return;
}

if ($route === 'franchise-image') {
    $imagePath = $customizer->franchisePathFor($currentUser);
    if ($imagePath === null) {
        http_response_code(404);
        return;
    }

    header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($imagePath));
    header('Cache-Control: private, no-store');
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
    header('Cache-Control: private, no-store');
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
    $collectionDetails,
);

$controller->handle($_SERVER, $_GET, $_POST, $_FILES);
