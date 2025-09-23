<?php
require_once __DIR__ . '/db.php';

function find_user_by_email(string $email) {
  $st = db()->prepare("SELECT * FROM users WHERE email = ?");
  $st->execute([$email]);
  return $st->fetch();
}

function create_applicant(array $data): string {
  $uid = uuid();
  $st = db()->prepare("
    INSERT INTO users (uid,email,password_hash,role,firstName,middleName,lastName,userName,account_status)
    VALUES (?,?,?,?,?,?,?,?,?)
  ");
  $st->execute([
    $uid,
    $data['email'],
    password_hash($data['password'], PASSWORD_DEFAULT),
    'applicant',
    $data['firstName'] ?? null,
    $data['middleName'] ?? null,
    $data['lastName'] ?? null,
    $data['userName'] ?? null,
    'active'
  ]);
  return $uid;
}

function login(string $email, string $password): bool {
  $u = find_user_by_email($email);
  if (!$u) return false;
  if (!password_verify($password, $u['password_hash'])) return false;
  if ($u['account_status'] !== 'active') return false;
  $_SESSION['uid']  = $u['uid'];
  $_SESSION['role'] = $u['role'];
  $_SESSION['email']= $u['email'];
  db()->prepare("UPDATE users SET last_login = NOW() WHERE uid = ?")->execute([$u['uid']]);
  return true;
}

function logout() {
  session_destroy();
}

function current_user() {
  if (!isset($_SESSION['uid'])) return null;
  $st = db()->prepare("SELECT * FROM users WHERE uid = ?");
  $st->execute([$_SESSION['uid']]);
  return $st->fetch();
}
