<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * Ensures a user session exists for protected routes.
 */
final class AuthMiddleware
{
    public static function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            redirect('/auth/login');
        }
    }

    public static function guestOnly(): void
    {
        if (!empty($_SESSION['user_id'])) {
            redirect('/dashboard');
        }
    }
}
