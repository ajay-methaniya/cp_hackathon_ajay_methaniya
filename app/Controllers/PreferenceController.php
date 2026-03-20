<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Support\I18n;

final class PreferenceController extends BaseController
{
    public function update(): void
    {
        AuthMiddleware::requireAuth();
        if (!verify_csrf()) {
            $this->json(['error' => 'Invalid CSRF'], 403);
        }

        $raw = file_get_contents('php://input');
        $payload = [];
        if (is_string($raw) && $raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            } catch (\Throwable) {
                $payload = [];
            }
        } else {
            $payload = $_POST;
        }

        if (isset($payload['theme'])) {
            $_SESSION['theme'] = I18n::normalizeTheme((string) $payload['theme']);
        }
        if (isset($payload['locale'])) {
            $_SESSION['locale'] = I18n::normalizeLocale((string) $payload['locale']);
        }

        $this->json([
            'ok' => true,
            'theme' => (string) ($_SESSION['theme'] ?? 'dark'),
            'locale' => (string) ($_SESSION['locale'] ?? 'en'),
        ]);
    }
}

