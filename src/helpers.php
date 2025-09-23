<?php
function redirect(string $path) {
  header('Location: ' . $path);
  exit;
}

function json_response($data, int $status = 200) {
  http_response_code($status);
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

function uuid(): string {
  $stmt = db()->query("SELECT UUID() as id");
  return $stmt->fetch()['id'];
}
