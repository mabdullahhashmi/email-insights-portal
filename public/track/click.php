<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

$pdo = Database::pdo($config);
$token = trim((string) ($_GET['t'] ?? ''));
$encoded = trim((string) ($_GET['u'] ?? ''));
$sentMessageId = isset($_GET['mid']) ? (int) $_GET['mid'] : null;
$target = HtmlTracker::decodeUrl($encoded);

if ($target === '' || !filter_var($target, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo 'Invalid URL';
    exit;
}

$scheme = parse_url($target, PHP_URL_SCHEME);
if (!in_array($scheme, ['http', 'https'], true)) {
    http_response_code(400);
    echo 'Invalid scheme';
    exit;
}

if ($token !== '') {
    $recipient = TrackingService::findRecipientByToken($pdo, $token);
    if ($recipient) {
        TrackingService::trackClick($pdo, $recipient, $target, $sentMessageId);
    }
}

header('Location: ' . $target, true, 302);
exit;
