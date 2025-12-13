<?php
require_once __DIR__ . '/../src/init.php';
require_role(['president', 'admin']);
csrf_check();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications Overview | PRESIDENT</title>
    <link rel="stylesheet" href="assets/utils/applications_overview.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .view-all-link {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 16px;
            background: #4361ee;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            transition: background 0.3s;
        }
        .view-all-link:hover {
            background: #3a56d4;
        }
        .view-all-link i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tasks"></i> Applications Overview</h1>
            <p class="subtitle">Review and manage applications for all job vacancies</p>
            
            <!-- Add View All Applications link -->
            <a href="applications_review.php" class="view-all-link">
                <i class="fas fa-eye"></i> View All Applications
            </a>
        </div>

        <div class="stats-container">
            <?php
            // Get overall statistics
            $statsStmt = db()->prepare("
                SELECT 
                    COUNT(DISTINCT j.vacancy_id) as total_jobs,
                    COUNT(a.application_id) as total_applications,
                    SUM(CASE WHEN a.status = 'submitted' THEN 1 ELSE 0 END) as submitted_apps,
                    SUM(CASE WHEN a.status IN ('approved_by_president', 'approved_by_admin', 'approved_by_hrmpsb') THEN 1 ELSE 0 END) as approved_apps,
                    SUM(CASE WHEN a.status IN ('rejected_by_president', 'rejected_by_admin', 'rejected_by_hrmpsb', 'rejected_final') THEN 1 ELSE 0 END) as rejected_apps
                FROM job_vacancies j
                LEFT JOIN applications a ON j.vacancy_id = a.vacancy_id
                WHERE j.status = 'open'
            ");
            $statsStmt->execute();
            $overallStats = $statsStmt->fetch();
            ?>
            <div class="stat-card open-jobs">
                <div class="stat-number"><?= $overallStats['total_jobs'] ?? 0 ?></div>
                <div class="stat-label">Open Jobs</div>
            </div>
            <div class="stat-card total">
                <div class="stat-number"><?= $overallStats['total_applications'] ?? 0 ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-number"><?= $overallStats['submitted_apps'] ?? 0 ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-number"><?= $overallStats['approved_apps'] ?? 0 ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-number"><?= $overallStats['rejected_apps'] ?? 0 ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <div class="content-section">
            <h2 class="section-title"><i class="fas fa-list-alt"></i> Job Vacancies</h2>
            
            <?php
            // Get all job vacancies with application counts
            $stmt = db()->prepare("
                SELECT 
                    j.vacancy_id,
                    j.job_title,
                    j.date_posted,
                    j.application_deadline,
                    j.status as job_status,
                    COUNT(a.application_id) as total_applications,
                    SUM(CASE WHEN a.status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
                    SUM(CASE WHEN a.status IN ('approved_by_president', 'approved_by_admin', 'approved_by_hrmpsb') THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN a.status IN ('rejected_by_president', 'rejected_by_admin', 'rejected_by_hrmpsb', 'rejected_final') THEN 1 ELSE 0 END) as rejected_count
                FROM job_vacancies j
                LEFT JOIN applications a ON j.vacancy_id = a.vacancy_id
                WHERE j.status = 'open'
                GROUP BY j.vacancy_id, j.job_title, j.date_posted, j.application_deadline, j.status
                ORDER BY j.date_posted DESC
            ");
            $stmt->execute();
            $jobs = $stmt->fetchAll();
            ?>
            
            <div class="table-container">
                <?php if (empty($jobs)): ?>
                    <div class="no-data">
                        <div class="no-data-icon"><i class="fas fa-folder-open"></i></div>
                        <h3>No Job Vacancies Found</h3>
                        <p>There are currently no open job vacancies to review.</p>
                    </div>
                <?php else: ?>
                    <table class="jobs-table">
                        <thead>
                            <tr>
                                <th>Job ID</th>
                                <th>Job Title</th>
                                <th>Posted Date</th>
                                <th>Deadline</th>
                                <th>Total</th>
                                <th>Submitted</th>
                                <th>Approved</th>
                                <th>Rejected</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): 
                                $vacancyId = $job['vacancy_id'];
                                $jobTitle = htmlspecialchars($job['job_title']);
                                $datePosted = date('M j, Y', strtotime($job['date_posted']));
                                $deadline = $job['application_deadline'] ? date('M j, Y', strtotime($job['application_deadline'])) : 'No deadline';
                                $totalApps = $job['total_applications'];
                                $submittedCount = $job['submitted_count'];
                                $approvedCount = $job['approved_count'];
                                $rejectedCount = $job['rejected_count'];
                            ?>
                                <tr data-job-id="<?= $vacancyId ?>">
                                    <td data-label="Job ID">
                                        <span class="job-id">#<?= $vacancyId ?></span>
                                    </td>
                                    <td data-label="Job Title" class="job-title-cell"><?= $jobTitle ?></td>
                                    <td data-label="Posted Date" class="date-cell"><?= $datePosted ?></td>
                                    <td data-label="Deadline" class="date-cell"><?= $deadline ?></td>
                                    <td data-label="Total" class="count-cell">
                                        <span class="count-badge count-total"><?= $totalApps ?></span>
                                    </td>
                                    <td data-label="Submitted" class="count-cell">
                                        <span class="count-badge count-submitted"><?= $submittedCount ?></span>
                                    </td>
                                    <td data-label="Approved" class="count-cell">
                                        <span class="count-badge count-approved"><?= $approvedCount ?></span>
                                    </td>
                                    <td data-label="Rejected" class="count-cell">
                                        <span class="count-badge count-rejected"><?= $rejectedCount ?></span>
                                    </td>
                                    <td data-label="Actions" class="action-cell">
                                        <a href="applications_review.php?job_id=<?= $vacancyId ?>" class="review-btn">
                                            <i class="fas fa-eye"></i> View Applications
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer">
            <p>KMS RecruitHub - President Dashboard | <?= date('Y') ?></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add click animation to review buttons
            document.querySelectorAll('.review-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 200);
                });
            });

            // Make entire table row clickable on mobile
            if (window.innerWidth <= 768) {
                document.querySelectorAll('.jobs-table tbody tr').forEach(row => {
                    const link = row.querySelector('.review-btn');
                    if (link) {
                        row.style.cursor = 'pointer';
                        row.addEventListener('click', function(e) {
                            if (!e.target.closest('.review-btn')) {
                                window.location.href = link.href;
                            }
                        });
                    }
                });
            }
        });
    </script>
</body>
</html>