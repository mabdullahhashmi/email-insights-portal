<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

$token = trim((string) ($_GET['t'] ?? ''));
$fileParam = trim((string) ($_GET['f'] ?? ''));
$encoded = trim((string) ($_GET['u'] ?? ''));
$sentMessageId = isset($_GET['mid']) ? (int) $_GET['mid'] : null;
$relative = ltrim(str_replace('\\', '/', $fileParam), '/');

if ($relative === '' && $encoded !== '') {
    // Backward compatibility for previously generated tracked HTML.
    $source = HtmlTracker::decodeUrl($encoded);
    if ($source !== '') {
        $base = parse_url((string) ($config['base_url'] ?? ''));
        $src = parse_url($source);

        $baseHost = strtolower((string) ($base['host'] ?? ''));
        $srcHost = strtolower((string) ($src['host'] ?? ''));
        $basePath = rtrim((string) ($base['path'] ?? ''), '/');
        $srcPath = (string) ($src['path'] ?? '');

        if ($baseHost !== '' && $srcHost !== '' && $baseHost === $srcHost && strpos($srcPath, $basePath . '/uploads/') === 0) {
            $relative = substr($srcPath, strlen($basePath . '/uploads/'));
            $relative = ltrim(str_replace('\\', '/', $relative), '/');
        }
    }
}

if ($relative === '' || strpos($relative, '..') !== false) {
    http_response_code(400);
    echo 'Invalid image path';
    exit;
}

$filePath = dirname(__DIR__, 2) . '/uploads/' . $relative;
if (!is_file($filePath)) {
    http_response_code(404);
    echo 'Image not found';
    exit;
}

$pdo = Database::pdo($config);
if ($token !== '') {
    $recipient = TrackingService::findRecipientByToken($pdo, $token);
    if ($recipient) {
        TrackingService::trackOpen($pdo, $recipient, $sentMessageId, 'image');
    }
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $detected = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($filePath));
readfile($filePath);
