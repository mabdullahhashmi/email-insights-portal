<?php

declare(strict_types=1);

final class Database
{
    public static function pdo(array $config): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $db = $config['db'] ?? [];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'] ?? '127.0.0.1',
            (int) ($db['port'] ?? 3306),
            $db['name'] ?? ''
        );

        $pdo = new PDO(
            $dsn,
            $db['user'] ?? '',
            $db['pass'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $pdo;
    }
}
