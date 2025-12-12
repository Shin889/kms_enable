<?php 
require_once __DIR__ . '/../src/init.php'; 
csrf_check();

$token = $_GET['token'] ?? '';
$st = db()->prepare("SELECT * FROM password_resets WHERE token=? AND used=0 AND expires_at>NOW()");
$st->execute([$token]);
$row = $st->fetch();
if (!$row) { 
    $_SESSION['flash_error'] = 'Invalid or expired password reset token.';
    redirect('password_reset_request.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $pwd = $_POST['password'] ?? '';
  if (strlen($pwd) < 8) {
    $error = 'Password must be at least 8 characters long.';
  } else {
    db()->prepare("UPDATE users SET password_hash=? WHERE uid=?")
      ->execute([password_hash($pwd,PASSWORD_DEFAULT), $row['uid']]);
    db()->prepare("UPDATE password_resets SET used=1 WHERE id=?")->execute([$row['id']]);
    
    $_SESSION['flash'] = 'Your password has been reset successfully. You can now log in with your new password.';
    redirect('login.php');
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password | KMS Enable Recruitment</title>
  <link rel="stylesheet" href="assets/utils/password_reset.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="reset-box">
        <div class="logo-container">
            <img src="assets/images/catanduanes-state-university-removebg-preview.jpg" alt="Catanduanes State University Logo" />
        </div>
        
        <h3>Set New Password</h3>
        <p class="subtitle">Create a strong password for your account</p>
        
        <div class="info-box">
            <i class="fas fa-shield-alt"></i>
            <div>
                <strong>Password Reset Request</strong><br>
                You're setting a new password for your account. Make sure it's secure and memorable.
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            
            <div class="form-group">
                <label for="password">New Password <span>*</span></label>
                <input id="password" name="password" type="password" required 
                       placeholder="Enter your new password" 
                       minlength="8"
                       autocomplete="new-password">
                
                <div class="password-requirements">
                    <h4>Password Requirements:</h4>
                    <ul>
                        <li>Minimum 8 characters long</li>
                        <li>Use a mix of letters and numbers</li>
                        <li>Avoid common passwords</li>
                        <li>Don't reuse old passwords</li>
                    </ul>
                </div>
            </div>

            <button type="submit">
                <i class="fas fa-key"></i>
                Reset Password
            </button>
        </form>

        <div class="links">
            <a href="login.php">
                <i class="fas fa-sign-in-alt"></i>
                Back to Login
            </a>
            <a href="index.php">
                <i class="fas fa-home"></i>
                Back to Home
            </a>
        </div>
    </div>
</body>
</html>