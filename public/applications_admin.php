<?php 
require_once __DIR__ . '/../src/init.php'; 
require_role(['admin']);

// Handle POST actions for approve/reject FIRST - before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['application_id'])) {
    $appId = (int) $_POST['application_id'];
    $adminUid = $_SESSION['uid'];
    
    // Get current application details
    $stmt = db()->prepare("SELECT applicant_uid, status FROM applications WHERE application_id = ?");
    $stmt->execute([$appId]);
    $app = $stmt->fetch();
    
    if ($app) {
        if ($_POST['action'] === 'admin_approve') {
            // Admin can approve from any status except already approved_by_admin
            if ($app['status'] !== 'approved_by_admin') {
                db()->prepare("
                    UPDATE applications
                    SET status = 'approved_by_admin',
                        approval_rejection_reason = NULL,
                        approved_by_uid = ?
                    WHERE application_id = ?
                ")->execute([$adminUid, $appId]);

                // Notify applicant
                notify_user(
                    $app['applicant_uid'],
                    'application_approved_admin',
                    'Application Approved',
                    '<p>Your application has been approved by the Administrator.</p>',
                    $adminUid
                );
                
                $_SESSION['success'] = 'Application approved successfully!';
            }
            
        } elseif ($_POST['action'] === 'admin_reject' && isset($_POST['rejection_reason'])) {
            $reason = trim($_POST['rejection_reason']);
            
            // Admin can reject from any status except already rejected_by_admin
            if ($app['status'] !== 'rejected_by_admin') {
                db()->prepare("
                    UPDATE applications
                    SET status = 'rejected_by_admin',
                        approval_rejection_reason = ?,
                        approved_by_uid = ?
                    WHERE application_id = ?
                ")->execute([$reason, $adminUid, $appId]);

                // Notify applicant
                notify_user(
                    $app['applicant_uid'],
                    'application_rejected_admin',
                    'Application Rejected',
                    '<p>Your application has been rejected. Reason: ' . htmlspecialchars($reason) . '</p>',
                    $adminUid
                );
                
                $_SESSION['success'] = 'Application rejected successfully!';
            }
        }
    }
    
    // Redirect back immediately after processing POST
    header('Location: applications_admin.php');
    exit; // Important: stop script execution after redirect
}

// Only AFTER handling POST and redirecting, start HTML output
// Fetch all applications for admin (excluding hired)
$apps = db()->query("
  SELECT a.*, 
         u.email AS applicant_email, 
         u.firstName, 
         u.lastName, 
         j.job_title,
         ap.profile_picture
  FROM applications a
  JOIN users u ON u.uid = a.applicant_uid
  JOIN job_vacancies j ON j.vacancy_id = a.vacancy_id
  LEFT JOIN applicant_profiles ap ON ap.applicant_uid = a.applicant_uid
  WHERE a.status NOT IN ('hired') 
  ORDER BY 
    CASE a.status
      WHEN 'submitted' THEN 1
      WHEN 'approved_by_president' THEN 2
      WHEN 'approved_by_admin' THEN 3
      ELSE 4
    END,
    a.date_applied DESC
")->fetchAll();

// Get counts
$counts = [
    'total' => count($apps),
    'submitted' => 0,
    'approved_by_president' => 0,
    'approved_by_admin' => 0,
    'rejected' => 0
];

foreach ($apps as $app) {
    if ($app['status'] === 'submitted') {
        $counts['submitted']++;
    } elseif ($app['status'] === 'approved_by_president') {
        $counts['approved_by_president']++;
    } elseif ($app['status'] === 'approved_by_admin') {
        $counts['approved_by_admin']++;
    } elseif (strpos($app['status'], 'rejected') !== false) {
        $counts['rejected']++;
    }
}

// Display success message if exists
$successMessage = '';
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications Dashboard | Admin</title>
    <link rel="stylesheet" href="assets/utils/applications_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .applicant-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4361ee;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        .applicant-avatar-small img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .action-buttons-cell {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        .status-approved_by_admin { background: #06d6a0; color: white; }
        .status-approved_by_president { background: #4cc9f0; color: white; }
        .status-submitted { background: #7209b7; color: white; }
        .status-rejected_by_admin,
        .status-rejected_by_president { background: #ef476f; color: white; }
        .status-hired { background: #8338ec; color: white; }
        
        .success-message {
            background: #06d6a0;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($successMessage): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($successMessage) ?>
        </div>
        <?php endif; ?>
        
        <div class="header">
            <h3><i class="fas fa-tachometer-alt"></i> Applications Dashboard - Admin</h3>
            <p class="subtitle">Manage all applications. You can approve or reject any application.</p>
            
            <div class="stats-container">
                <div class="stat-card total">
                    <div class="stat-number"><?= $counts['total'] ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-card submitted">
                    <div class="stat-number"><?= $counts['submitted'] ?></div>
                    <div class="stat-label">Submitted</div>
                </div>
                <div class="stat-card president">
                    <div class="stat-number"><?= $counts['approved_by_president'] ?></div>
                    <div class="stat-label">Pending Admin Approval</div>
                </div>
                <div class="stat-card admin">
                    <div class="stat-number"><?= $counts['approved_by_admin'] ?></div>
                    <div class="stat-label">Admin Approved</div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-number"><?= $counts['rejected'] ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>

        <div class="filter-container">
            <div class="filter-group">
                <label class="filter-label"><i class="fas fa-filter"></i> Filter by Status</label>
                <select class="filter-select" id="statusFilter">
                    <option value="all">All Statuses</option>
                    <option value="submitted">Submitted</option>
                    <option value="approved_by_president">Pending Admin Approval</option>
                    <option value="approved_by_admin">Admin Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            
            <div class="filter-search">
                <label class="filter-label"><i class="fas fa-search"></i> Search</label>
                <div class="search-box">
                    <span class="search-icon"><i class="fas fa-search"></i></span>
                    <input type="text" class="search-input" placeholder="Search applications..." id="searchInput">
                </div>
            </div>
        </div>

        <?php if (!empty($apps)): ?>
        <div class="applications-container">
            <div class="table-header">
                <h4><i class="fas fa-list"></i> All Applications (<?= $counts['total'] ?>)</h4>
                <div class="table-count" id="tableCount">
                    Showing <?= count($apps) ?> applications
                </div>
            </div>
            <table class="applications-table" id="applicationsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Applicant</th>
                        <th>Job Position</th>
                        <th>Status</th>
                        <th>Date Applied</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apps as $a): 
                        $statusClass = 'status-' . $a['status'];
                        $applicantName = trim(htmlspecialchars($a['firstName'] . ' ' . $a['lastName']));
                        $initials = strtoupper(substr($a['firstName'] ?? '', 0, 1) . substr($a['lastName'] ?? '', 0, 1));
                        $email = htmlspecialchars($a['applicant_email']);
                        $isApproved = $a['status'] === 'approved_by_admin';
                        $isRejected = strpos($a['status'], 'rejected') !== false;
                    ?>
                        <tr class="application-row" 
                            data-status="<?= $a['status'] ?>"
                            data-name="<?= htmlspecialchars(strtolower($applicantName)) ?>"
                            data-email="<?= htmlspecialchars(strtolower($email)) ?>"
                            data-job="<?= htmlspecialchars(strtolower($a['job_title'])) ?>">
                            <td>#<?= $a['application_id'] ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($a['profile_picture'])): 
                                        $profile_pic = htmlspecialchars($a['profile_picture']);
                                        // Remove 'uploads/' prefix if it exists
                                        if (strpos($profile_pic, 'uploads/') === 0) {
                                            $profile_pic = substr($profile_pic, 8);
                                        }
                                    ?>
                                        <img src="../uploads/<?= $profile_pic ?>" 
                                             class="applicant-avatar-small"
                                             alt="<?= $applicantName ?>"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <?php endif; ?>
                                    <div class="applicant-avatar-small" style="<?= !empty($a['profile_picture']) ? 'display:none;' : '' ?>">
                                        <?= $initials ?>
                                    </div>
                                    <div>
                                        <div class="applicant-name"><?= $applicantName ?></div>
                                        <div class="applicant-email"><?= $email ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($a['job_title']) ?></td>
                            <td>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?php 
                                    $statusText = str_replace('_', ' ', $a['status']);
                                    if ($statusText === 'approved by president') {
                                        echo 'Pending Admin Approval';
                                    } else {
                                        echo htmlspecialchars($statusText);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="date-applied">
                                <?= date('M j, Y', strtotime($a['date_applied'])) ?>
                            </td>
                            <td>
                                <div class="action-buttons-cell">
                                    <!-- View button for all -->
                                    <a href="application_view.php?id=<?= $a['application_id'] ?>" 
                                       class="btn btn-secondary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    
                                    <!-- Admin can approve any application except already approved -->
                                    <?php if (!$isApproved && !$isRejected): ?>
                                        <button onclick="adminApprove(<?= $a['application_id'] ?>)" 
                                                class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button onclick="showRejectForm(<?= $a['application_id'] ?>)" 
                                                class="btn btn-danger btn-sm">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php elseif ($isApproved): ?>
                                        <span style="color: #06d6a0; font-size: 12px;">
                                            <i class="fas fa-check-circle"></i> Approved
                                        </span>
                                    <?php elseif ($isRejected): ?>
                                        <span style="color: #ef476f; font-size: 12px;">
                                            <i class="fas fa-times-circle"></i> Rejected
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="applications-container">
            <div class="no-data">
                <div class="no-data-icon"><i class="fas fa-file-alt"></i></div>
                <h4>No Applications Found</h4>
                <p>There are no applications to review at this time.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-times-circle"></i> Reject Application</h4>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="rejectForm" method="post" action="">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="application_id" id="rejectAppId">
                    <input type="hidden" name="action" value="admin_reject">
                    
                    <div class="form-group">
                        <label for="rejection_reason">Rejection Reason:</label>
                        <textarea name="rejection_reason" 
                                  id="rejection_reason" 
                                  class="form-control" 
                                  rows="4" 
                                  placeholder="Enter reason for rejection..."
                                  required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Confirm Rejection
                        </button>
                        <button type="button" class="btn btn-secondary" id="cancelReject">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusFilter = document.getElementById('statusFilter');
            const searchInput = document.getElementById('searchInput');
            const applicationRows = document.querySelectorAll('.application-row');
            const tableCount = document.getElementById('tableCount');
            
            function filterApplications() {
                const selectedStatus = statusFilter.value;
                const searchTerm = searchInput.value.toLowerCase();
                let visibleCount = 0;
                
                applicationRows.forEach(row => {
                    const status = row.getAttribute('data-status');
                    const name = row.getAttribute('data-name');
                    const email = row.getAttribute('data-email');
                    const job = row.getAttribute('data-job');
                    
                    // Status filter
                    let statusMatch = false;
                    if (selectedStatus === 'all') {
                        statusMatch = true;
                    } else if (selectedStatus === 'rejected') {
                        statusMatch = status.includes('rejected');
                    } else if (selectedStatus === 'approved_by_president') {
                        statusMatch = status === 'approved_by_president';
                    } else {
                        statusMatch = status === selectedStatus;
                    }
                    
                    // Search filter
                    let searchMatch = !searchTerm || 
                        name.includes(searchTerm) || 
                        email.includes(searchTerm) || 
                        job.includes(searchTerm);
                    
                    if (statusMatch && searchMatch) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Update table count
                if (tableCount) {
                    tableCount.textContent = `Showing ${visibleCount} applications`;
                }
            }
            
            statusFilter.addEventListener('change', filterApplications);
            searchInput.addEventListener('input', filterApplications);
            
            // Modal functionality
            const modal = document.getElementById('rejectModal');
            const closeBtn = document.querySelector('.close');
            const cancelBtn = document.getElementById('cancelReject');
            const rejectForm = document.getElementById('rejectForm');
            
            if (closeBtn) {
                closeBtn.onclick = function() {
                    modal.style.display = 'none';
                }
            }
            
            if (cancelBtn) {
                cancelBtn.onclick = function() {
                    modal.style.display = 'none';
                }
            }
            
            // Set form action to current page
            if (rejectForm) {
                rejectForm.action = window.location.href;
            }
            
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        });
        
        function adminApprove(appId) {
            if (confirm('Are you sure you want to approve this application?')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.action = window.location.href;
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_csrf';
                csrfInput.value = '<?= csrf_token(); ?>';
                
                const appIdInput = document.createElement('input');
                appIdInput.type = 'hidden';
                appIdInput.name = 'application_id';
                appIdInput.value = appId;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'admin_approve';
                
                form.appendChild(csrfInput);
                form.appendChild(appIdInput);
                form.appendChild(actionInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function showRejectForm(appId) {
            const modal = document.getElementById('rejectModal');
            const appIdInput = document.getElementById('rejectAppId');
            const textarea = document.getElementById('rejection_reason');
            
            appIdInput.value = appId;
            textarea.value = '';
            modal.style.display = 'block';
            textarea.focus();
        }
    </script>
    
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 10px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .modal-header {
            background: #ef476f;
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h4 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .close {
            cursor: pointer;
            font-size: 24px;
        }
        .modal-body {
            padding: 20px;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            margin-bottom: 15px;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
    </style>
</body>
</html>