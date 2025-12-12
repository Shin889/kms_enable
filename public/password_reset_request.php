<?php 
require_once __DIR__ . '/../src/init.php'; 
csrf_check();

$info = null;
$error = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = trim($_POST['email'] ?? '');
  
  if (empty($email)) {
    $error = 'Please enter your email address.';
  } else {
    $u = find_user_by_email($email);
    if ($u) {
      // Check if user already has a pending reset request
      $st = db()->prepare("SELECT id FROM password_resets WHERE uid=? AND used=0 AND expires_at>NOW()");
      $st->execute([$u['uid']]);
      $existing = $st->fetch();
      
      if (!$existing) {
        $token = bin2hex(random_bytes(32));
        db()->prepare("INSERT INTO password_resets (uid, token, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
          ->execute([$u['uid'], $token]);
      } else {
        // Use existing token
        $token = $existing['token'];
      }
      
      $link = APP_URL . "/password_reset.php?token=" . $token;
      notify_user($u['uid'], 'password_reset', 'Password Reset Request', 
        '<p>We received a request to reset your password for KMS Enable Recruitment.</p>
         <p>Click the link below to set a new password:</p>
         <p><a href="'.$link.'" style="display:inline-block; padding:10px 20px; background:#3b82f6; color:white; text-decoration:none; border-radius:6px;">Reset Password</a></p>
         <p>Or copy and paste this link in your browser:<br><code>'.$link.'</code></p>
         <p>This link will expire in 1 hour.</p>
         <p>If you didn\'t request this password reset, you can safely ignore this email.</p>');
    }
    // Always show the same message for security (prevent email enumeration)
    $info = 'If your email is registered, you will receive a password reset link shortly.';
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | KMS Enable Recruitment</title>
  <link rel="stylesheet" href="assets/utils/password_reset_request.css"> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="reset-request-box">
        <div class="logo-container">
            <img src="assets/images/catanduanes-state-university-removebg-preview.jpg" alt="Catanduanes State University Logo" />
        </div>
        
        <h3>Forgot Password</h3>
        <p class="subtitle">Enter your email to receive a password reset link</p>
        
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>How it works:</strong><br>
                Enter your email address and we'll send you a link to reset your password. 
                The link will be valid for 1 hour.
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($info): ?>
            <div class="info-box success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Check your email</strong><br>
                    <?= htmlspecialchars($info) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$info): ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            
            <div class="form-group">
                <label for="email">Email Address <span>*</span></label>
                <input id="email" name="email" type="email" required 
                       placeholder="Enter your registered email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <p class="instructions">Enter the email address associated with your account.</p>
            </div>

            <button type="submit">
                <i class="fas fa-paper-plane"></i>
                Send Reset Link
            </button>
        </form>
        <?php endif; ?>

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