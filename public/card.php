<?php
require_once __DIR__ . '/../src/init.php';
require_role(['president', 'admin']);
require_once __DIR__ . '/dashboard_sidebar.php';
$page_title = "Applications Overview";
$user = current_user(); 

csrf_check();

// Get job vacancies with application counts and status breakdown
$stmt = db()->query("
    SELECT 
        j.vacancy_id, 
        j.job_title, 
        j.application_deadline,
        j.status as job_status,
        COUNT(a.application_id) AS total_applications,
        SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN a.status = 'under_review' THEN 1 ELSE 0 END) AS under_review_count,
        SUM(CASE WHEN a.status = 'shortlisted' THEN 1 ELSE 0 END) AS shortlisted_count,
        SUM(CASE WHEN a.status = 'interviewed' THEN 1 ELSE 0 END) AS interviewed_count,
        SUM(CASE WHEN a.status = 'approved_by_hrmpsb' THEN 1 ELSE 0 END) AS approved_by_hrmpsb_count,
        SUM(CASE WHEN a.status = 'approved_by_admin' THEN 1 ELSE 0 END) AS approved_by_admin_count,
        SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
    FROM job_vacancies j
    LEFT JOIN applications a ON j.vacancy_id = a.vacancy_id
    WHERE j.status = 'open'
    GROUP BY j.vacancy_id, j.job_title, j.application_deadline, j.status
    ORDER BY j.date_posted DESC
");
$jobs = $stmt->fetchAll();

// Get overall statistics
$stats = [
    'total_jobs' => count($jobs),
    'total_applications' => 0,
    'pending_applications' => 0,
    'active_reviews' => 0,
    'upcoming_deadlines' => 0
];

foreach ($jobs as $job) {
    $stats['total_applications'] += $job['total_applications'];
    $stats['pending_applications'] += $job['pending_count'];
    $stats['active_reviews'] += $job['under_review_count'] + $job['shortlisted_count'] + $job['interviewed_count'];
    
    // Check for upcoming deadlines (within 7 days)
    if (!empty($job['application_deadline'])) {
        $deadline = strtotime($job['application_deadline']);
        $sevenDays = strtotime('+7 days');
        if ($deadline <= $sevenDays && $deadline >= time()) {
            $stats['upcoming_deadlines']++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Applications Overview | HRMPSB</title>
  <link rel="stylesheet" href="assets/utils/dashboard.css">
  <link rel="stylesheet" href="assets/utils/card.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>

  </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php render_sidebar($user, 'applications_overview'); ?>
        
        <main class="tri-content">
            <?php render_topbar($user, $page_title); ?>
            
            <div class="content-wrapper">
                <div class="page-content"></div>
                    <div class="container">
                        <div class="header">
                            <h2>Applications Overview</h2>
                            <p class="subtitle">Review and manage applications for open job vacancies</p>
                            
                            <div class="stats-container">
                                <div class="stat-card total-jobs">
                                    <div class="stat-number"><?= $stats['total_jobs'] ?></div>
                                    <div class="stat-label">Open Jobs</div>
                                </div>
                                <div class="stat-card total-apps">
                                    <div class="stat-number"><?= $stats['total_applications'] ?></div>
                                    <div class="stat-label">Total Applications</div>
                                </div>
                                <div class="stat-card pending">
                                    <div class="stat-number"><?= $stats['pending_applications'] ?></div>
                                    <div class="stat-label">Pending Review</div>
                                </div>
                                <div class="stat-card active">
                                    <div class="stat-number"><?= $stats['active_reviews'] ?></div>
                                    <div class="stat-label">In Progress</div>
                                </div>
                                <div class="stat-card deadlines">
                                    <div class="stat-number"><?= $stats['upcoming_deadlines'] ?></div>
                                    <div class="stat-label">Upcoming Deadlines</div>
                                </div>
                            </div>
                        </div>

                        <div class="jobs-header">
                            <h3><i class="fas fa-briefcase"></i> Open Job Vacancies</h3>
                            <div class="jobs-count"><?= count($jobs) ?> job<?= count($jobs) !== 1 ? 's' : '' ?></div>
                        </div>
                        
                        <?php if (!empty($jobs)): ?>
                        <div class="cards-container">
                            <?php foreach ($jobs as $job): 
                                $totalApps = (int) $job['total_applications'];
                                $deadlineClass = '';
                                
                                if (!empty($job['application_deadline'])) {
                                    $deadline = strtotime($job['application_deadline']);
                                    $now = time();
                                    $daysLeft = floor(($deadline - $now) / (60 * 60 * 24));
                                    
                                    if ($daysLeft <= 3 && $daysLeft >= 0) {
                                        $deadlineClass = 'deadline-urgent';
                                    } elseif ($daysLeft < 0) {
                                        $deadlineClass = 'deadline-passed';
                                    }
                                }
                            ?>
                            <div class="job-card" onclick="window.location.href='applications_review.php?job_id=<?= $job['vacancy_id'] ?>'">
                                <div class="job-title"><?= htmlspecialchars($job['job_title']) ?></div>
                                
                                <?php if (!empty($job['application_deadline'])): ?>
                                <div class="deadline-info <?= $deadlineClass ?>">
                                    <i class="fas fa-clock"></i>
                                    <div class="deadline-text">
                                        Application Deadline: 
                                        <span class="deadline-date">
                                            <?= date('M j, Y', strtotime($job['application_deadline'])) ?>
                                        </span>
                                        <?php if ($deadlineClass === 'deadline-urgent'): ?>
                                            <span style="color: var(--danger); font-weight: 600;"> (Urgent)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="applications-overview">
                                    <div class="overview-title">Application Status Breakdown</div>
                                    <div class="status-grid">
                                        <div class="status-item status-pending">
                                            <div class="status-count"><?= (int) $job['pending_count'] ?></div>
                                            <div class="status-label">Pending</div>
                                        </div>
                                        <div class="status-item status-review">
                                            <div class="status-count"><?= (int) $job['under_review_count'] ?></div>
                                            <div class="status-label">Under Review</div>
                                        </div>
                                        <div class="status-item status-shortlisted">
                                            <div class="status-count"><?= (int) $job['shortlisted_count'] ?></div>
                                            <div class="status-label">Shortlisted</div>
                                        </div>
                                        <div class="status-item status-interviewed">
                                            <div class="status-count"><?= (int) $job['interviewed_count'] ?></div>
                                            <div class="status-label">Interviewed</div>
                                        </div>
                                        <div class="status-item status-approved-hrmpsb">
                                            <div class="status-count"><?= (int) $job['approved_by_hrmpsb_count'] ?></div>
                                            <div class="status-label">HRMPSB Approved</div>
                                        </div>
                                        <div class="status-item status-rejected">
                                            <div class="status-count"><?= (int) $job['rejected_count'] ?></div>
                                            <div class="status-label">Rejected</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="application-total">
                                    <div class="total-label">Total Applications</div>
                                    <div class="total-count"><?= $totalApps ?></div>
                                </div>
                                
                                <div class="card-footer">
                                    <div class="progress-indicator">
                                        <i class="fas fa-chart-line"></i>
                                        <span><?= $totalApps > 0 ? 'Review in progress' : 'No applications yet' ?></span>
                                    </div>
                                    <a href="applications_review.php?job_id=<?= $job['vacancy_id'] ?>" class="view-applications" onclick="event.stopPropagation()">
                                        <i class="fas fa-external-link-alt"></i>
                                        View Applications
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="no-jobs">
                            <div class="no-jobs-icon"><i class="fas fa-briefcase"></i></div>
                            <h4>No Open Job Vacancies</h4>
                            <p>There are currently no open job vacancies to review applications for.</p>
                            <p>Job vacancies will appear here once they are created and published.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                 </div>
            </div>
        </main>
    </div>
</body>
</html>