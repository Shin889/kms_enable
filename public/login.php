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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | KMS Enable Recruitment</title>
  <link rel="stylesheet" href="assets/utils/login.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-box">
        <div class="logo-container">
            <img src="assets/images/catanduanes-state-university-removebg-preview.jpg" alt="Catanduanes State University Logo" />
        </div>
        
        <h3>Login</h3>
        <p class="subtitle">Enter your credentials to access your account</p>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required placeholder="Enter your password">
            </div>

            <button type="submit">
                <i class="fas fa-sign-in-alt"></i>
                Login
            </button>
        </form>

        <div class="links">
            <a href="password_reset_request.php">
                <i class="fas fa-key"></i>
                Forgot Password?
            </a>
            <a href="register.php">
                <i class="fas fa-user-plus"></i>
                Don't have an account? Register
            </a>
            <a href="index.php">
                <i class="fas fa-home"></i>
                Back to Home
            </a>
        </div>
    </div>
</body>
</html>