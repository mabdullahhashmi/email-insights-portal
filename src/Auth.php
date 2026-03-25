<?php

declare(strict_types=1);

final class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['insights_user']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            global $config;
            header('Location: ' . portal_url($config, '/login.php'));
            exit;
        }
    }

    public static function login(string $username, string $password, array $config): bool
    {
        $portal = $config['portal'] ?? [];
        $expectedUser = (string) ($portal['username'] ?? '');
        $passwordHash = (string) ($portal['password_hash'] ?? '');

        if ($username !== $expectedUser || $passwordHash === '') {
            return false;
        }

        if (!password_verify($password, $passwordHash)) {
            return false;
        }

        $_SESSION['insights_user'] = $username;
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }
}
