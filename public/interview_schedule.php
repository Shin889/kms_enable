<?php require_once __DIR__ . '/../src/init.php'; require_role(['admin']); csrf_check();

$appId = (int)($_POST['application_id'] ?? 0);
$dt    = $_POST['interview_date'] ?? null;

db()->prepare("UPDATE applications SET interview_date=?, status='approved_by_admin' WHERE application_id=?")
  ->execute([$dt, $appId]);

$st = db()->prepare("SELECT applicant_uid FROM applications WHERE application_id=?");
$st->execute([$appId]); $uid = $st->fetchColumn();
notify_user($uid, 'interview_invite', 'Interview Scheduled', '<p>Your interview is scheduled at: '.htmlspecialchars($dt).'</p>', $_SESSION['uid']);

redirect('applications_admin.php');
