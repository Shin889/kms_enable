<?php
require_once __DIR__ . '/../src/init.php';
require_role(['president']);
require_once __DIR__ . '/dashboard_sidebar.php';

$page_title = "Applications Review";
$user = current_user();
csrf_check();

function download_url($path) {
    if (!$path) return null;
    $path = str_replace('\\', '/', $path); 
    
    // If path already has 'uploads/', remove it
    if (strpos($path, 'uploads/') === 0) {
        $path = substr($path, 8); // Remove 'uploads/'
    }
    
    // Simple relative path since both files are in /public
    return 'download_all.php?file=' . urlencode($path);
}

// Check if a specific job ID is requested
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Build the SQL query based on whether we're filtering by job_id
$sql = "
    SELECT a.*, 
           u.email AS applicant_email, 
           u.firstName, 
           u.lastName, 
           j.job_title,
           j.vacancy_id,
           ap.profile_picture,
           a.status
    FROM applications a
    JOIN users u ON u.uid = a.applicant_uid
    JOIN job_vacancies j ON j.vacancy_id = a.vacancy_id
    LEFT JOIN applicant_profiles ap ON ap.applicant_uid = a.applicant_uid
";

// Add WHERE clause if filtering by job_id
$params = [];
if ($job_id > 0) {
    $sql .= " WHERE j.vacancy_id = ?";
    $params[] = $job_id;
    
    // Get job title for the header
    $job_stmt = db()->prepare("SELECT job_title FROM job_vacancies WHERE vacancy_id = ?");
    $job_stmt->execute([$job_id]);
    $job_info = $job_stmt->fetch();
    $job_title = $job_info ? htmlspecialchars($job_info['job_title']) : 'Unknown Job';
}

$sql .= " ORDER BY 
    CASE a.status
        WHEN 'submitted' THEN 1
        WHEN 'approved_by_president' THEN 2
        WHEN 'approved_by_admin' THEN 3
        ELSE 4
    END,
    a.date_applied DESC";

// Execute query
$stmt = db()->prepare($sql);
$stmt->execute($params);
$apps = $stmt->fetchAll();

$count = count($apps);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications Review | President</title>
    <link rel="stylesheet" href="assets/utils/dashboard.css">
    <link rel="stylesheet" href="assets/utils/applications_review.css">
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
        }
        .applicant-avatar-small img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
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
        
        .breadcrumb {
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }
        .breadcrumb a {
            color: #4361ee;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb span {
            margin: 0 5px;
        }
        .filter-badge {
            display: inline-block;
            background: #4361ee;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php render_sidebar($user, 'applications_overview'); ?>
        
        <main class="tri-content">
            <?php 
            $topbar_title = $job_id > 0 ? "Applications for: " . $job_title : "Applications Review";
            render_topbar($user, $topbar_title); 
            ?>
            
            <div class="content-wrapper">
                <div class="container">
                    <div class="header">
                        <div class="breadcrumb">
                            <a href="applications_overview.php"><i class="fas fa-arrow-left"></i> Back to Overview</a>
                            <?php if ($job_id > 0): ?>
                                <span>›</span>
                                <span>Job: <?= $job_title ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <h3><i class="fas fa-user-tie"></i> Applications Review - President</h3>
                        <p class="subtitle">
                            View all applications. Applications are now approved/rejected by Admin only.
                            <?php if ($job_id > 0): ?>
                                <span class="filter-badge">
                                    <i class="fas fa-filter"></i> Filtered by: <?= $job_title ?>
                                </span>
                            <?php endif; ?>
                        </p>
                        
                        <div class="stats-container">
                            <div class="stat-card total">
                                <div class="stat-number"><?= $count ?></div>
                                <div class="stat-label">
                                    <?= $job_id > 0 ? 'Applications for Job' : 'Total Applications' ?>
                                </div>
                            </div>
                            
                            <?php if ($job_id > 0): ?>
                            <a href="applications_review.php" class="stat-card view-all" style="text-decoration: none; color: inherit;">
                                <div class="stat-number"><i class="fas fa-eye"></i></div>
                                <div class="stat-label">View All</div>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($apps)): ?>
                    <div class="applications-container">
                        <div class="table-header">
                            <h4><i class="fas fa-list"></i> 
                                <?php if ($job_id > 0): ?>
                                    Applications for "<?= $job_title ?>" (<?= $count ?>)
                                <?php else: ?>
                                    All Applications (<?= $count ?>)
                                <?php endif; ?>
                            </h4>
                        </div>
                        <table class="applications-table">
                            <thead>
                                <tr>
                                    <th>App ID</th>
                                    <th>Applicant</th>
                                    <th>Job Position</th>
                                    <th>Status</th>
                                    <th>Date Applied</th>
                                    <th>Documents</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($apps as $a): 
                                    $statusClass = 'status-' . $a['status'];
                                    $applicantName = trim(htmlspecialchars($a['firstName'] . ' ' . $a['lastName']));
                                    $initials = strtoupper(substr($a['firstName'], 0, 1) . substr($a['lastName'], 0, 1));
                                    $email = htmlspecialchars($a['applicant_email']);
                                    
                                    // Status text formatting
                                    $statusText = str_replace('_', ' ', $a['status']);
                                    if ($statusText === 'approved by president') {
                                        $statusText = 'Pending Admin Approval';
                                    }
                                ?>
                                    <tr>
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
                                        <td>
                                            <?= htmlspecialchars($a['job_title']) ?>
                                            <?php if ($job_id == 0): ?>
                                                <br>
                                                <small style="color: #666; font-size: 11px;">
                                                    Job ID: #<?= $a['vacancy_id'] ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $statusClass ?>">
                                                <?= htmlspecialchars($statusText) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($a['date_applied'])) ?></td>
                                        <td>
                                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                <?php if (!empty($a['resume_path'])): ?>
                                                    <a href="<?= htmlspecialchars(download_url($a['resume_path'])) ?>" 
                                                    target="_blank" 
                                                    class="btn btn-secondary btn-sm">
                                                        <i class="fas fa-file-pdf"></i> Resume
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($a['cover_letter_path'])): ?>
                                                    <a href="<?= htmlspecialchars(download_url($a['cover_letter_path'])) ?>" 
                                                    target="_blank" 
                                                    class="btn btn-secondary btn-sm">
                                                        <i class="fas fa-file-alt"></i> Cover
                                                    </a>
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
                            <p>
                                <?php if ($job_id > 0): ?>
                                    No applications found for this job vacancy.
                                <?php else: ?>
                                    There are no applications to view at this time.
                                <?php endif; ?>
                            </p>
                            <?php if ($job_id > 0): ?>
                                <a href="applications_review.php" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View All Applications
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add row highlighting on hover
            const rows = document.querySelectorAll('.applications-table tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });
    </script>
</body>
</html>