<?php require_once __DIR__ . '/../src/init.php'; csrf_check();

$info = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = $_POST['email'];
  $u = find_user_by_email($email);
  if ($u) {
    $token = bin2hex(random_bytes(32));
    db()->prepare("INSERT INTO password_resets (uid, token, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
      ->execute([$u['uid'], $token]);
    $link = APP_URL . "/password_reset.php?token=" . $token;
    notify_user($u['uid'], 'password_reset', 'Password Reset', '<p>Reset link: <a href="'.$link.'">'.$link.'</a></p>');
  }
  $info = 'If the email exists, a reset link has been sent.';
}
?>
<!doctype html><html><body>
  <link rel="stylesheet" href="assets/utils/password_reset_request.css">
<div class="container">
<h3>Password Reset</h3>
<?php if ($info) echo "<p>$info</p>"; ?>
<form method="post">
  <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
  <label>Email <input name="email" type="email" required></label>
  <button>Send Link</button>
</form>
</div>
</body></html>
