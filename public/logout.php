<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
Auth::logout();
header('Location: ' . portal_url($config, '/login.php'));
exit;
