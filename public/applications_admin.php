<?php 
require_once __DIR__ . '/../src/init.php'; 
require_role(['admin']);

$apps = db()->query("
  SELECT a.*, u.email AS applicant_email, j.job_title
  FROM applications a
  JOIN users u ON u.uid = a.applicant_uid
  JOIN job_vacancies j ON j.vacancy_id = a.vacancy_id
  WHERE a.status IN ('approved_by_hrmpsb','approved_by_admin','interviewed')
  ORDER BY a.date_applied DESC
")->fetchAll();
?>
<!doctype html>
<html>
<body>
<link rel="stylesheet" href="assets/utils/applications_admin.css">

<h3>Applications (Admin)</h3>
<table border="1" cellpadding="6">
  <tr>
    <th>ID</th>
    <th>Job</th>
    <th>Applicant</th>
    <th>Status</th>
  </tr>

  <?php foreach ($apps as $a): ?>
  <tr>
    <td>
      <a href="alt_manage.php?id=<?= urlencode($a['application_id']) ?>" style="color:blue; text-decoration:underline;">
        <?= htmlspecialchars($a['application_id']) ?>
      </a>
    </td>
    <td><?= htmlspecialchars($a['job_title']) ?></td>
    <td><?= htmlspecialchars($a['applicant_email']) ?></td>
    <td><?= htmlspecialchars($a['status']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

</body>
</html>
