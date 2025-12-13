<?php 
require_once __DIR__ . '/../src/init.php';
require_role(['president', 'admin']);
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'create') {
        db()->prepare("INSERT INTO employee_tracking (applicant_uid, employment_status, start_date, monitoring_start_date, promotion_history, remarks)
                   VALUES (?,?,?,?,?,?)")
            ->execute([$_POST['applicant_uid'], $_POST['employment_status'], $_POST['start_date'], $_POST['monitoring_start_date'] ?: null, $_POST['promotion_history'] ?: null, $_POST['remarks'] ?: null]);
    } elseif ($_POST['action'] === 'update') {
        db()->prepare("UPDATE employee_tracking SET employment_status=?, start_date=?, monitoring_start_date=?, promotion_history=?, remarks=? WHERE employee_id=?")
            ->execute([$_POST['employment_status'], $_POST['start_date'], $_POST['monitoring_start_date'] ?: null, $_POST['promotion_history'] ?: null, $_POST['remarks'] ?: null, (int) $_POST['employee_id']]);
    }
    redirect('employee_tracking.php');
}

$rows = db()->query("
  SELECT e.*, u.email, u.firstName, u.lastName
  FROM employee_tracking e
  JOIN users u ON u.uid = e.applicant_uid
  ORDER BY e.employee_id DESC
")->fetchAll();

// Get counts for different statuses
$counts = [
    'total' => count($rows),
    'job_order' => 0,
    'permanent' => 0,
    'temporary' => 0,
    'contractual' => 0
];

foreach ($rows as $row) {
    if (isset($counts[$row['employment_status']])) {
        $counts[$row['employment_status']]++;
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Tracking | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="assets/utils/employee_tracking.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <h3>Employee Tracking</h3>
            <p class="subtitle">Monitor and manage employee status after hiring</p>
            
            <div class="stats-container">
                <div class="stat-card total">
                    <div class="stat-number"><?= $counts['total'] ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>
                <div class="stat-card job_order">
                    <div class="stat-number"><?= $counts['job_order'] ?></div>
                    <div class="stat-label">Job Order</div>
                </div>
                <div class="stat-card permanent">
                    <div class="stat-number"><?= $counts['permanent'] ?></div>
                    <div class="stat-label">Permanent</div>
                </div>
                <div class="stat-card temporary">
                    <div class="stat-number"><?= $counts['temporary'] ?></div>
                    <div class="stat-label">Temporary</div>
                </div>
                <div class="stat-card contractual">
                    <div class="stat-number"><?= $counts['contractual'] ?></div>
                    <div class="stat-label">Contractual</div>
                </div>
            </div>
        </div>

        <div class="main-content">
            <div class="form-section">
                <h4><i class="fas fa-user-plus"></i> Add New Employee</h4>
                <form method="post" class="form-grid">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="form-group">
                        <label for="applicant_uid">Applicant UID <span>*</span></label>
                        <input id="applicant_uid" name="applicant_uid" type="text" required
                            placeholder="Enter applicant user ID">
                    </div>

                    <div class="form-group">
                        <label for="employment_status">Employment Status <span>*</span></label>
                        <select id="employment_status" name="employment_status" required>
                            <option value="">Select Status</option>
                            <option value="job_order">Job Order</option>
                            <option value="permanent">Permanent</option>
                            <option value="temporary">Temporary</option>
                            <option value="contractual">Contractual</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="start_date">Start Date <span>*</span></label>
                        <input id="start_date" name="start_date" type="date" required
                               value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group">
                        <label for="monitoring_start_date">Monitoring Start Date</label>
                        <input id="monitoring_start_date" name="monitoring_start_date" type="date">
                    </div>

                    <div class="form-group">
                        <label for="promotion_history">Promotion History</label>
                        <textarea id="promotion_history" name="promotion_history"
                            placeholder="Enter promotion history details..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" placeholder="Enter any additional remarks..."></textarea>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-user-plus"></i> Create Employee Record
                    </button>
                </form>
            </div>

            <div class="table-section">
                <h4>
                    <span><i class="fas fa-users"></i> Employee Records</span>
                    <span class="table-count"><?= $counts['total'] ?> employees</span>
                </h4>
                
                <button onclick="window.print()" class="btn print-btn">
                    <i class="fas fa-print"></i> Print Report
                </button>
                
                <?php if (!empty($rows)): ?>
                <div class="table-container">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Employee</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>Monitoring</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): 
                                $employeeName = trim(htmlspecialchars(($r['firstName'] ?? '') . ' ' . ($r['lastName'] ?? '')));
                            ?>
                                <tr>
                                    <td><strong>#<?= $r['employee_id'] ?></strong></td>
                                    <td>
                                        <div class="employee-name">
                                            <a href="profile.php?uid=<?= $r['applicant_uid'] ?>" class="employee-link">
                                                <i class="fas fa-user"></i>
                                                <?= $employeeName ?>
                                            </a>
                                        </div>
                                        <div class="employee-email"><?= htmlspecialchars($r['email']) ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $r['employment_status'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $r['employment_status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($r['start_date'])) ?></td>
                                    <td>
                                        <?php if ($r['monitoring_start_date']): ?>
                                            <?= date('M j, Y', strtotime($r['monitoring_start_date'])) ?>
                                        <?php else: ?>
                                            <span style="color: var(--muted);">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['remarks']): ?>
                                            <div style="max-width: 200px; line-height: 1.4;">
                                                <?= nl2br(htmlspecialchars($r['remarks'])) ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" class="update-form">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="employee_id" value="<?= $r['employee_id'] ?>">

                                            <select name="employment_status">
                                                <option value="job_order" <?= $r['employment_status'] === 'job_order' ? 'selected' : '' ?>>Job Order</option>
                                                <option value="permanent" <?= $r['employment_status'] === 'permanent' ? 'selected' : '' ?>>Permanent</option>
                                                <option value="temporary" <?= $r['employment_status'] === 'temporary' ? 'selected' : '' ?>>Temporary</option>
                                                <option value="contractual" <?= $r['employment_status'] === 'contractual' ? 'selected' : '' ?>>Contractual</option>
                                            </select>

                                            <input type="date" name="start_date" value="<?= $r['start_date'] ?>">
                                            <input type="date" name="monitoring_start_date"
                                                value="<?= $r['monitoring_start_date'] ?>">
                                            
                                            <textarea name="promotion_history" 
                                                placeholder="Promotion history"
                                                style="grid-column: 1 / -1;"><?= htmlspecialchars($r['promotion_history']) ?></textarea>
                                            
                                            <textarea name="remarks" 
                                                placeholder="Remarks"
                                                style="grid-column: 1 / -1;"><?= htmlspecialchars($r['remarks']) ?></textarea>

                                            <button type="submit" class="btn btn-small" style="grid-column: 1 / -1;">
                                                <i class="fas fa-save"></i> Update
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon"><i class="fas fa-users"></i></div>
                    <h4>No Employee Records</h4>
                    <p>No employees have been added to the tracking system yet.</p>
                    <p>Use the form on the left to add new employee records.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

       <!--  <div class="action-buttons">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="applications.php" class="btn">
                <i class="fas fa-file-alt"></i> View Applications
            </a>
        </div> -->
    </div>
</body>

</html>