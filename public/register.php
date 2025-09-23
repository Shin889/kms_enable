<?php require_once __DIR__ . '/../src/init.php'; csrf_check();
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $uid = create_applicant($_POST);
  // send confirmation email
  notify_user($uid, 'registration', 'Welcome to KMS RecruitHub', '<p>Your account has been created.</p>');
  redirect('login.php');
}
?>
<!doctype html><html><body>
    <link rel="stylesheet" type="text/css" href="assets/utils/register.css">
<form method="post">
  <h3>Register</h3>
  <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
  <label>First Name <input name="firstName" required></label>
  <label>Middle Name <input name="middleName"></label>
  <label>Last Name <input name="lastName" required></label>
  <label>Email <input name="email" type="email" required></label>
  <label>Username <input name="userName"></label>
  <label>Password <input name="password" type="password" required></label>
  <button type="submit">Create Account</button>
</form>
</body></html>