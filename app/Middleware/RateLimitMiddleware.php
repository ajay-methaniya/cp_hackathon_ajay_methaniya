<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * Simple per-user hourly rate limit using storage files.
 */
final class RateLimitMiddleware
{
    public static function checkUploads(int $userId, int $maxPerHour = 10): void
    {
        $dir = (string) config('storage.rate_limit_path');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir . '/upload_' . $userId . '.json';
        $now = time();
        $windowStart = $now - 3600;

        $entries = [];
        if (is_file($file)) {
            $raw = file_get_contents($file);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $ts) {
                        if (is_numeric($ts) && (int) $ts >= $windowStart) {
                            $entries[] = (int) $ts;
                        }
                    }
                }
            }
        }

        if (count($entries) >= $maxPerHour) {
            http_response_code(429);
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Upload rate limit exceeded. Try again later.']);
                exit;
            }
            echo 'Too many uploads. Please try again later.';
            exit;
        }

        $entries[] = $now;
        file_put_contents($file, json_encode($entries), LOCK_EX);
    }
}
