<?php 
require_once __DIR__ . '/../src/init.php'; 
csrf_check();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (login($_POST['email'], $_POST['password'])) {
    redirect('index.php');
  } else {
    // pull error reason from session
    $reason = get_login_error();
    switch ($reason) {
      case 'pending':
        $error = 'Your account is pending admin approval. Please wait until it is activated.';
        break;
      case 'rejected':
        $error = 'Your account was rejected. Contact the administrator for more information.';
        break;
      case 'invalid_credentials':
      default:
        $error = 'Invalid email or password.';
        break;
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" type="text/css" href="assets/utils/login.css">
  <title>Login</title>
</head>
<body>
  <div class="login-box">
      <h3>Login</h3>
      <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        
        <label>Email
          <input name="email" type="email" required>
        </label>

        <label>Password
          <input name="password" type="password" required>
        </label>

        <button type="submit">Login</button>
      </form>

      <p><a href="password_reset_request.php">Forgot password?</a></p>
  </div>
</body>
</html>
