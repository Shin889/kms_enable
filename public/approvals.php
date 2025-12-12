<?php
require_once __DIR__ . '/../src/init.php';
$me = current_user();
if (!$me || $me['role'] !== 'admin') {
    $_SESSION['flash_error'] = 'Access denied. Admin privileges required.';
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    
    $action = $_POST['action'] ?? '';
    $uid = $_POST['uid'] ?? '';
    
    if ($action === 'approve') {
        db()->prepare("UPDATE users SET account_status = 'active' WHERE uid = ?")->execute([$uid]);
        notify_user($uid, 'account_approved', 'Account Approved', 
            '<p>Your clerk account has been approved by the administrator.</p>
             <p>You can now log in and access the HRMPSB dashboard.</p>');
        $_SESSION['flash'] = 'Account approved successfully.';
    } elseif ($action === 'reject') {
        db()->prepare("UPDATE users SET account_status = 'rejected' WHERE uid = ?")->execute([$uid]);
        notify_user($uid, 'account_rejected', 'Account Rejected', 
            '<p>Your clerk account registration was rejected.</p>
             <p>Please contact the administrator for more information.</p>');
        $_SESSION['flash'] = 'Account rejected successfully.';
    }
    redirect('pending_approvals.php');
}

// Get pending clerk accounts
$st = db()->prepare("SELECT * FROM users WHERE account_status = 'pending' AND role = 'clerk' ORDER BY created_at DESC");
$st->execute();
$pending = $st->fetchAll(PDO::FETCH_ASSOC);

// Get counts
$stats = [
    'total' => count($pending),
    'today' => 0,
    'week' => 0
];

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));

foreach ($pending as $clerk) {
    $createdAt = date('Y-m-d', strtotime($clerk['created_at']));
    if ($createdAt === $today) {
        $stats['today']++;
    }
    if ($createdAt >= $weekAgo) {
        $stats['week']++;
    }
}

// Check for flash messages
$flash = $_SESSION['flash'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_error']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals | Admin</title>
    <link rel="stylesheet" href="assets/utils/approvals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Pending Clerk Approvals</h2>
            <p class="subtitle">Review and approve new HRMPSB staff account registrations</p>
            
            <div class="stats-container">
                <div class="stat-card total">
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Pending</div>
                </div>
                <div class="stat-card today">
                    <div class="stat-number"><?= $stats['today'] ?></div>
                    <div class="stat-label">Today</div>
                </div>
                <div class="stat-card week">
                    <div class="stat-number"><?= $stats['week'] ?></div>
                    <div class="stat-label">Last 7 Days</div>
                </div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message flash-success">
                <i class="fas fa-check-circle"></i>
                <div><?= htmlspecialchars($flash) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="flash-message flash-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= htmlspecialchars($flash_error) ?></div>
            </div>
        <?php endif; ?>

        <div class="approvals-container">
            <div class="table-header">
                <h3><i class="fas fa-user-clock"></i> Pending Clerk Accounts</h3>
                <div class="table-count"><?= $stats['total'] ?> accounts awaiting approval</div>
            </div>
            
            <?php if (empty($pending)): ?>
                <div class="no-pending">
                    <div class="no-pending-icon"><i class="fas fa-check-circle"></i></div>
                    <h4>All Clear!</h4>
                    <p>There are no pending clerk accounts awaiting approval.</p>
                    <p>New clerk registrations will appear here for review.</p>
                </div>
            <?php else: ?>
                <table class="approvals-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Applicant</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $clerk): 
                            $firstName = htmlspecialchars($clerk['firstName']);
                            $lastName = htmlspecialchars($clerk['lastName']);
                            $fullName = $firstName . ' ' . $lastName;
                            $email = htmlspecialchars($clerk['email']);
                            $createdAt = date('M j, Y', strtotime($clerk['created_at']));
                            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                        ?>
                            <tr>
                                <td>
                                    <span class="user-id">#<?= $clerk['uid'] ?></span>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar"><?= $initials ?></div>
                                        <div class="user-details">
                                            <div class="user-name"><?= $fullName ?></div>
                                            <div class="user-email"><?= $email ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="date-joined">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?= $createdAt ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="uid" value="<?= $clerk['uid'] ?>">
                                            <button type="submit" name="action" value="approve" 
                                                    class="btn btn-success"
                                                    onclick="return confirm('Approve clerk account for <?= addslashes($fullName) ?>?')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="uid" value="<?= $clerk['uid'] ?>">
                                            <button type="submit" name="action" value="reject" 
                                                    class="btn btn-danger"
                                                    onclick="return confirm('Reject clerk account for <?= addslashes($fullName) ?>? This action cannot be undone.')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- <div class="action-buttons-footer">
            <a href="dashboard.php" class="action-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="user_management.php" class="action-link">
                <i class="fas fa-users-cog"></i> User Management
            </a>
            <a href="vacancy_manage.php" class="action-link primary">
                <i class="fas fa-briefcase"></i> Manage Vacancies
            </a>
        </div> -->
    </div>

    <div id="approveModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3>Approve Clerk Account</h3>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this clerk account?</p>
                <p>The user will be able to log in and access the HRMPSB dashboard.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <form method="post" id="approveForm" style="display: inline;">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="uid" id="approveUid">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Yes, Approve
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="rejectModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3>Reject Clerk Account</h3>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reject this clerk account?</p>
                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                <p>The user will be notified and will not be able to log in.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <form method="post" id="rejectForm" style="display: inline;">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="uid" id="rejectUid">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Yes, Reject
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showApproveModal(uid, name) {
            document.getElementById('approveUid').value = uid;
            document.getElementById('approveModal').style.display = 'flex';
        }
        
        function showRejectModal(uid, name) {
            document.getElementById('rejectUid').value = uid;
            document.getElementById('rejectModal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
        
        // Confirmation for direct form submissions (fallback)
        document.querySelectorAll('form[action*="approve"] button[type="submit"]').forEach(button => {
            button.addEventListener('click', function(e) {
                const form = this.closest('form');
                const name = form.querySelector('input[name="name"]')?.value || 'this clerk';
                if (!confirm(`Approve clerk account for ${name}?`)) {
                    e.preventDefault();
                }
            });
        });
        
        document.querySelectorAll('form[action*="reject"] button[type="submit"]').forEach(button => {
            button.addEventListener('click', function(e) {
                const form = this.closest('form');
                const name = form.querySelector('input[name="name"]')?.value || 'this clerk';
                if (!confirm(`Reject clerk account for ${name}? This action cannot be undone.`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>