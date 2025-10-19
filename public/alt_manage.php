<?php
require_once __DIR__ . '/../src/init.php';
require_role(['admin']);

if (!isset($_GET['id'])) {
  die("Missing application ID.");
}

$appId = (int) $_GET['id'];

// Fetch application details
$stmt = db()->prepare("
  SELECT 
      a.*, 
      u.email AS applicant_email, 
      j.job_title,
      approver.uid AS approver_uid,
      approver.firstName AS approver_firstName,
      approver.middleName AS approver_middleName,
      approver.lastName AS approver_lastName
  FROM applications a
  JOIN users u ON u.uid = a.applicant_uid
  JOIN job_vacancies j ON j.vacancy_id = a.vacancy_id
  LEFT JOIN users approver 
    ON approver.uid = a.approved_by_uid 
    AND approver.role = 'clerk'
  WHERE a.application_id = ?
");
$stmt->execute([$appId]);
$app = $stmt->fetch();

if (!$app) {
  die("Application not found.");
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  // 🔹 Get current user UID safely
  $currentUser = current_user();
  $approvedBy = is_array($currentUser) && isset($currentUser['uid']) ? $currentUser['uid'] : null;

  if ($approvedBy === null) {
    die("Error: Could not determine current user UID. Please re-login.");
  }

  // 🔹 Set interview schedule
  if ($action === 'set_interview') {
    $date = $_POST['interview_date'] ?? null;

    if (!empty($date)) {
      $stmt = db()->prepare("UPDATE applications SET interview_date = ? WHERE application_id = ?");
      $stmt->execute([$date, $appId]);

      $_SESSION['flash'] = "Interview schedule updated successfully.";
      redirect("alt_manage.php?id={$appId}");
    } else {
      $_SESSION['flash'] = "Please provide a valid interview date.";
    }
  }

  // 🔹 Finalize (hire/reject)
  if ($action === 'finalize') {
    $final_status = $_POST['final_status'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (in_array($final_status, ['hired', 'rejected_final'])) {
      $stmt = db()->prepare("
        UPDATE applications 
        SET status = ?, 
            approval_rejection_reason = ?, 
            approved_by_uid = ? 
        WHERE application_id = ?
      ");

      try {
        $stmt->execute([$final_status, $reason, $approvedBy, $appId]);

        // ✅ If Hired: Insert into employee_tracking
        if ($final_status === 'hired') {
          $check = db()->prepare("SELECT * FROM employee_tracking WHERE applicant_uid = ?");
          $check->execute([$app['applicant_uid']]);
          $exists = $check->fetch();

          if (!$exists) {
            $insert = db()->prepare("
              INSERT INTO employee_tracking 
              (applicant_uid, employment_status, start_date, monitoring_start_date, promotion_history, remarks)
              VALUES (?, 'job_order', CURDATE(), CURDATE(), 'None', 'Initial hire')
            ");
            $insert->execute([$app['applicant_uid']]);
          }
        }

        $_SESSION['flash'] = "Application finalized as {$final_status}.";
        redirect("alt_manage.php?id={$appId}");
      } catch (PDOException $e) {
        $_SESSION['flash'] = "Database error: " . htmlspecialchars($e->getMessage());
      }
    } else {
      $_SESSION['flash'] = "Invalid status selected.";
    }
  }
}
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <title>Manage Application</title>
  <link rel="stylesheet" href="assets/utils/alt_manage.css">
</head>

<body>

  <h3>Application Details</h3>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
  <?php endif; ?>

  <div class="app-details">
    <table>
      <tr>
        <th>Application ID</th>
        <td><?= htmlspecialchars($app['application_id']) ?></td>
      </tr>
      <tr>
        <th>Job Title</th>
        <td><?= htmlspecialchars($app['job_title']) ?></td>
      </tr>
      <tr>
        <th>Applicant Email</th>
        <td><?= htmlspecialchars($app['applicant_email']) ?></td>
      </tr>
      <tr>
        <th>Status</th>
        <td><?= htmlspecialchars($app['status']) ?></td>
      </tr>
      <tr>
        <th>Interview Date</th>
        <td><?= htmlspecialchars($app['interview_date'] ?: 'Not yet scheduled') ?></td>
      </tr>
      <tr>
        <th>Approved By (UID)</th>
        <td><?= htmlspecialchars($app['approver_uid'] ?: 'N/A') ?></td>
      </tr>
      <tr>
        <th>Approved By (Clerk)</th>
        <td>
          <?php if ($app['approver_firstName']): ?>
            <?= htmlspecialchars($app['approver_firstName'] . ' ' .
              ($app['approver_middleName'] ? $app['approver_middleName'] . ' ' : '') .
              $app['approver_lastName']) ?>
          <?php else: ?>
            N/A
          <?php endif; ?>
        </td>
      </tr>
    </table>

    <h4>Set Interview Schedule</h4>
    <form method="post" style="margin-bottom: 25px;">
      <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
      <input type="hidden" name="action" value="set_interview">
      <input type="datetime-local" name="interview_date" value="<?= htmlspecialchars($app['interview_date'] ?? '') ?>" required>
      <button type="submit">Save Interview Schedule</button>
    </form>

    <h4>Finalize Application</h4>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
      <input type="hidden" name="action" value="finalize">
      <select name="final_status" required>
        <option value="">Select</option>
        <option value="hired">Hire</option>
        <option value="rejected_final">Reject</option>
      </select>
      <input name="reason" placeholder="Reason (optional)" style="width:250px;">
      <button type="submit">Finalize</button>
    </form>
  </div>
</body>

</html>
