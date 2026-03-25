<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (Auth::check()) {
    header('Location: ' . portal_url($config, '/dashboard.php'));
    exit;
}

header('Location: ' . portal_url($config, '/login.php'));
exit;
