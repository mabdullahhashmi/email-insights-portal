<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$secret = trim((string) ($_GET['s'] ?? ''));
$expected = trim((string) ($config['portal']['webhook_secret'] ?? ''));

if ($secret === '' || $expected === '' || !hash_equals($expected, $secret)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$pdo = Database::pdo($config);
$dueMessages = TrackingService::listDueScheduledMessages($pdo, 200);
$result = ['processed' => 0, 'sent' => 0, 'failed' => 0];

foreach ($dueMessages as $msg) {
    $result['processed']++;

    $sendResult = Mailer::sendHtml(
        $config,
        (string) ($msg['recipient_email'] ?? ''),
        (string) ($msg['recipient_name'] ?? ''),
        (string) ($msg['subject'] ?? ''),
        (string) ($msg['tracked_html'] ?? '')
    );

    if (!empty($sendResult['ok'])) {
        TrackingService::markMessageSent($pdo, (int) $msg['id']);
        TrackingService::logEvent($pdo, (int) $msg['recipient_id'], 'sent', ['source' => 'scheduled_cron'], (int) $msg['id']);
        $result['sent']++;
    } else {
        $sendError = (string) ($sendResult['error'] ?? 'Unknown send error');
        TrackingService::markMessageFailed($pdo, (int) $msg['id'], $sendError);
        TrackingService::logEvent($pdo, (int) $msg['recipient_id'], 'send_failed', ['error' => $sendError], (int) $msg['id']);
        $result['failed']++;
    }
}

header('Content-Type: application/json');
echo json_encode($result, JSON_UNESCAPED_SLASHES);
