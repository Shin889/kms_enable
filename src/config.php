<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost/kms-recruithub/public');

define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'kms_recruithub');
define('DB_USER', $_ENV['DB_USER'] ?? 'shin');
define('DB_PASS', $_ENV['DB_PASS'] ?? '1142');

define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_FROM', $_ENV['SMTP_FROM'] ?? 'KMS RecruitHub <no-reply@example.com>');

define('UPLOAD_DIR', dirname(__DIR__) . '/uploads');
