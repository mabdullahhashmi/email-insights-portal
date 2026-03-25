<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

$pdo = Database::pdo($config);
$token = trim((string) ($_GET['t'] ?? ''));
$sentMessageId = isset($_GET['mid']) ? (int) $_GET['mid'] : null;

if ($token !== '') {
    $recipient = TrackingService::findRecipientByToken($pdo, $token);
    if ($recipient) {
        TrackingService::trackOpen($pdo, $recipient, $sentMessageId);
    }
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: image/gif');

echo base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
