<?php
require_once __DIR__ . '/../src/init.php';
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $allowed_roles = ['applicant', 'clerk'];
    $role = in_array($_POST['role'] ?? '', $allowed_roles, true) ? $_POST['role'] : 'applicant';
    
    $uid = create_user($_POST, $role, true);

    notify_user(
      $uid,
      'registration',
      'Welcome to KMS RecruitHub',
      ($role === 'clerk')
      ? '<p>Your clerk account has been created and is pending admin approval.</p>'
      : '<p>Your applicant account has been created. You can now log in.</p>'
    );

    $_SESSION['flash'] = ($role === 'clerk')
      ? 'Your clerk account was created and is pending admin approval.'
      : 'Your account was created. You can now log in.';

    redirect('login.php');

  } catch (RuntimeException $ex) {
    // e.g. email_taken
    $_SESSION['flash_error'] = $ex->getMessage();
  } catch (Exception $ex) {
    $_SESSION['flash_error'] = 'Registration failed: ' . $ex->getMessage();
  }
}

// Check for flash messages
$flash = $_SESSION['flash'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_error']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | KMS Enable Recruitment</title>
  <link rel="stylesheet" href="assets/utils/register.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="register-box">
        <div class="logo-container">
            <img src="assets/images/catanduanes-state-university-removebg-preview.jpg" alt="Catanduanes State University Logo" />
        </div>
        
        <h3>Create Account</h3>
        <p class="subtitle">Join KMS Enable Recruitment to start your application</p>
        
        <?php if ($flash): ?>
            <div class="flash-message flash-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($flash) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($flash_error): ?>
            <div class="flash-message flash-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($flash_error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            
            <div class="form-group">
                <label for="role">Account Type <span>*</span></label>
                <select id="role" name="role" required>
                    <option value="applicant" selected>Applicant</option>
                    <option value="clerk">HRMPSB Staff</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">First Name <span>*</span></label>
                    <input id="firstName" name="firstName" required placeholder="Enter first name">
                </div>

                <div class="form-group">
                    <label for="lastName">Last Name <span>*</span></label>
                    <input id="lastName" name="lastName" required placeholder="Enter last name">
                </div>
            </div>

            <div class="form-group">
                <label for="middleName">Middle Name</label>
                <input id="middleName" name="middleName" placeholder="Enter middle name (optional)">
            </div>

            <div class="form-group">
                <label for="email">Email Address <span>*</span></label>
                <input id="email" name="email" type="email" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label for="userName">Username</label>
                <input id="userName" name="userName" placeholder="Choose a username (optional)">
                <span class="password-hint">Leave blank to use email as username</span>
            </div>

            <div class="form-group">
                <label for="password">Password <span>*</span></label>
                <input id="password" name="password" type="password" required placeholder="Create a secure password">
                <span class="password-hint">Use at least 8 characters with letters and numbers</span>
            </div>

            <button type="submit">
                <i class="fas fa-user-plus"></i>
                Create Account
            </button>
        </form>

        <div class="links">
            <a href="login.php">
                <i class="fas fa-sign-in-alt"></i>
                Already have an account? Login
            </a>
            <a href="index.php">
                <i class="fas fa-home"></i>
                Back to Home
            </a>
        </div>
    </div>
</body>
</html>