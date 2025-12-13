<?php
require_once __DIR__ . '/../src/init.php';
require_role(['president','admin','applicant']);

if (empty($_GET['file'])) {
    http_response_code(400);
    exit('Missing file');
}

$requested = str_replace(['\\', '..'], ['/', ''], $_GET['file']);

// Uploads directory is one level up from public
$uploadsRoot = dirname(__DIR__) . '/uploads';
$fullPath = $uploadsRoot . '/' . $requested;

// For debugging - uncomment these lines temporarily
error_log("DEBUG: Requested file: " . $requested);
error_log("DEBUG: Full path: " . $fullPath);
error_log("DEBUG: Uploads root: " . $uploadsRoot);

// Check if file exists
if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found: ' . htmlspecialchars(basename($requested)) . 
         ' (Path: ' . htmlspecialchars($fullPath) . ')');
}

$fname = basename($fullPath);
$mime = mime_content_type($fullPath) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="' . $fname . '"');
readfile($fullPath);
exit;
?>