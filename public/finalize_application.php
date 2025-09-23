<?php require_once __DIR__ . '/../src/init.php'; require_role(['admin']); csrf_check();

$appId = (int)($_POST['application_id'] ?? 0);
$status= $_POST['final_status'] ?? 'rejected_final';
$reason= trim($_POST['reason'] ?? '');

db()->prepare("UPDATE applications SET status=?, approval_rejection_reason=? WHERE application_id=?")
  ->execute([$status, $reason ?: null, $appId]);

if ($status === 'hired') {
  $st = db()->prepare("SELECT applicant_uid FROM applications WHERE application_id=?");
  $st->execute([$appId]); $uid = $st->fetchColumn();
  db()->prepare("INSERT IGNORE INTO employee_tracking (applicant_uid, employment_status, start_date) VALUES (?, 'job_order', CURDATE())")
    ->execute([$uid]);
}

$st = db()->prepare("SELECT applicant_uid FROM applications WHERE application_id=?");
$st->execute([$appId]); $uid = $st->fetchColumn();
notify_user($uid, 'final_decision', 'Application Result: ' . strtoupper($status),
  '<p>Status: '.htmlspecialchars($status).'</p><p>'.htmlspecialchars($reason).'</p>', $_SESSION['uid']);

redirect('applications_admin.php');
