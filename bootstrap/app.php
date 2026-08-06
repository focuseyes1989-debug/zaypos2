<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();
$dotenv->required([
    'APP_ENV',
    'APP_DEBUG',
    'APP_TIMEZONE',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
]);

date_default_timezone_set($_ENV['APP_TIMEZONE']);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', (string) ((int) ($_ENV['SESSION_LIFETIME_MINUTES'] ?? 480) * 60));

$logDirectory = BASE_PATH . '/storage/logs';
if (!is_dir($logDirectory)) {
    mkdir($logDirectory, 0775, true);
}

$logger = new Logger('zaypos2');
$logger->pushHandler(new StreamHandler($logDirectory . '/app.log', Level::Debug));

set_exception_handler(static function (Throwable $exception) use ($logger): void {
    $logger->error($exception->getMessage(), [
        'exception' => $exception,
    ]);

    http_response_code(500);

    $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL);
    $message = $debug
        ? $exception->getMessage()
        : 'An unexpected server error occurred.';

    require BASE_PATH . '/views/errors/500.php';
});

$secureSession = filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOL);

session_name($_ENV['SESSION_NAME'] ?? 'zaypos2_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secureSession,
    'httponly' => true,
    'samesite' => $_ENV['SESSION_SAMESITE'] ?? 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['_started_at'])) {
    session_regenerate_id(true);
    $_SESSION['_started_at'] = time();
}

return $logger;
