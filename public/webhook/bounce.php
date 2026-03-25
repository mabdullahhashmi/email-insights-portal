<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

$providedSecret = (string) ($payload['secret'] ?? ($_SERVER['HTTP_X_INSIGHTS_SECRET'] ?? ''));
$expectedSecret = (string) (($config['portal']['webhook_secret'] ?? ''));

if ($expectedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'invalid_secret']);
    exit;
}

$token = trim((string) ($payload['token'] ?? ''));
$reason = trim((string) ($payload['reason'] ?? 'Bounce received'));

if ($token === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'token_required']);
    exit;
}

$pdo = Database::pdo($config);
$updated = TrackingService::markBounced($pdo, $token, $reason);

if (!$updated) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'recipient_not_found']);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
