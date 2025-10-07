<?php
require_once __DIR__ . '/../src/init.php';
require_role(['clerk', 'admin']);
csrf_check();

$stmt = db()->query("
    SELECT j.vacancy_id, j.job_title, COUNT(a.application_id) AS total_applications
    FROM job_vacancies j
    LEFT JOIN applications a ON j.vacancy_id = a.vacancy_id
    GROUP BY j.vacancy_id, j.job_title
    ORDER BY j.date_posted DESC
");
$jobs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Applications Overview</title>
  <link rel="stylesheet" href="assets/utils/card.css">
</head>

<body>

  <h2>Applications (HRMPSB)</h2>

  <div class="cards-container">
    <?php foreach ($jobs as $job): ?>
      <div class="job-card" onclick="window.location.href='applications_review.php?job_id=<?= $job['vacancy_id'] ?>'">
        <div class="job-title"><?= htmlspecialchars($job['job_title']) ?></div>
        <div class="application-count">
          <?= (int) $job['total_applications'] ?> applicant<?= $job['total_applications'] == 1 ? '' : 's' ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</body>

</html>