<?php
require_once __DIR__ . '/../src/init.php';
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $role = $_POST['role'] ?? 'applicant';
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

?>
<!doctype html>
<html>

<head>
  <link rel="stylesheet" type="text/css" href="assets/utils/register.css">
</head>

<body>
  <form method="post">
    <h3>Register</h3>
    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
    <label>Role
      <select name="role" required>
        <option value="applicant">Applicant</option>
        <option value="clerk">Clerk</option>
      </select>
    </label>
    <label>First Name <input name="firstName" required></label>
    <label>Middle Name <input name="middleName"></label>
    <label>Last Name <input name="lastName" required></label>
    <label>Email <input name="email" type="email" required></label>
    <label>Username <input name="userName"></label>
    <label>Password <input name="password" type="password" required></label>

    <button type="submit">Create Account</button>
  </form>
</body>

</html>