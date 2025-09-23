<?php require_once __DIR__ . '/../src/init.php'; require_role(['applicant']);
$st = db()->prepare("SELECT a.*, j.job_title FROM applications a JOIN job_vacancies j ON a.vacancy_id=j.vacancy_id WHERE a.applicant_uid=? ORDER BY a.date_applied DESC");
$st->execute([$_SESSION['uid']]); $apps=$st->fetchAll();
?>
<!doctype html><html><body>
  <link rel="stylesheet" type="text/css" href="assets/utils/my_applications.css">
<h3>My Applications</h3>
<table border="1" cellpadding="6">
  <tr><th>Job</th><th>Status</th><th>Interview</th><th>Reason</th></tr>
  <?php foreach($apps as $a): ?>
    <tr>
  <td data-label="Job"><?= htmlspecialchars($a['job_title']) ?></td>
  <td data-label="Status"><?= htmlspecialchars($a['status']) ?></td>
  <td data-label="Interview"><?= htmlspecialchars($a['interview_date']) ?></td>
  <td data-label="Reason"><?= htmlspecialchars($a['approval_rejection_reason']) ?></td>
</tr>
  <?php endforeach; ?>
</table>
</body></html>
