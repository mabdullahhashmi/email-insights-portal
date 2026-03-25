<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image uploaded']);
    exit;
}

$file = $_FILES['file'];
$errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errorCode !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload failed with error code ' . $errorCode]);
    exit;
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid upload payload']);
    exit;
}

$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $detected = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        if (is_string($detected)) {
            $mime = $detected;
        }
    }
}

$allowedMimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

if (!isset($allowedMimeToExt[$mime])) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPG, PNG, GIF, and WEBP are allowed']);
    exit;
}

$uploadsDir = dirname(__DIR__) . '/uploads';
if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not create upload directory']);
    exit;
}

$filename = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowedMimeToExt[$mime];
$destination = $uploadsDir . '/' . $filename;

if (!move_uploaded_file($tmpPath, $destination)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded image']);
    exit;
}

$imageUrl = portal_url($config, '/uploads/' . $filename);
echo json_encode(['location' => $imageUrl]);
