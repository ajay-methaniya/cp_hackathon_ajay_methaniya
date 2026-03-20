<?php

declare(strict_types=1);

use App\Support\I18n;

/**
 * Escape HTML entities for safe output.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * URL path prefix when the app lives in a subdirectory (no trailing slash).
 */
function base_path(): string
{
    return rtrim((string) config('app.base_path', ''), '/');
}

/**
 * Full path for links and redirects, e.g. url('/dashboard') → /hackathon/cp-promptx/public/dashboard
 */
function url(string $path = '/'): string
{
    $b = base_path();
    $path = $path === '' ? '/' : '/' . ltrim($path, '/');

    return $b . $path;
}

/**
 * Request path relative to the app (for routing / active nav), without query string.
 */
function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $b = base_path();
    if ($b !== '' && str_starts_with($uri, $b)) {
        $uri = substr($uri, strlen($b)) ?: '/';
    }
    if ($uri !== '/' && str_ends_with($uri, '/')) {
        $uri = rtrim($uri, '/');
    }

    return $uri;
}

function verify_csrf(): bool
{
    $expected = (string) ($_SESSION['_csrf'] ?? '');
    if ($expected === '') {
        return false;
    }
    // Use header when set (XHR uploads); otherwise form field. If post_max_size is exceeded, $_POST may be empty.
    $fromHeader = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $token = $fromHeader !== '' ? $fromHeader : (string) ($_POST['_csrf'] ?? '');
    if ($token === '' && str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            try {
                $j = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($j) && isset($j['_csrf'])) {
                    $token = (string) $j['_csrf'];
                }
            } catch (\Throwable) {
                // ignore
            }
        }
    }
    return is_string($token) && hash_equals($expected, $token);
}

function redirect(string $path, int $code = 302): never
{
    if (!preg_match('#^https?://#i', $path)) {
        $path = url($path);
    }
    header('Location: ' . $path, true, $code);
    exit;
}

function json_response(array $data, int $code = 200): never
{
    header('Content-Type: application/json; charset=utf-8', true, $code);
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Friendly label for a Whisper / ISO 639-1 language code (upload list + transcript).
 */
function transcription_language_label(string $code): string
{
    $list = config('transcription_languages', []);
    if (is_array($list)) {
        foreach ($list as $row) {
            if (is_array($row) && ($row['code'] ?? '') === $code) {
                return (string) ($row['label'] ?? $code);
            }
        }
    }

    return strtoupper($code);
}

function app_locale(): string
{
    return I18n::normalizeLocale((string) ($_SESSION['locale'] ?? 'en'));
}

function app_theme(): string
{
    return I18n::normalizeTheme((string) ($_SESSION['theme'] ?? 'dark'));
}

function t(string $key, string $default = ''): string
{
    return I18n::translate($key, $default);
}

/**
 * User-facing hint when MySQL is missing, misconfigured, or schema not imported.
 */
function db_unavailable_message(): string
{
    return 'Database unavailable. Create the MySQL database and import database/schema.sql (see database/DATABASE.md), and set DB_* in .env to match your phpMyAdmin user and password.';
}
