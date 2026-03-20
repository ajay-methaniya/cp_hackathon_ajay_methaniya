<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
} else {
    throw new RuntimeException('Run composer install in cp-promptx.');
}

if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$GLOBALS['app_config'] = [
    'app' => require $root . '/config/app.php',
    'database' => require $root . '/config/database.php',
    'openai' => require $root . '/config/openai.php',
    'storage' => require $root . '/config/storage.php',
    'sales_questions' => require $root . '/config/sales_questions.php',
    'transcription_languages' => require $root . '/config/transcription_languages.php',
];

$logPath = $GLOBALS['app_config']['storage']['logs_path'] . '/app.log';
if (!is_dir(dirname($logPath))) {
    mkdir(dirname($logPath), 0775, true);
}

$logger = new Logger('cp-promptx');
$logger->pushHandler(new StreamHandler($logPath, Level::Debug));
$GLOBALS['logger'] = $logger;

$appCfg = $GLOBALS['app_config']['app'];
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string) (($appCfg['session_lifetime'] ?? 120) * 60));
    // Root path so the session cookie is sent for all routes under this host (fixes CSRF on XHR in subfolders).
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!($appCfg['debug'] ?? false)) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}

/**
 * @return array<string, mixed>
 */
function config(string $key, mixed $default = null): mixed
{
    $parts = explode('.', $key);
    $cfg = $GLOBALS['app_config'];
    foreach ($parts as $p) {
        if (!is_array($cfg) || !array_key_exists($p, $cfg)) {
            return $default;
        }
        $cfg = $cfg[$p];
    }
    return $cfg;
}

/**
 * @param mixed $context
 */
function app_log(string $level, string $message, array $context = []): void
{
    /** @var Logger $logger */
    $logger = $GLOBALS['logger'];
    $logger->log(match (strtolower($level)) {
        'debug' => Level::Debug,
        'info' => Level::Info,
        'warning' => Level::Warning,
        'error' => Level::Error,
        default => Level::Info,
    }, $message, $context);
}
