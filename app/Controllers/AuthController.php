<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\User;
use PDOException;

final class AuthController extends BaseController
{
    public function loginForm(): void
    {
        AuthMiddleware::guestOnly();
        $this->view('auth.login', ['title' => 'Sign in'], 'auth');
    }

    public function login(): void
    {
        AuthMiddleware::guestOnly();
        if (!verify_csrf()) {
            $_SESSION['flash_error'] = 'Invalid session. Please try again.';
            redirect('/auth/login');
        }
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        try {
            $user = User::findByEmail($email);
        } catch (PDOException $e) {
            app_log('error', 'Login DB error', ['message' => $e->getMessage()]);
            $_SESSION['flash_error'] = db_unavailable_message();
            redirect('/auth/login');
        }
        if ($user === null || !password_verify($password, (string) $user['password'])) {
            $_SESSION['flash_error'] = 'Invalid credentials.';
            redirect('/auth/login');
        }
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = (string) $user['name'];
        $_SESSION['user_role'] = (string) $user['role'];
        redirect('/dashboard');
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/dashboard');
        }
        if (!verify_csrf()) {
            redirect('/dashboard');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
        redirect('/auth/login');
    }

    public function registerForm(): void
    {
        AuthMiddleware::guestOnly();
        $this->view('auth.register', ['title' => 'Create account'], 'auth');
    }

    public function register(): void
    {
        AuthMiddleware::guestOnly();
        if (!verify_csrf()) {
            $_SESSION['flash_error'] = 'Invalid session. Please try again.';
            redirect('/auth/register');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || $email === '' || strlen($password) < 8) {
            $_SESSION['flash_error'] = 'Please provide name, a valid email, and a password of at least 8 characters.';
            redirect('/auth/register');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Please enter a valid email address.';
            redirect('/auth/register');
        }
        try {
            if (User::findByEmail($email) !== null) {
                $_SESSION['flash_error'] = 'Email already registered.';
                redirect('/auth/register');
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $id = User::create($name, $email, $hash, 'agent');
        } catch (PDOException $e) {
            app_log('error', 'Register DB error', ['message' => $e->getMessage()]);
            $_SESSION['flash_error'] = db_unavailable_message();
            redirect('/auth/register');
        }
        $_SESSION['user_id'] = $id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = 'agent';
        redirect('/dashboard');
    }
}
