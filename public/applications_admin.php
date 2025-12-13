<?php 
require_once __DIR__ . '/../src/init.php'; 
require_role(['admin']);

$apps = db()->query("
  SELECT a.*, u.email AS applicant_email, u.firstName, u.lastName, j.job_title
  FROM applications a
  JOIN users u ON u.uid = a.applicant_uid
  JOIN job_vacancies j ON j.vacancy_id = a.vacancy_id
  WHERE a.status IN ('approved_by_president','approved_by_admin','interviewed')
  ORDER BY a.date_applied DESC
")->fetchAll();

// Get counts for different statuses
$counts = [
    'total' => count($apps),
    'approved_by_president' => 0,
    'approved_by_admin' => 0,
    'interviewed' => 0
];

foreach ($apps as $app) {
    if (isset($counts[$app['status']])) {
        $counts[$app['status']]++;
    }
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
</head>
<body>
    <div class="container">
        <div class="header">
            <h3>Applications Dashboard</h3>
            <p class="subtitle">Manage and review applications that have passed initial screening</p>
            
            <div class="stats-container">
                <div class="stat-card total">
                    <div class="stat-number"><?= $counts['total'] ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card president">
                    <div class="stat-number"><?= $counts['approved_by_president'] ?></div>
                    <div class="stat-label">President Approved</div>
                </div>
                <div class="stat-card admin">
                    <div class="stat-number"><?= $counts['approved_by_admin'] ?></div>
                    <div class="stat-label">Admin Approved</div>
                </div>
                <div class="stat-card interviewed">
                    <div class="stat-number"><?= $counts['interviewed'] ?></div>
                    <div class="stat-label">Interviewed</div>
                </div>
            </div>
        </div>

        <div class="filter-container">
            <div class="filter-group">
                <label class="filter-label">Filter by Status</label>
                <select class="filter-select" id="statusFilter">
                    <option value="all">All Statuses</option>
                    <option value="approved_by_president">President Approved</option>
                    <option value="approved_by_admin">Admin Approved</option>
                    <option value="interviewed">Interviewed</option>
                </select>
            </div>
            
            <div class="filter-search">
                <label class="filter-label">Search Applications</label>
                <div class="search-box">
                    <span class="search-icon"><i class="fas fa-search"></i></span>
                    <input type="text" class="search-input" placeholder="Search by job title, applicant name or email..." id="searchInput">
                </div>
            </div>
        </div>

        <?php if (!empty($apps)): ?>
        <div class="applications-container">
            <div class="table-header">
                <h4>Advanced Applications</h4>
                <div class="table-count">
                    Showing <?= count($apps) ?> applications
                </div>
            </div>
            <table class="applications-table" id="applicationsTable">
                <thead>
                    <tr>
                        <th>Application ID</th>
                        <th>Job Position</th>
                        <th>Applicant</th>
                        <th>Status</th>
                        <th>Date Applied</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apps as $a): 
                        $statusClass = 'status-' . $a['status'];
                        $applicantName = trim(htmlspecialchars($a['firstName'] . ' ' . $a['lastName']));
                    ?>
                        <tr class="application-row" 
                            data-status="<?= $a['status'] ?>"
                            data-job="<?= htmlspecialchars(strtolower($a['job_title'])) ?>"
                            data-applicant="<?= htmlspecialchars(strtolower($applicantName . ' ' . $a['applicant_email'])) ?>">
                            <td>
                                <span class="application-id"><?= htmlspecialchars($a['application_id']) ?></span>
                                <br>
                                <a href="alt_manage.php?id=<?= urlencode($a['application_id']) ?>" class="application-link">
                                    <i class="fas fa-external-link-alt"></i>
                                    Manage Application
                                </a>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($a['job_title']) ?></strong>
                            </td>
                            <td>
                                <div class="applicant-name"><?= $applicantName ?></div>
                                <div class="applicant-email"><?= htmlspecialchars($a['applicant_email']) ?></div>
                            </td>
                            <td>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $a['status'])) ?>
                                </span>
                            </td>
                            <td class="date-applied">
                                <?= date('M j, Y', strtotime($a['date_applied'])) ?>
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
                <h4>No Advanced Applications</h4>
                <p>There are no applications in advanced review stages.</p>
                <p>Applications will appear here once they are approved by President or admin.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- <div class="action-links">
            <a href="dashboard.php" class="action-link secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="applications_all.php" class="action-link">
                <i class="fas fa-list"></i> View All Applications
            </a>
        </div> -->
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusFilter = document.getElementById('statusFilter');
            const searchInput = document.getElementById('searchInput');
            const applicationRows = document.querySelectorAll('.application-row');
            
            function filterApplications() {
                const selectedStatus = statusFilter.value;
                const searchTerm = searchInput.value.toLowerCase();
                let visibleCount = 0;
                
                applicationRows.forEach(row => {
                    const status = row.getAttribute('data-status');
                    const job = row.getAttribute('data-job');
                    const applicant = row.getAttribute('data-applicant');
                    
                    let statusMatch = selectedStatus === 'all' || status === selectedStatus;
                    let searchMatch = !searchTerm || 
                        job.includes(searchTerm) || 
                        applicant.includes(searchTerm);
                    
                    if (statusMatch && searchMatch) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Update table count
                const tableCount = document.querySelector('.table-count');
                if (tableCount) {
                    tableCount.textContent = `Showing ${visibleCount} applications`;
                }
            }
            
            statusFilter.addEventListener('change', filterApplications);
            searchInput.addEventListener('input', filterApplications);
        });
    </script>
</body>
</html>