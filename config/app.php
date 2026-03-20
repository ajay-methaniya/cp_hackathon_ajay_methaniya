<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'CP Prompt-X',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
    /** Subfolder before /public when the app is not at the server root, e.g. /hackathon/cp-promptx/public */
    'base_path' => rtrim((string) ($_ENV['APP_BASE_PATH'] ?? ''), '/'),
    'session_lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 120),
];
