<?php
function require_login() {
  if (!isset($_SESSION['uid'])) { redirect('login.php'); }
}

function require_role(array $roles) {
  require_login();
  if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
    http_response_code(403);
    exit('Forbidden');
  }
}
