<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo 'Missing config/config.php. Copy config/config.example.php and update values.';
    exit;
}

$config = require $configFile;

function portal_url(array $config, string $path = ''): string
{
    $base = rtrim((string) ($config['base_url'] ?? ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/TrackingService.php';
require_once __DIR__ . '/HtmlTracker.php';
require_once __DIR__ . '/Mailer.php';
