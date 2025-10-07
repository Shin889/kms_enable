<?php require_once __DIR__ . '/../src/init.php';
require_role(['clerk', 'admin']);
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
  SELECT e.*, u.email
  FROM employee_tracking e
  JOIN users u ON u.uid=e.applicant_uid
  ORDER BY e.employee_id DESC
")->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Tracking</title>
    <link rel="stylesheet" href="assets/utils/employee_tracking.css">
</head>

<body>
    <div class="container">
        <h3>Employee Tracking</h3>
        <h4>Create New Employee Record</h4>
        <div class="form-container">
            <form method="post" class="form-grid">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label for="applicant_uid">Applicant UID</label>
                    <input id="applicant_uid" name="applicant_uid" type="text" required
                        placeholder="Enter applicant UID">
                </div>

                <div class="form-group">
                    <label for="employment_status">Employment Status</label>
                    <select id="employment_status" name="employment_status">
                        <option value="job_order">Job Order</option>
                        <option value="permanent">Permanent</option>
                        <option value="temporary">Temporary</option>
                        <option value="contractual">Contractual</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input id="start_date" name="start_date" type="date" required>
                </div>

                <div class="form-group">
                    <label for="monitoring_start_date">Monitoring Start Date</label>
                    <input id="monitoring_start_date" name="monitoring_start_date" type="date">
                </div>

                <div class="form-group full-width">
                    <label for="promotion_history">Promotion History</label>
                    <textarea id="promotion_history" name="promotion_history"
                        placeholder="Enter promotion history details..."></textarea>
                </div>

                <div class="form-group full-width">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" placeholder="Enter any additional remarks..."></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn">Create Employee Record</button>
                </div>
            </form>
        </div>
        <button onclick="printEmployeeReport()" class="btn" style="margin-bottom:10px;">🖨️ Print Report</button>
        <div id="printArea">
            <h4>Employee List</h4>
            <div class="table-container">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>Monitoring</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= $r['employee_id'] ?></td>
                                    <td>
                                        <a href="profile.php?uid=<?= $r['applicant_uid'] ?>">
                                            <?= htmlspecialchars($r['email']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $r['employment_status'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $r['employment_status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($r['start_date']) ?></td>
                                    <td><?= htmlspecialchars($r['monitoring_start_date']) ?></td>
                                    <td><?= htmlspecialchars($r['remarks']) ?></td>
                                    <td>
                                        <form method="post" class="update-form">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="employee_id" value="<?= $r['employee_id'] ?>">

                                            <select name="employment_status">
                                                <?php foreach (['job_order', 'permanent', 'temporary', 'contractual'] as $s): ?>
                                                    <option value="<?= $s ?>" <?= $r['employment_status'] === $s ? 'selected' : '' ?>>
                                                        <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <input type="date" name="start_date" value="<?= $r['start_date'] ?>">
                                            <input type="date" name="monitoring_start_date"
                                                value="<?= $r['monitoring_start_date'] ?>">
                                            <input name="promotion_history"
                                                value="<?= htmlspecialchars($r['promotion_history']) ?>"
                                                placeholder="Promotion history">
                                            <input name="remarks" value="<?= htmlspecialchars($r['remarks']) ?>"
                                                placeholder="Remarks">

                                            <button type="submit" class="btn btn-small">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/scripts/print.js"></script>

</body>

</html>