<?php require_once __DIR__ . '/../src/init.php'; csrf_check();

$token = $_GET['token'] ?? '';
$st = db()->prepare("SELECT * FROM password_resets WHERE token=? AND used=0 AND expires_at>NOW()");
$st->execute([$token]);
$row = $st->fetch();
if (!$row) { exit('Invalid or expired token'); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $pwd = $_POST['password'] ?? '';
  if (strlen($pwd) < 5) exit('Password too short');
  db()->prepare("UPDATE users SET password_hash=? WHERE uid=?")
    ->execute([password_hash($pwd,PASSWORD_DEFAULT), $row['uid']]);
  db()->prepare("UPDATE password_resets SET used=1 WHERE id=?")->execute([$row['id']]);
  redirect('login.php');
}
?>
<!doctype html><html><body>
  <link rel="stylesheet" href="assets/utils/password_reset.css">
<h3>Set New Password</h3>
<form method="post">
  <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
  <label>New Password <input type="password" name="password" required></label>
  <button>Save</button>
</form>
</body></html>
