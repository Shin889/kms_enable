<?php
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}
function csrf_check() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = isset($_POST['_csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf']);
    if (!$ok) { http_response_code(419); exit('CSRF validation failed'); }
  }
}
