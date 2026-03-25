<?php

declare(strict_types=1);

return [
    'base_url' => 'https://abdullahhashmi.com/email-insights',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'email_insights',
        'user' => 'db_user',
        'pass' => 'db_pass',
    ],
    'portal' => [
        'username' => 'admin',
        // Generate with: php -r "echo password_hash('ChangeMe123!', PASSWORD_DEFAULT), PHP_EOL;"
        'password_hash' => '',
        'webhook_secret' => 'replace_with_long_random_secret',
    ],
];
