var_dump($uploadsRoot, $requested, $fullPath, file_exists($fullPath));
exit;

<?php
require_once __DIR__ . '/../src/init.php';
require_role(['clerk','admin','applicant']);

if (empty($_GET['file'])) {
    http_response_code(400);
    exit('Missing file');
}

// Sanitize input
$requested = str_replace(['\\', '..'], ['/', ''], $_GET['file']);

// Base uploads folder
$uploadsRoot = __DIR__ . '/../uploads';

// Full path (no realpath)
$fullPath = $uploadsRoot . '/' . $requested;

// Security: make sure file is under uploads
if (strpos(realpath($uploadsRoot . '/' . $requested), realpath($uploadsRoot)) !== 0) {
    http_response_code(403);
    exit('Access denied');
}

// Check file exists
if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

$fname = basename($fullPath);
$mime = mime_content_type($fullPath) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="' . $fname . '"');
readfile($fullPath);
exit;
