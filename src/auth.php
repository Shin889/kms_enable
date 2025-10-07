<?php
require_once __DIR__ . '/db.php';

function find_user_by_email(string $email) {
  $st = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
  $st->execute([$email]);
  return $st->fetch(PDO::FETCH_ASSOC);
}

function email_exists(string $email): bool {
  return (bool) find_user_by_email($email);
}

function create_user(array $data, string $role = 'applicant', bool $self_register = true): string {
  $allowed_roles = ['applicant', 'clerk', 'admin'];
  if (!in_array($role, $allowed_roles, true)) {
    $role = 'applicant';
  }

  if (empty($data['email']) || empty($data['password'])) {
    throw new InvalidArgumentException('Email and password are required.');
  }

  $email = trim($data['email']);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new InvalidArgumentException('Invalid email address.');
  }

  if (email_exists($email)) {
    throw new RuntimeException('email_taken');
  }

  $uid = uuid();

  $status = ($role === 'clerk' && $self_register) ? 'pending' : 'active';

  $st = db()->prepare("
    INSERT INTO users (
      uid, email, password_hash, role, firstName, middleName, lastName, userName, account_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $st->execute([
    $uid,
    $email,
    password_hash($data['password'], PASSWORD_DEFAULT),
    $role,
    $data['firstName'] ?? null,
    $data['middleName'] ?? null,
    $data['lastName'] ?? null,
    $data['userName'] ?? null,
    $status
  ]);

  return $uid;
}

function create_applicant(array $data): string {
  return create_user($data, 'applicant', true);
}

function create_clerk(array $data, bool $self_register = true): string {
  return create_user($data, 'clerk', $self_register);
}

function login(string $email, string $password): bool {
  unset($_SESSION['login_error']);

  $u = find_user_by_email($email);
  if (!$u) {
    $_SESSION['login_error'] = 'invalid_credentials';
    return false;
  }

  if (!password_verify($password, $u['password_hash'])) {
    $_SESSION['login_error'] = 'invalid_credentials';
    return false;
  }

  if ($u['account_status'] !== 'active') {
    $_SESSION['login_error'] = $u['account_status'];
    return false;
  }

  $_SESSION['uid']   = $u['uid'];
  $_SESSION['role']  = $u['role'];
  $_SESSION['email'] = $u['email'];

  db()->prepare("UPDATE users SET last_login = NOW() WHERE uid = ?")->execute([$u['uid']]);
  return true;
}

function logout() {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params['path'], $params['domain'],
      $params['secure'], $params['httponly']
    );
  }
  session_destroy();
}

function current_user() {
  if (!isset($_SESSION['uid'])) return null;
  $st = db()->prepare("SELECT * FROM users WHERE uid = ? LIMIT 1");
  $st->execute([$_SESSION['uid']]);
  return $st->fetch(PDO::FETCH_ASSOC);
}

function get_login_error(): ?string {
  return $_SESSION['login_error'] ?? null;
}
