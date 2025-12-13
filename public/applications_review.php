<?php
require_once __DIR__ . '/../src/init.php';
require_role(['president', 'admin']);
csrf_check();

// DEBUG: Check what's happening
echo "<!-- DEBUG: Starting applications_review.php -->";
echo "<!-- DEBUG: Current user role: " . current_user()['role'] . " -->";
echo "<!-- DEBUG: GET job_id = " . ($_GET['job_id'] ?? 'NOT SET') . " -->";
$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
echo "<!-- DEBUG: jobId variable = $jobId -->";

function download_url($path)
{
  if (!$path)
    return null;
  $path = str_replace('\\', '/', $path); 
  return 'download_all.php?file=' . urlencode($path);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['application_id'])) {
  $appId = (int) $_POST['application_id'];
  $presidentUid = $_SESSION['uid'];

  if ($_POST['action'] === 'approve') {
    db()->prepare("
            UPDATE applications
            SET status = 'approved_by_president',
                approval_rejection_reason = NULL,
                approved_by_uid = ?
            WHERE application_id = ?
        ")->execute([$presidentUid, $appId]);

    $stmt = db()->prepare("SELECT applicant_uid FROM applications WHERE application_id = ?");
    $stmt->execute([$appId]);
    $uid = $stmt->fetchColumn();

    notify_user(
      $uid,
      'application_approved_president',
      'Your application passed initial review',
      '<p>Approved by PRESIDENT.</p>',
      $presidentUid
    );

  } elseif ($_POST['action'] === 'reject') {
    $reason = trim($_POST['reason'] ?? '');
    db()->prepare("
            UPDATE applications
            SET status = 'rejected_by_president',
                approval_rejection_reason = ?,
                approved_by_uid = ?
            WHERE application_id = ?
        ")->execute([$reason, $presidentUid, $appId]);

    $stmt = db()->prepare("SELECT applicant_uid FROM applications WHERE application_id = ?");
    $stmt->execute([$appId]);
    $uid = $stmt->fetchColumn();

    notify_user(
      $uid,
      'application_rejected_president',
      'Application Rejected',
      '<p>Reason: ' . htmlspecialchars($reason) . '</p>',
      $presidentUid
    );
  }

  $jobId = $_POST['job_id'] ?? 0;
  redirect('applications_review.php?job_id=' . urlencode($jobId));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rank_value'], $_POST['rank_application_id'])) {
  $rank = (int) $_POST['rank_value'];
  $appId = (int) $_POST['rank_application_id'];

  if (current_user()['role'] === 'president') {
    $stmt = db()->prepare("UPDATE applications SET rank = ? WHERE application_id = ?");
    $stmt->execute([$rank, $appId]);
  }

  $jobId = $_POST['job_id'] ?? 0;
  redirect('applications_review.php?job_id=' . urlencode($jobId));
}

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;

if ($jobId > 0) {
  $stmt = db()->prepare("
        SELECT a.*, u.email AS applicant_email, u.firstName, u.lastName, j.job_title
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
  
  // Get application statistics for this job
  $stats = [
      'total' => count($apps),
      'submitted' => 0,
      'under_review' => 0,
      'approved' => 0,
      'rejected' => 0
  ];
  
  foreach ($apps as $app) {
      $status = $app['status'];
      if (in_array($status, ['submitted', 'rejected_by_president'])) {
          $stats['submitted']++;
      } elseif (in_array($status, ['under_review', 'shortlisted', 'interviewed'])) {
          $stats['under_review']++;
      } elseif (in_array($status, ['approved_by_president', 'approved_by_admin'])) {
          $stats['approved']++;
      } else {
          $stats['rejected']++;
      }
  }
} else {
  $apps = [];
  $jobTitle = "Unknown Job";
  $stats = ['total' => 0, 'submitted' => 0, 'under_review' => 0, 'approved' => 0, 'rejected' => 0];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Applications Review - <?= htmlspecialchars($jobTitle) ?> | PRESIDENT</title>
  <link rel="stylesheet" href="assets/utils/applications_review.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h3>Applications Review</h3>
            <p class="subtitle">Review applications for: <strong><?= htmlspecialchars($jobTitle) ?></strong></p>
            
            <div class="stats-container">
                <div class="stat-card total">
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card submitted">
                    <div class="stat-number"><?= $stats['submitted'] ?></div>
                    <div class="stat-label">Submitted</div>
                </div>
                <div class="stat-card review">
                    <div class="stat-number"><?= $stats['under_review'] ?></div>
                    <div class="stat-label">Under Review</div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-number"><?= $stats['approved'] ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-number"><?= $stats['rejected'] ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>

        <div class="review-container">
            <div class="review-header">
                <h4><i class="fas fa-file-alt"></i> Application Details</h4>
                <div class="review-count"><?= $stats['total'] ?> applications</div>
            </div>
            
            <?php if (empty($apps)): ?>
                <div class="no-applications">
                    <div class="no-applications-icon"><i class="fas fa-file-alt"></i></div>
                    <h4>No Applications Found</h4>
                    <p>No applications have been submitted for this job vacancy yet.</p>
                    <p>Applications will appear here once applicants submit their documents.</p>
                </div>
            <?php else: ?>
                <table class="applications-table">
                    <thead>
                        <tr>
                            <th>Application ID</th>
                            <th>Applicant</th>
                            <th>Status</th>
                            <th>Date Applied</th>
                            <th>Documents</th>
                            <th>Rank</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apps as $a): 
                            $firstName = htmlspecialchars($a['firstName']);
                            $lastName = htmlspecialchars($a['lastName']);
                            $fullName = $firstName . ' ' . $lastName;
                            $email = htmlspecialchars($a['applicant_email']);
                            $status = $a['status'];
                            $statusClass = 'status-' . str_replace(' ', '_', strtolower($status));
                            $dateApplied = date('M j, Y', strtotime($a['date_applied']));
                            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                        ?>
                            <tr>
                                <td data-label="Application ID">
                                    <span class="application-id">#<?= $a['application_id'] ?></span>
                                </td>
                                <td data-label="Applicant">
                                    <div class="applicant-info">
                                        <div class="applicant-avatar"><?= $initials ?></div>
                                        <div class="applicant-details">
                                            <div class="applicant-name"><?= $fullName ?></div>
                                            <div class="applicant-email"><?= $email ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $status)) ?>
                                    </span>
                                </td>
                                <td data-label="Date Applied" class="date-applied">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= $dateApplied ?>
                                </td>
                                <td data-label="Documents">
                                    <div class="files-list">
                                        <?php if (!empty($a['resume_path'])): ?>
                                            <a href="<?= htmlspecialchars(download_url($a['resume_path'])) ?>" 
                                               target="_blank" 
                                               class="file-link">
                                                <i class="fas fa-file-pdf"></i> Resume
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($a['cover_letter_path'])): ?>
                                            <a href="<?= htmlspecialchars(download_url($a['cover_letter_path'])) ?>" 
                                               target="_blank" 
                                               class="file-link">
                                                <i class="fas fa-file-alt"></i> Cover Letter
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($a['requirements_docs'])) {
                                            $decoded = json_decode($a['requirements_docs'], true);
                                            $files = is_array($decoded) ? $decoded : [$a['requirements_docs']];
                                            foreach ($files as $index => $file) {
                                                if (!$file) continue;
                                                ?>
                                                <a href="<?= htmlspecialchars(download_url($file)) ?>" 
                                                   target="_blank" 
                                                   class="file-link">
                                                    <i class="fas fa-paperclip"></i> Requirement <?= $index + 1 ?>
                                                </a>
                                                <?php
                                            }
                                        } ?>
                                    </div>
                                </td>
                                <td data-label="Rank">
                                    <?php if (current_user()['role'] === 'president'): ?>
                                        <form method="post" class="rank-form">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="rank_application_id" value="<?= $a['application_id'] ?>">
                                            <input type="hidden" name="job_id" value="<?= $jobId ?>">
                                            <input class="rank-input" 
                                                   type="number" 
                                                   name="rank_value" 
                                                   min="1" 
                                                   max="10"
                                                   value="<?= htmlspecialchars($a['rank'] ?? '') ?>"
                                                   placeholder="1-10">
                                            <button class="btn btn-secondary" type="submit">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <?= $a['rank'] ? htmlspecialchars($a['rank']) : '—' ?>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <?php if ($a['status'] === 'submitted' || $a['status'] === 'rejected_by_president'): ?>
                                        <div class="action-buttons">
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
                                                <input type="hidden" name="job_id" value="<?= $jobId ?>">
                                                <button type="submit" 
                                                        name="action" 
                                                        value="approve" 
                                                        class="btn btn-success"
                                                        onclick="return confirm('Approve application from <?= addslashes($fullName) ?>?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
                                                <input type="hidden" name="job_id" value="<?= $jobId ?>">
                                                <button type="button" 
                                                        class="btn btn-danger reject-btn"
                                                        data-app-id="<?= $a['application_id'] ?>"
                                                        data-app-name="<?= htmlspecialchars($fullName) ?>">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                                <input type="text" 
                                                       name="reason" 
                                                       placeholder="Enter rejection reason..."
                                                       class="reason-input"
                                                       id="reason_<?= $a['application_id'] ?>"
                                                       required>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--muted); font-style: italic;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- <div class="action-buttons-footer">
            <a href="applications_overview.php" class="action-link">
                <i class="fas fa-arrow-left"></i> Back to Overview
            </a>
            <a href="applications.php" class="action-link">
                <i class="fas fa-list"></i> View All Applications
            </a>
            <a href="dashboard.php" class="action-link primary">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
        </div> -->
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Reject button functionality
            document.querySelectorAll('.reject-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const appId = this.getAttribute('data-app-id');
                    const appName = this.getAttribute('data-app-name');
                    const reasonInput = document.getElementById('reason_' + appId);
                    
                    // Show/hide reason input
                    if (reasonInput.style.display === 'block') {
                        reasonInput.style.display = 'none';
                        this.innerHTML = '<i class="fas fa-times"></i> Reject';
                    } else {
                        // Hide all other reason inputs
                        document.querySelectorAll('.reason-input').forEach(input => {
                            input.style.display = 'none';
                        });
                        
                        // Reset all reject buttons
                        document.querySelectorAll('.reject-btn').forEach(btn => {
                            btn.innerHTML = '<i class="fas fa-times"></i> Reject';
                        });
                        
                        // Show this reason input
                        reasonInput.style.display = 'block';
                        reasonInput.focus();
                        this.innerHTML = '<i class="fas fa-times"></i> Cancel';
                    }
                });
            });
            
            // Submit reject form when Enter is pressed in reason input
            document.querySelectorAll('.reason-input').forEach(input => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const form = this.closest('form');
                        const reason = this.value.trim();
                        
                        if (!reason) {
                            alert('Please enter a rejection reason.');
                            return;
                        }
                        
                        const appName = form.querySelector('.reject-btn').getAttribute('data-app-name');
                        if (confirm(`Reject application from ${appName}?`)) {
                            form.querySelector('button[name="action"]').value = 'reject';
                            form.submit();
                        }
                    }
                });
            });
            
            // Auto-hide reason inputs when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.reject-btn') && !e.target.classList.contains('reason-input')) {
                    document.querySelectorAll('.reason-input').forEach(input => {
                        input.style.display = 'none';
                    });
                    document.querySelectorAll('.reject-btn').forEach(btn => {
                        btn.innerHTML = '<i class="fas fa-times"></i> Reject';
                    });
                }
            });
            
            // Responsive table enhancements
            function setupResponsiveTable() {
                const tableCells = document.querySelectorAll('.applications-table td');
                const headers = document.querySelectorAll('.applications-table th');
                
                if (window.innerWidth <= 1024) {
                    tableCells.forEach((cell, index) => {
                        const headerIndex = index % headers.length;
                        const headerText = headers[headerIndex].textContent;
                        cell.setAttribute('data-label', headerText);
                    });
                } else {
                    tableCells.forEach(cell => {
                        cell.removeAttribute('data-label');
                    });
                }
            }
            
            // Initial setup
            setupResponsiveTable();
            
            // Update on resize
            window.addEventListener('resize', setupResponsiveTable);
            
            // Confirmation for approve actions
            document.querySelectorAll('form button[name="action"][value="approve"]').forEach(button => {
                button.addEventListener('click', function(e) {
                    const form = this.closest('form');
                    const appName = form.querySelector('input[name="application_id"]')?.getAttribute('data-app-name') || 'this applicant';
                    if (!confirm(`Approve application from ${appName}?`)) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>