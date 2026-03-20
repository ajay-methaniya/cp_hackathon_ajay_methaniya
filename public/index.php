<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Controllers\AuthController;
use App\Controllers\CallController;
use App\Controllers\DashboardController;
use App\Controllers\PreferenceController;
use App\Controllers\ReportsController;
use App\Support\View;

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

$appBase = base_path();
if ($appBase !== '' && str_starts_with($uri, $appBase)) {
    $uri = substr($uri, strlen($appBase)) ?: '/';
}
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}

if ($method === 'GET' && $uri === '/') {
    redirect('/dashboard');
}

/**
 * @param class-string $class
 * @param list<string> $params
 */
function dispatch_controller(string $class, string $action, array $params = []): void
{
    $params = array_map(
        static fn (string $p): int|string => ctype_digit($p) ? (int) $p : $p,
        $params
    );
    $controller = new $class();
    $controller->{$action}(...$params);
}

/** @var list<array{method:string,pattern:string,handler:array{0:class-string,1:string}}> $routes */
$routes = [
    ['method' => 'GET', 'pattern' => '#^/dashboard/export$#', 'handler' => [DashboardController::class, 'exportCsv']],
    ['method' => 'GET', 'pattern' => '#^/dashboard$#', 'handler' => [DashboardController::class, 'index']],
    ['method' => 'GET', 'pattern' => '#^/api/dashboard/stats$#', 'handler' => [DashboardController::class, 'stats']],
    ['method' => 'GET', 'pattern' => '#^/api/dashboard/keywords$#', 'handler' => [DashboardController::class, 'keywords']],
    ['method' => 'GET', 'pattern' => '#^/reports$#', 'handler' => [ReportsController::class, 'index']],
    ['method' => 'GET', 'pattern' => '#^/api/reports/overview$#', 'handler' => [ReportsController::class, 'overview']],
    ['method' => 'POST', 'pattern' => '#^/preferences$#', 'handler' => [PreferenceController::class, 'update']],
    ['method' => 'GET', 'pattern' => '#^/calls$#', 'handler' => [CallController::class, 'index']],
    ['method' => 'POST', 'pattern' => '#^/calls/bulk-delete$#', 'handler' => [CallController::class, 'bulkDestroy']],
    ['method' => 'GET', 'pattern' => '#^/calls/upload$#', 'handler' => [CallController::class, 'uploadForm']],
    ['method' => 'POST', 'pattern' => '#^/calls/upload$#', 'handler' => [CallController::class, 'store']],
    ['method' => 'GET', 'pattern' => '#^/calls/(\d+)/audio$#', 'handler' => [CallController::class, 'streamAudio']],
    ['method' => 'GET', 'pattern' => '#^/api/calls/(\d+)/status$#', 'handler' => [CallController::class, 'status']],
    ['method' => 'GET', 'pattern' => '#^/calls/(\d+)$#', 'handler' => [CallController::class, 'show']],
    ['method' => 'DELETE', 'pattern' => '#^/calls/(\d+)$#', 'handler' => [CallController::class, 'destroy']],
    ['method' => 'POST', 'pattern' => '#^/calls/(\d+)/notes$#', 'handler' => [CallController::class, 'addNote']],
    ['method' => 'PATCH', 'pattern' => '#^/calls/(\d+)/followups/(\d+)$#', 'handler' => [CallController::class, 'toggleFollowUp']],
    ['method' => 'POST', 'pattern' => '#^/calls/(\d+)/followups$#', 'handler' => [CallController::class, 'addFollowUp']],
    ['method' => 'GET', 'pattern' => '#^/auth/login$#', 'handler' => [AuthController::class, 'loginForm']],
    ['method' => 'POST', 'pattern' => '#^/auth/login$#', 'handler' => [AuthController::class, 'login']],
    ['method' => 'POST', 'pattern' => '#^/auth/logout$#', 'handler' => [AuthController::class, 'logout']],
    ['method' => 'GET', 'pattern' => '#^/auth/register$#', 'handler' => [AuthController::class, 'registerForm']],
    ['method' => 'POST', 'pattern' => '#^/auth/register$#', 'handler' => [AuthController::class, 'register']],
];

foreach ($routes as $route) {
    if ($route['method'] !== $method) {
        continue;
    }
    if (preg_match($route['pattern'], $uri, $m)) {
        array_shift($m);
        [$class, $action] = $route['handler'];
        dispatch_controller($class, $action, $m);
        exit;
    }
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
View::render('errors.404', ['title' => 'Not found']);
