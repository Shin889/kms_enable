<?php
require_once __DIR__ . '/../src/init.php';

$user = current_user();
if (!$user) {
    redirect('login.php');
}

$uid = $user['uid'];

$view_uid = isset($_GET['uid']) && in_array($user['role'], ['clerk', 'admin'])
    ? $_GET['uid']
    : $uid;

$usr_stmt = db()->prepare("SELECT uid, email, firstName, middleName, lastName, userName FROM users WHERE uid=?");
$usr_stmt->execute([$view_uid]);
$usr = $usr_stmt->fetch(PDO::FETCH_ASSOC);

$profile_stmt = db()->prepare("SELECT * FROM applicant_profiles WHERE applicant_uid=?");
$profile_stmt->execute([$view_uid]);
$profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);

$employment_stmt = db()->prepare("SELECT * FROM employee_tracking WHERE applicant_uid=?");
$employment_stmt->execute([$view_uid]);
$employment = $employment_stmt->fetch(PDO::FETCH_ASSOC);

if (!$usr) {
    echo "<p style='color:red;'>User not found.</p>";
    exit;
}

if (!$profile) {
    $profile = [];
}

if (!$employment) {
    $employment = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uid === $view_uid && $user['role'] === 'applicant') {
    csrf_check();

    $profile_pic_path = $profile['profile_picture'] ?? null;
    if (!empty($_FILES['profile_picture']['name'])) {
        $profile_pic_path = accept_upload($_FILES['profile_picture'], 'profile_pictures');
    }

    $req_json = $profile['requirements_docs'] ?? null;
    if (!empty($_FILES['requirements']['name'][0])) {
        $uploaded = [];
        foreach ($_FILES['requirements']['name'] as $i => $name) {
            $file = [
                'name' => $_FILES['requirements']['name'][$i],
                'type' => $_FILES['requirements']['type'][$i],
                'tmp_name' => $_FILES['requirements']['tmp_name'][$i],
                'error' => $_FILES['requirements']['error'][$i],
                'size' => $_FILES['requirements']['size'][$i]
            ];
            $uploaded[] = accept_upload($file, 'requirements');
        }
        $req_json = json_encode($uploaded);
    }

    db()->prepare("UPDATE users SET firstName=?, middleName=?, lastName=?, userName=? WHERE uid=?")
        ->execute([$_POST['firstName'], $_POST['middleName'], $_POST['lastName'], $_POST['userName'], $uid]);

    $skills = !empty($_POST['skills']) ? json_encode([$_POST['skills']]) : json_encode([]);
    $qualifications = !empty($_POST['qualifications']) ? json_encode([$_POST['qualifications']]) : json_encode([]);

    if (!empty($profile)) {
        db()->prepare("UPDATE applicant_profiles 
            SET skills=?, qualifications=?, other_details=?, letter_of_intent=?, profile_picture=?, requirements_docs=? 
            WHERE applicant_uid=?")
            ->execute([
                $skills,
                $qualifications,
                $_POST['other_details'],
                $_POST['letter_of_intent'],
                $profile_pic_path,
                $req_json,
                $uid
            ]);
    } else {
        db()->prepare("INSERT INTO applicant_profiles 
            (applicant_uid, skills, qualifications, other_details, letter_of_intent, profile_picture, requirements_docs) 
            VALUES (?,?,?,?,?,?,?)")
            ->execute([
                $uid,
                $skills,
                $qualifications,
                $_POST['other_details'],
                $_POST['letter_of_intent'],
                $profile_pic_path,
                $req_json
            ]);
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

        <div class="section">
            <h4>Basic Information</h4>
            <?php if ($uid === $view_uid && $user['role'] === 'applicant'): ?>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

                    <label>First Name</label>
                    <input type="text" name="firstName" value="<?= htmlspecialchars($usr['firstName'] ?? '') ?>">

                    <label>Middle Name</label>
                    <input type="text" name="middleName" value="<?= htmlspecialchars($usr['middleName'] ?? '') ?>">

                    <label>Last Name</label>
                    <input type="text" name="lastName" value="<?= htmlspecialchars($usr['lastName'] ?? '') ?>">

                    <label>Username</label>
                    <input type="text" name="userName" value="<?= htmlspecialchars($usr['userName'] ?? '') ?>">

                    <label>Email</label>
                    <input type="text" value="<?= htmlspecialchars($usr['email'] ?? '') ?>" disabled>

                    <h4>Profile Details</h4>
                    <label>Profile Picture</label>
                    <input type="file" name="profile_picture" accept="image/*">
                    <?php if (!empty($profile['profile_picture'])): ?>
                        <p><img src="<?= htmlspecialchars($profile['profile_picture']) ?>" alt="Profile Picture"></p>
                    <?php endif; ?>

                    <?php
                    $skills = !empty($profile['skills']) ? json_decode($profile['skills'], true) : [];
                    $qualifications = !empty($profile['qualifications']) ? json_decode($profile['qualifications'], true) : [];
                    ?>
                    <label>Skills</label>
                    <textarea name="skills"><?= htmlspecialchars(implode(', ', $skills)) ?></textarea>

                    <label>Qualifications</label>
                    <textarea name="qualifications"><?= htmlspecialchars(implode(', ', $qualifications)) ?></textarea>

                    <label>Other Details</label>
                    <textarea name="other_details"><?= htmlspecialchars($profile['other_details'] ?? '') ?></textarea>

                    <label>Letter of Intent</label>
                    <textarea name="letter_of_intent"><?= htmlspecialchars($profile['letter_of_intent'] ?? '') ?></textarea>

                    <label>Requirements (upload multiple)</label>
                    <input type="file" name="requirements[]" multiple>
                    <?php if (!empty($profile['requirements_docs'])):
                        $reqs = json_decode($profile['requirements_docs'], true);
                        foreach ($reqs as $r): ?>
                            <p><a href="<?= htmlspecialchars($r) ?>" target="_blank"><?= basename($r) ?></a></p>
                        <?php endforeach;
                    endif; ?>

                    <button type="submit" class="btn">Save Profile</button>
                </form>
            <?php else: ?>
                <p><strong>Name:</strong>
                    <?= htmlspecialchars(trim(($usr['firstName'] ?? '') . ' ' . ($usr['lastName'] ?? ''))) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($usr['email'] ?? '') ?></p>
                <?php if (!empty($profile['profile_picture'])): ?>
                    <p><img src="<?= htmlspecialchars($profile['profile_picture']) ?>" alt="Profile Picture"
                            style="max-width:150px"></p>
                <?php endif; ?>
                <p><strong>Skills:</strong>
                    <?= htmlspecialchars(implode(', ', json_decode($profile['skills'] ?? '[]', true))) ?></p>
                <p><strong>Qualifications:</strong>
                    <?= htmlspecialchars(implode(', ', json_decode($profile['qualifications'] ?? '[]', true))) ?></p>
                <p><strong>Other Details:</strong> <?= htmlspecialchars($profile['other_details'] ?? 'N/A') ?></p>
                <p><strong>Letter of Intent:</strong> <?= htmlspecialchars($profile['letter_of_intent'] ?? 'N/A') ?></p>
                <?php if (!empty($profile['requirements_docs'])):
                    $reqs = json_decode($profile['requirements_docs'], true); ?>
                    <p><strong>Requirements:</strong></p>
                    <ul>
                        <?php foreach ($reqs as $r): ?>
                            <li><a href="<?= htmlspecialchars($r) ?>" target="_blank"><?= basename($r) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($employment)): ?>
            <div class="section">
                <h4>Employment Status</h4>
                <p><strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $employment['employment_status'])) ?></p>
                <p><strong>Start Date:</strong> <?= htmlspecialchars($employment['start_date'] ?? '') ?></p>
                <p><strong>Monitoring Start:</strong> <?= htmlspecialchars($employment['monitoring_start_date'] ?? '') ?>
                </p>
                <p><strong>Promotion History:</strong> <?= htmlspecialchars($employment['promotion_history'] ?? '') ?></p>
                <p><strong>Remarks:</strong> <?= htmlspecialchars($employment['remarks'] ?? '') ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>