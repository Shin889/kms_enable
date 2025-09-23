<?php require_once __DIR__ . '/../src/init.php'; require_login(); csrf_check();
$u = current_user();
$msg = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!empty($_POST['new_password'])) {
    db()->prepare("UPDATE users SET password_hash=? WHERE uid=?")
      ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $u['uid']]);
    $msg = 'Password updated.';
  }
}
?>
<!doctype html><html><body>
<h3>Account Settings</h3>
<p>Email: <?= htmlspecialchars($u['email']) ?></p>
<form method="post">
  <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
  <label>New Password <input name="new_password" type="password"></label>
  <button>Update</button>
</form>
<?php if ($msg) echo "<p style='color:green'>$msg</p>"; ?>
</body></html>
