<?php

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/models/Coordinator.php';
require_once __DIR__ . '/../app/models/DutySubmission.php';

$fileId = $_GET['id'] ?? '';
if (!is_string($fileId) || !ctype_digit($fileId) || (int) $fileId < 1) {
    http_response_code(404);
    exit('Proof file not found.');
}

$file = (new DutySubmission($pdo))->fileById((int) $fileId);
if (!$file) {
    http_response_code(404);
    exit('Proof file not found.');
}

$authorized = false;
if (($_SESSION['role'] ?? '') === 'scholar') {
    $authorized = (int) $file['scholar_user_id'] === (int) $_SESSION['user_id'];
} elseif (($_SESSION['role'] ?? '') === 'coordinator') {
    $coordinator = (new Coordinator($pdo))->findByUserId((int) $_SESSION['user_id']);
    $authorized = $coordinator !== null
        && $coordinator['year_level'] === $file['year_level'];
} elseif (($_SESSION['role'] ?? '') === 'admin') {
    $authorized = true;
}

if (!$authorized) {
    http_response_code(403);
    exit('Access denied.');
}

$extensionsByMime = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];
$storedFilename = (string) $file['stored_filename'];
$mimeType = (string) $file['mime_type'];
if (
    !isset($extensionsByMime[$mimeType])
    || !preg_match('/\A[a-f0-9]{32}\.' . preg_quote($extensionsByMime[$mimeType], '/') . '\z/', $storedFilename)
) {
    http_response_code(404);
    exit('Proof file not found.');
}

$path = __DIR__ . '/../uploads/duty-submissions/' . $storedFilename;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Proof file not found.');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: inline; filename="duty-proof.' . $extensionsByMime[$mimeType] . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
readfile($path);
