<?php
require_once __DIR__ . '/../src/init.php';
require_role(['clerk', 'admin']);
csrf_check();

function download_url($path)
{
  if (!$path)
    return null;
  $path = str_replace('\\', '/', $path); 
  return 'download_all.php?file=' . urlencode($path);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['application_id'])) {
  $appId = (int) $_POST['application_id'];
  $clerkUid = $_SESSION['uid'];

  if ($_POST['action'] === 'approve') {
    db()->prepare("
            UPDATE applications
            SET status = 'approved_by_hrmpsb',
                approval_rejection_reason = NULL,
                approved_by_uid = ?
            WHERE application_id = ?
        ")->execute([$clerkUid, $appId]);

    $stmt = db()->prepare("SELECT applicant_uid FROM applications WHERE application_id = ?");
    $stmt->execute([$appId]);
    $uid = $stmt->fetchColumn();

    notify_user(
      $uid,
      'application_approved_hrmpsb',
      'Your application passed initial review',
      '<p>Approved by HRMPSB.</p>',
      $clerkUid
    );

  } elseif ($_POST['action'] === 'reject') {
    $reason = trim($_POST['reason'] ?? '');
    db()->prepare("
            UPDATE applications
            SET status = 'rejected_by_clerk',
                approval_rejection_reason = ?,
                approved_by_uid = ?
            WHERE application_id = ?
        ")->execute([$reason, $clerkUid, $appId]);

    $stmt = db()->prepare("SELECT applicant_uid FROM applications WHERE application_id = ?");
    $stmt->execute([$appId]);
    $uid = $stmt->fetchColumn();

    notify_user(
      $uid,
      'application_rejected_clerk',
      'Application Rejected',
      '<p>Reason: ' . htmlspecialchars($reason) . '</p>',
      $clerkUid
    );
  }

  $jobId = $_POST['job_id'] ?? 0;
  redirect('applications_review.php?job_id=' . urlencode($jobId));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rank_value'], $_POST['rank_application_id'])) {
  $rank = (int) $_POST['rank_value'];
  $appId = (int) $_POST['rank_application_id'];

  if (current_user()['role'] === 'clerk') {
    $stmt = db()->prepare("UPDATE applications SET rank = ? WHERE application_id = ?");
    $stmt->execute([$rank, $appId]);
  }

  $jobId = $_POST['job_id'] ?? 0;
  redirect('applications_review.php?job_id=' . urlencode($jobId));
}

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;

if ($jobId > 0) {
  $stmt = db()->prepare("
        SELECT a.*, u.email AS applicant_email, j.job_title
        FROM applications a
        JOIN users u ON u.uid = a.applicant_uid
        JOIN job_vacancies j ON j.vacancy_id = a.vacancy_id
        WHERE j.vacancy_id = ?
        ORDER BY COALESCE(a.rank, 9999), a.date_applied ASC
    ");
  $stmt->execute([$jobId]);
  $apps = $stmt->fetchAll();

  $jobTitleStmt = db()->prepare("SELECT job_title FROM job_vacancies WHERE vacancy_id = ?");
  $jobTitleStmt->execute([$jobId]);
  $jobTitle = $jobTitleStmt->fetchColumn();
} else {
  $apps = [];
  $jobTitle = "Unknown Job";
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Applications Review - <?= htmlspecialchars($jobTitle) ?></title>
  <link rel="stylesheet" type="text/css" href="assets/utils/applications_review.css">
  <style>

  </style>
</head>

<body>

  <h3>Applications for <?= htmlspecialchars($jobTitle) ?></h3>

  <div id="print-area">
    <table border="1" cellpadding="6" cellspacing="0">
      <tr>
        <th>ID</th>
        <th>Applicant</th>
        <th>Status</th>
        <th>Files</th>
        <th>Rank</th>
        <th>Actions</th>
      </tr>

      <?php if (empty($apps)): ?>
        <tr>
          <td colspan="6" style="text-align:center;">No applications found for this job.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($apps as $a): ?>
          <tr>
            <td><?= $a['application_id'] ?></td>
            <td><?= htmlspecialchars($a['applicant_email']) ?></td>
            <td><?= htmlspecialchars($a['status']) ?></td>
            <td>
              <?php
              if (!empty($a['resume_path'])):
                echo '<a href="' . htmlspecialchars(download_url($a['resume_path'])) . '" target="_blank">Resume</a><br>';
              endif;

              if (!empty($a['cover_letter_path'])):
                echo '<a href="' . htmlspecialchars(download_url($a['cover_letter_path'])) . '" target="_blank">Cover Letter</a><br>';
              endif;

              if (!empty($a['requirements_docs'])) {
                $decoded = json_decode($a['requirements_docs'], true);
                $files = is_array($decoded) ? $decoded : [$a['requirements_docs']];
                foreach ($files as $file) {
                  if (!$file)
                    continue;
                  echo '<a href="' . htmlspecialchars(download_url($file)) . '" target="_blank">Requirement</a><br>';
                }
              }
              ?>
            </td>
            <td>
              <?php if (current_user()['role'] === 'clerk'): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                  <input type="hidden" name="rank_application_id" value="<?= $a['application_id'] ?>">
                  <input type="hidden" name="job_id" value="<?= $jobId ?>">
                  <input class="rank-input" type="number" name="rank_value" min="1" max="10"
                    value="<?= htmlspecialchars($a['rank'] ?? '') ?>">
                  <button class="save-rank" type="submit">Save</button>
                </form>
              <?php else: ?>
                <?= $a['rank'] ? htmlspecialchars($a['rank']) : '—' ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($a['status'] === 'submitted' || $a['status'] === 'rejected_by_clerk'): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                  <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
                  <input type="hidden" name="job_id" value="<?= $jobId ?>">
                  <button name="action" value="approve">Approve</button>
                </form>

                <form method="post" style="display:inline;">
                  <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                  <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
                  <input type="hidden" name="job_id" value="<?= $jobId ?>">
                  <input name="reason" placeholder="Reason">
                  <button name="action" value="reject">Reject</button>
                </form>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </table>
  </div>

</body>

</html>