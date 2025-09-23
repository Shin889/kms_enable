<?php 
require_once __DIR__ . '/../src/init.php'; 
require_role(['clerk','admin']); 
csrf_check();

// Build download URL (keeps same download.php contract used elsewhere)
function download_url($path) {
    if (!$path) return null;
    $path = str_replace('\\', '/', $path); // normalize slashes
    return 'download_all.php?file=' . urlencode($path);
}

// Helper to get display filename
function display_name($path) {
    $path = str_replace('\\', '/', $path);
    return htmlspecialchars(basename($path));
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'], $_POST['application_id'])) {
  $appId = (int)$_POST['application_id'];
  if ($_POST['action']==='approve') {
    db()->prepare("UPDATE applications SET status='approved_by_clerk', approval_rejection_reason=NULL WHERE application_id=?")
      ->execute([$appId]);
    // notify applicant
    $row = db()->prepare("SELECT applicant_uid FROM applications WHERE application_id=?");
    $row->execute([$appId]); $uid = $row->fetchColumn();
    notify_user($uid, 'application_approved_clerk', 'Your application passed initial review', '<p>Approved by Clerk.</p>', $_SESSION['uid']);
  } elseif ($_POST['action']==='reject') {
    $reason = trim($_POST['reason'] ?? '');
    db()->prepare("UPDATE applications SET status='rejected_by_clerk', approval_rejection_reason=? WHERE application_id=?")
      ->execute([$reason, $appId]);
    $row = db()->prepare("SELECT applicant_uid FROM applications WHERE application_id=?");
    $row->execute([$appId]); $uid = $row->fetchColumn();
    notify_user($uid, 'application_rejected_clerk', 'Application Rejected', '<p>Reason: '.htmlspecialchars($reason).'</p>', $_SESSION['uid']);
  }
  redirect('applications_review.php');
}

$apps = db()->query("
  SELECT a.*, u.email AS applicant_email, j.job_title
  FROM applications a
  JOIN users u ON u.uid=a.applicant_uid
  JOIN job_vacancies j ON j.vacancy_id=a.vacancy_id
  ORDER BY a.date_applied DESC
")->fetchAll();
?>
<!doctype html>
<html>
<head>
<link rel="stylesheet" type="text/css" href="assets/utils/applications_review.css">

<style>
/* Print-specific styles */
@media print {
  body * {
    visibility: hidden;
  }
  #print-area, #print-area * {
    visibility: visible;
  }
  #print-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }

  /* Hide buttons during print */
  button, form {
    display: none !important;
  }

  table {
    border-collapse: collapse;
    width: 100%;
    font-size: 14px;
  }
  table th, table td {
    border: 1px solid #000;
    padding: 6px;
    text-align: left;
  }
}
</style>
</head>
<body>

<h3>Applications</h3>
<button onclick="window.print()">🖨 Print Report</button>

<div id="print-area">

  <table border="1" cellpadding="6">
  <tr>
    <th>ID</th>
    <th>Job</th>
    <th>Applicant</th>
    <th>Status</th>
    <th>Files</th>
    <th>Actions</th>
  </tr>
  <?php foreach($apps as $a): ?>
  <tr>
    <td><?= $a['application_id'] ?></td>
    <td><?= htmlspecialchars($a['job_title']) ?></td>
    <td><?= htmlspecialchars($a['applicant_email']) ?></td>
    <td><?= htmlspecialchars($a['status']) ?></td>
    <td>
      <?php
      // Resume
      if (!empty($a['resume_path'])):
          $link = download_url($a['resume_path']);
          echo '<a href="'.htmlspecialchars($link).'" target="_blank" rel="noopener">Resume — '.display_name($a['resume_path']).'</a><br>';
      endif;

      // Cover letter (fixed variable name & link text)
      if (!empty($a['cover_letter_path'])):
          $link = download_url($a['cover_letter_path']);
          echo '<a href="'.htmlspecialchars($link).'" target="_blank" rel="noopener">Cover Letter — '.display_name($a['cover_letter_path']).'</a><br>';
      endif;

      // Requirements: could be JSON array or single string
      if (!empty($a['requirements_docs'])) {
          $raw = $a['requirements_docs'];
          $decoded = json_decode($raw, true);

          if (is_array($decoded)) {
              foreach ($decoded as $file) {
                  if (!$file) continue;
                  $link = download_url($file);
                  echo '<a href="'.htmlspecialchars($link).'" target="_blank" rel="noopener">Requirement — '.display_name($file).'</a><br>';
              }
          } else {
              // If it's not JSON array, treat as a single path string
              $file = $raw;
              $file = trim($file);
              if ($file !== '') {
                  $link = download_url($file);
                  echo '<a href="'.htmlspecialchars($link).'" target="_blank" rel="noopener">Requirement — '.display_name($file).'</a><br>';
              }
          }
      }
      ?>
    </td>
    <td>
      <?php if ($a['status']==='submitted' || $a['status']==='rejected_by_clerk'): ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
          <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
          <button name="action" value="approve">Approve</button>
        </form>
        <form method="post" style="display:inline;">
          <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
          <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
          <input name="reason" placeholder="Reason">
          <button name="action" value="reject">Reject</button>
        </form>
      <?php else: ?>—
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </table>
</div>

</body>
</html>
