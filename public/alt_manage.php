<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/dashboard_sidebar.php'; 

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
        u.firstName AS applicant_firstName,
        u.middleName AS applicant_middleName,
        u.lastName AS applicant_lastName,
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
        AND approver.role = 'president'
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

    // Get current user UID safely
    $currentUser = current_user();
    $approvedBy = is_array($currentUser) && isset($currentUser['uid']) ? $currentUser['uid'] : null;

    if ($approvedBy === null) {
        die("Error: Could not determine current user UID. Please re-login.");
    }

    // Set interview schedule
    if ($action === 'set_interview') {
        $date = $_POST['interview_date'] ?? null;

        if (!empty($date)) {
            $stmt = db()->prepare("UPDATE applications SET interview_date = ? WHERE application_id = ?");
            $stmt->execute([$date, $appId]);

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => "Interview schedule updated successfully."
            ];
            redirect("alt_manage.php?id={$appId}");
        } else {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => "Please provide a valid interview date."
            ];
        }
    }

    // Finalize (hire/reject)
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

                // If Hired: Insert into employee_tracking
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

                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => "Application finalized as " . ($final_status === 'hired' ? 'Hired' : 'Rejected') . "."
                ];
                redirect("alt_manage.php?id={$appId}");
            } catch (PDOException $e) {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => "Database error: " . htmlspecialchars($e->getMessage())
                ];
            }
        } else {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => "Invalid status selected."
            ];
        }
    }
}

// Get status badge class
function getStatusBadgeClass($status) {
    $classes = [
        'approved_by_president' => 'status-president',
        'approved_by_admin' => 'status-admin',
        'interviewed' => 'status-interviewed',
        'hired' => 'status-hired',
        'rejected_final' => 'status-rejected'
    ];
    return $classes[$status] ?? 'status-default';
}

// Get status display text
function getStatusText($status) {
    return ucwords(str_replace('_', ' ', $status));
}

function formatDate($date) {
    if (!$date) return 'Not scheduled';
    return date('F j, Y \a\t g:i A', strtotime($date));
}

$page_title = "Manage Application #" . $appId;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Application | <?= htmlspecialchars($app['application_id']) ?></title>
    <link rel="stylesheet" href="assets/utils/dashboard.css">
    <link rel="stylesheet" href="assets/utils/alt_manage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
     <div class="dashboard-layout">
        <?php render_sidebar($user, 'applications_admin'); ?>
        
        <main class="tri-content">
            <?php render_topbar($user, $page_title); ?>
            
            <div class="content-wrapper">
                <div class="page-content"></div>
                    <div class="container">
                        <!-- Header -->
                        <div class="header">
                            <div class="header-left">
                                <h1>Manage Application</h1>
                                <p class="subtitle">Application ID: <?= htmlspecialchars($app['application_id']) ?></p>
                            </div>
                            <a href="applications_advanced.php" class="back-link">
                                <i class="fas fa-arrow-left"></i> Back to Applications
                            </a>
                        </div>

                        <!-- Flash Messages -->
                        <?php if (isset($_SESSION['flash'])): ?>
                            <div class="flash <?= $_SESSION['flash']['type'] ?>">
                                <i class="fas fa-<?= $_SESSION['flash']['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                                <?= htmlspecialchars($_SESSION['flash']['message']) ?>
                            </div>
                            <?php unset($_SESSION['flash']); ?>
                        <?php endif; ?>

                        <!-- Main Content -->
                        <div class="main-content">
                            <!-- Application Details -->
                            <div class="details-card">
                                <div class="details-header">
                                    <h3>Application Information</h3>
                                    <span class="status-badge <?= getStatusBadgeClass($app['status']) ?>">
                                        <?= getStatusText($app['status']) ?>
                                    </span>
                                </div>
                                
                                <table class="details-table">
                                    <tr>
                                        <th>Application ID</th>
                                        <td>
                                            <strong><?= htmlspecialchars($app['application_id']) ?></strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Job Position</th>
                                        <td>
                                            <strong><?= htmlspecialchars($app['job_title']) ?></strong>
                                        <!--  <?php if ($app['department']): ?>
                                                <br><span style="color: var(--muted); font-size: 14px;"><?= htmlspecialchars($app['department']) ?></span>
                                            <?php endif; ?> -->
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Applicant</th>
                                        <td>
                                            <div class="applicant-info">
                                                <span class="applicant-name">
                                                    <?= htmlspecialchars($app['applicant_firstName'] . ' ' . 
                                                        ($app['applicant_middleName'] ? $app['applicant_middleName'] . ' ' : '') . 
                                                        $app['applicant_lastName']) ?>
                                                </span>
                                                <span class="applicant-contact">
                                                    <?= htmlspecialchars($app['applicant_email']) ?>
                                                    <!-- <?php if ($app['applicant_phone']): ?>
                                                        • <?= htmlspecialchars($app['applicant_phone']) ?>
                                                    <?php endif; ?> -->
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Date Applied</th>
                                        <td><?= date('F j, Y', strtotime($app['date_applied'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Interview Date</th>
                                        <td>
                                            <?php if ($app['interview_date']): ?>
                                                <div class="interview-date">
                                                    <?= formatDate($app['interview_date']) ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--muted);">Not scheduled</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Approved By</th>
                                        <td>
                                            <?php if ($app['approver_firstName']): ?>
                                                <?= htmlspecialchars($app['approver_firstName'] . ' ' .
                                                    ($app['approver_middleName'] ? $app['approver_middleName'] . ' ' : '') .
                                                    $app['approver_lastName']) ?>
                                                <br>
                                                <span style="color: var(--muted); font-size: 14px;">UID: <?= htmlspecialchars($app['approver_uid']) ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--muted);">Not yet approved</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Action Cards -->
                            <div class="action-sidebar">
                                <!-- Interview Schedule Card -->
                                <div class="action-card">
                                    <h4>
                                        <i class="fas fa-calendar-alt"></i> Interview Schedule
                                    </h4>
                                    <form method="post">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="action" value="set_interview">
                                        
                                        <div class="form-group">
                                            <label for="interview_date">
                                                <i class="fas fa-clock"></i> Interview Date & Time
                                            </label>
                                            <input 
                                                type="datetime-local" 
                                                id="interview_date" 
                                                name="interview_date" 
                                                class="form-control" 
                                                value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($app['interview_date'] ?? 'now'))) ?>" 
                                                required
                                            >
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-save"></i> Save Interview Schedule
                                        </button>
                                    </form>
                                </div>

                                <!-- Finalize Application Card -->
                                <div class="action-card">
                                    <h4>
                                        <i class="fas fa-flag-checkered"></i> Finalize Application
                                    </h4>
                                    <form method="post">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="action" value="finalize">
                                        
                                        <div class="form-group">
                                            <label for="final_status">
                                                <i class="fas fa-check-circle"></i> Final Decision
                                            </label>
                                            <select id="final_status" name="final_status" class="form-control" required>
                                                <option value="">Select decision...</option>
                                                <option value="hired">Hire Applicant</option>
                                                <option value="rejected_final">Reject Applicant</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="reason">
                                                <i class="fas fa-comment"></i> Reason (Optional)
                                            </label>
                                            <textarea 
                                                id="reason" 
                                                name="reason" 
                                                class="form-control" 
                                                placeholder="Enter reason for decision..."
                                                rows="3"
                                            ></textarea>
                                        </div>
                                        
                                        <div class="btn-group">
                                            <button type="submit" name="finalize_action" value="hired" class="btn btn-success" style="flex: 1;">
                                                <i class="fas fa-user-check"></i> Hire
                                            </button>
                                            <button type="submit" name="finalize_action" value="rejected_final" class="btn btn-danger" style="flex: 1;">
                                                <i class="fas fa-user-times"></i> Reject
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Quick Actions -->
                                <div class="action-card">
                                    <h4>
                                        <i class="fas fa-bolt"></i> Quick Actions
                                    </h4>
                                    <div style="display: flex; flex-direction: column; gap: 12px;">
                                        <a href="view_applicant.php?id=<?= $app['applicant_uid'] ?>" class="btn" style="background: var(--bg-2); color: var(--text);">
                                            <i class="fas fa-user-circle"></i> View Applicant Profile
                                        </a>
                                        <a href="view_job.php?id=<?= $app['vacancy_id'] ?>" class="btn" style="background: var(--bg-2); color: var(--text);">
                                            <i class="fas fa-briefcase"></i> View Job Details
                                        </a>
                                        <button onclick="window.print()" class="btn" style="background: var(--bg-2); color: var(--text);">
                                            <i class="fas fa-print"></i> Print Application
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>


    <script>
        // Form handling for finalize buttons
        document.querySelectorAll('button[name="finalize_action"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form');
                const status = this.value;
                const reason = form.querySelector('[name="reason"]').value;
                
                // Set the hidden status field
                form.querySelector('[name="final_status"]').value = status;
                
                // Confirm action
                const action = status === 'hired' ? 'hire' : 'reject';
                if (confirm(`Are you sure you want to ${action} this applicant?`)) {
                    form.submit();
                }
            });
        });

        document.getElementById('interview_date').min = new Date().toISOString().slice(0, 16);
    </script>
</body>
</html>