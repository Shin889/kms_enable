<?php 
require_once __DIR__ . '/../src/init.php';

$user = current_user();
if (!$user) {
    redirect('login.php');
}

$uid = $user['uid'];

$view_uid = isset($_GET['uid']) && in_array($user['role'], ['clerk','admin']) 
    ? (int)$_GET['uid'] 
    : $uid;

$usr = db()->prepare("SELECT uid, email, firstName, middleName, lastName, userName FROM users WHERE uid=?");
$usr->execute([$view_uid]);
$usr = $usr->fetch();

$profile = db()->prepare("SELECT * FROM applicant_profiles WHERE applicant_uid=?");
$profile->execute([$view_uid]);
$profile = $profile->fetch();

$employment = db()->prepare("SELECT * FROM employee_tracking WHERE applicant_uid=?");
$employment->execute([$view_uid]);
$employment = $employment->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uid === $view_uid && $user['role'] === 'applicant') {
    csrf_check();

    db()->prepare("UPDATE users SET firstName=?, middleName=?, lastName=?, userName=? WHERE uid=?")
       ->execute([$_POST['firstName'], $_POST['middleName'], $_POST['lastName'], $_POST['userName'], $uid]);

    if ($profile) {
        db()->prepare("UPDATE applicant_profiles SET skills=?, qualifications=?, other_details=? WHERE applicant_uid=?")
           ->execute([$_POST['skills'], $_POST['qualifications'], $_POST['other_details'], $uid]);
    } else {
        db()->prepare("INSERT INTO applicant_profiles (applicant_uid, skills, qualifications, other_details) VALUES (?,?,?,?)")
           ->execute([$uid, $_POST['skills'], $_POST['qualifications'], $_POST['other_details']]);
    }

    redirect("profile.php");
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Applicant Profile</title>
  <link rel="stylesheet" href="assets/utils/profile.css">
</head>
<body>
<div class="container">
    <h3>Applicant Profile</h3>

    <!-- User Info -->
    <div class="section">
        <h4>Basic Information</h4>
        <?php if ($uid === $view_uid && $user['role'] === 'applicant'): ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

            <label>First Name</label>
            <input type="text" name="firstName" value="<?= htmlspecialchars($usr['firstName']) ?>">

            <label>Middle Name</label>
            <input type="text" name="middleName" value="<?= htmlspecialchars($usr['middleName']) ?>">

            <label>Last Name</label>
            <input type="text" name="lastName" value="<?= htmlspecialchars($usr['lastName']) ?>">

            <label>Username</label>
            <input type="text" name="userName" value="<?= htmlspecialchars($usr['userName']) ?>">

            <label>Email</label>
            <input type="text" value="<?= htmlspecialchars($usr['email']) ?>" disabled>

            <h4>Profile Details</h4>
            <label>Skills</label>
            <textarea name="skills"><?= htmlspecialchars($profile['skills'] ?? '') ?></textarea>

            <label>Qualifications</label>
            <textarea name="qualifications"><?= htmlspecialchars($profile['qualifications'] ?? '') ?></textarea>

            <label>Other Details</label>
            <textarea name="other_details"><?= htmlspecialchars($profile['other_details'] ?? '') ?></textarea>

            <button type="submit" class="btn">Save Profile</button>
        </form>
        <?php else: ?>
            <p><strong>Name:</strong> <?= htmlspecialchars(trim($usr['firstName'].' '.$usr['lastName'])) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($usr['email']) ?></p>
            <p><strong>Skills:</strong> <?= htmlspecialchars($profile['skills'] ?? 'N/A') ?></p>
            <p><strong>Qualifications:</strong> <?= htmlspecialchars($profile['qualifications'] ?? 'N/A') ?></p>
            <p><strong>Other Details:</strong> <?= htmlspecialchars($profile['other_details'] ?? 'N/A') ?></p>
        <?php endif; ?>
    </div>

    <?php if ($employment): ?>
    <div class="section">
        <h4>Employment Status</h4>
        <p><strong>Status:</strong> <?= ucfirst(str_replace('_',' ',$employment['employment_status'])) ?></p>
        <p><strong>Start Date:</strong> <?= htmlspecialchars($employment['start_date']) ?></p>
        <p><strong>Monitoring Start:</strong> <?= htmlspecialchars($employment['monitoring_start_date']) ?></p>
        <p><strong>Promotion History:</strong> <?= htmlspecialchars($employment['promotion_history']) ?></p>
        <p><strong>Remarks:</strong> <?= htmlspecialchars($employment['remarks']) ?></p>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
