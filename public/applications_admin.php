<?php require_once __DIR__ . '/../src/init.php'; require_role(['admin']);
$apps = db()->query("
  SELECT a.*, u.email AS applicant_email, j.job_title
  FROM applications a
  JOIN users u ON u.uid=a.applicant_uid
  JOIN job_vacancies j ON j.vacancy_id=a.vacancy_id
  WHERE a.status IN ('approved_by_clerk','approved_by_admin','interviewed')
  ORDER BY a.date_applied DESC
")->fetchAll();
?>
<!doctype html><html><body>
  <link rel="stylesheet" href="assets/utils/applications_admin.css">
<h3>Applications (Admin)</h3>
<button class="print-btn">
  <i class="fa fa-print"></i>🖨 Print Report
</button>

<table border="1" cellpadding="6">
<tr><th>ID</th><th>Job</th><th>Applicant</th><th>Status</th><th>Interview</th><th>Actions</th></tr>
<?php foreach($apps as $a): ?>
<tr>
  <td><?= $a['application_id'] ?></td>
  <td><?= htmlspecialchars($a['job_title']) ?></td>
  <td><?= htmlspecialchars($a['applicant_email']) ?></td>
  <td><?= htmlspecialchars($a['status']) ?></td>
  <td><?= htmlspecialchars($a['interview_date']) ?></td>
  <td>
    <form action="interview_schedule.php" method="post" style="display:inline;">
      <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
      <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
      <input type="datetime-local" name="interview_date" required>
      <button>Set Interview</button>
    </form>
    <form action="finalize_application.php" method="post" style="display:inline;">
      <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
      <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
      <select name="final_status">
        <option value="hired">Hire</option>
        <option value="rejected_final">Reject</option>
      </select>
      <input name="reason" placeholder="Reason (optional)">
      <button>Finalize</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
