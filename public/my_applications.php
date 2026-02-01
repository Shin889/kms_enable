<?php 
require_once __DIR__ . '/../src/init.php'; 
// require_once __DIR__ . '/dashboard_sidebar.php';  
require_role(['applicant']);

$st = db()->prepare("SELECT a.*, j.job_title, j.candidate_slot FROM applications a JOIN job_vacancies j ON a.vacancy_id=j.vacancy_id WHERE a.applicant_uid=? ORDER BY a.date_applied DESC");
$st->execute([$_SESSION['uid']]); 
$apps = $st->fetchAll();

// $page_title = "My Applications";
// $user = current_user(); 
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications | KMS Enable Recruitment</title>
    <!-- <link rel="stylesheet" href="assets/utils/dashboard.css"> -->
    <link rel="stylesheet" href="assets/utils/my_applications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- <div class="dashboard-layout">
        <?php render_sidebar($user, 'my_applications'); ?>
        
        <main class="tri-content">
            <?php render_topbar($user, $page_title); ?>
            
            <div class="content-wrapper"> -->
                <div class="container">
                        <div class="header">
                            <h3>My Applications</h3>
                            <p class="subtitle">Track the status of your job applications</p>
                            
                            <?php 
                            $stats = [
                                'pending' => 0,
                                'reviewing' => 0,
                                'shortlisted' => 0,
                                'approved' => 0,
                                'rejected' => 0
                            ];
                            
                            foreach ($apps as $app) {
                                $status = strtolower($app['status']);
                                if (isset($stats[$status])) {
                                    $stats[$status]++;
                                } else {
                                    $stats['reviewing']++;
                                }
                            }
                            
                            $totalApplications = count($apps);
                            ?>
                            
                            <div class="stats-container">
                                <div class="stat-card">
                                    <div class="stat-number"><?= $totalApplications ?></div>
                                    <div class="stat-label">Total Applications</div>
                                </div>
                                <div class="stat-card pending">
                                    <div class="stat-number"><?= $stats['pending'] ?></div>
                                    <div class="stat-label">Pending</div>
                                </div>
                                <div class="stat-card shortlisted">
                                    <div class="stat-number"><?= $stats['shortlisted'] ?></div>
                                    <div class="stat-label">Shortlisted</div>
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

                        <?php if (!empty($apps)): ?>
                        <div class="applications-container">
                            <div class="table-header">
                                <h4>Application History</h4>
                            </div>
                            <table class="applications-table">
                                <thead>
                                    <tr>
                                        <th>Job Position</th>
                                        <th>Status</th>
                                        <th>Interview Date</th>
                                        <th>Remarks / Reason</th>
                                        <th>Date Applied</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($apps as $a): 
                                        $statusClass = 'status-' . strtolower($a['status']);
                                        $interviewDate = !empty($a['interview_date']) && $a['interview_date'] !== '0000-00-00 00:00:00' 
                                            ? date('M j, Y \a\t g:i A', strtotime($a['interview_date']))
                                            : null;
                                    ?>
                                        <tr>
                                            <td data-label="Job Position">
                                                <strong><?= htmlspecialchars($a['job_title']) ?></strong>
                                                <?php if (!empty($a['candidate_slot'])): ?>
                                                    <br><small style="color: var(--muted);">Slot: <?= htmlspecialchars($a['candidate_slot']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Status">
                                                <span class="status-badge <?= $statusClass ?>">
                                                    <?= htmlspecialchars($a['status']) ?>
                                                </span>
                                            </td>
                                            <td data-label="Interview Date">
                                                <?php if ($interviewDate): ?>
                                                    <div class="interview-date">
                                                        <i class="fas fa-calendar-alt"></i>
                                                        <?= $interviewDate ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="no-interview">Not scheduled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Remarks / Reason" class="reason-text">
                                                <?php if (!empty($a['approval_rejection_reason'])): ?>
                                                    <?= nl2br(htmlspecialchars($a['approval_rejection_reason'])) ?>
                                                <?php else: ?>
                                                    <span style="color: var(--muted); font-style: italic;">No remarks yet</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Date Applied">
                                                <?= date('M j, Y', strtotime($a['date_applied'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="no-data">
                            <div class="no-data-icon"><i class="fas fa-file-alt"></i></div>
                            <h4>No Applications Yet</h4>
                            <p>You haven't applied to any job vacancies yet.</p>
                            <p>Browse available positions and submit your applications.</p>
                        <!--   <div class="action-links">
                                <a href="vacancies.php" class="action-link">
                                    <i class="fas fa-briefcase"></i> Browse Job Vacancies
                                </a>
                                <a href="index.php" class="action-link secondary">
                                    <i class="fas fa-home"></i> Back to Dashboard
                                </a>
                            </div> -->
                        </div>
                        <?php endif; ?>

                    <!--  <?php if (!empty($apps)): ?>
                        <div class="action-links">
                            <a href="vacancies.php" class="action-link">
                                <i class="fas fa-plus-circle"></i> Apply for More Jobs
                            </a>
                            <a href="dashboard.php" class="action-link secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                        <?php endif; ?> -->
                    </div>
                <!-- </div>
        </main>
    </div>        -->
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .applications-table {
            width: 100%;
            overflow-x: auto;
        }
        
        .stats-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .stat-card {
            flex: 1;
            min-width: 120px;
        }
        
        @media (max-width: 768px) {
            .stats-container {
                flex-direction: column;
            }
            
            .stat-card {
                width: 100%;
            }
        }
    </style>            
</body>
</html>