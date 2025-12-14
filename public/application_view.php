<?php
require_once __DIR__ . '/../src/init.php';
require_role(['admin', 'president']);

$currentUser = current_user();

function download_url($path) {
    if (!$path) return null;
    $path = str_replace('\\', '/', $path); 
    
    // Remove any leading slashes or dots
    $path = ltrim($path, './');
    
    // Check if path already has the correct directory prefix
    $allowedPrefixes = ['resumes/', 'cover_letters/', 'requirements/', 'uploads/', 'profile_pictures/'];
    
    foreach ($allowedPrefixes as $prefix) {
        if (strpos($path, $prefix) === 0) {
            return 'download_all.php?file=' . urlencode($path);
        }
    }
    
    // If no prefix found, try to determine based on file type
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
    // Try to guess directory based on context
    if (strpos($path, 'resume') !== false || strpos($path, 'cv') !== false || $extension === 'docx' || $extension === 'pdf') {
        return 'download_all.php?file=resumes/' . urlencode(basename($path));
    } elseif (strpos($path, 'cover') !== false) {
        return 'download_all.php?file=cover_letters/' . urlencode(basename($path));
    } elseif (strpos($path, 'requirement') !== false) {
        return 'download_all.php?file=requirements/' . urlencode(basename($path));
    }
    
    // Default fallback
    return 'download_all.php?file=' . urlencode(basename($path));
}

// Get application ID
$appId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($appId <= 0) {
    redirect('applications_admin.php');
}

// SAFE QUERY - Only select fields that definitely exist
$stmt = db()->prepare("
    SELECT a.*, 
           u.email AS applicant_email, 
           u.firstName, 
           u.lastName, 
           j.job_title,
           j.job_description AS job_description,
           au.email AS approver_email,
           au.firstName AS approver_firstName,
           au.lastName AS approver_lastName
    FROM applications a
    JOIN users u ON u.uid = a.applicant_uid
    JOIN job_vacancies j ON j.vacancy_id = a.vacancy_id
    LEFT JOIN users au ON au.uid = a.approved_by_uid
    WHERE a.application_id = ?
");
$stmt->execute([$appId]);
$app = $stmt->fetch();

if (!$app) {
    redirect('applications_admin.php');
}

// Get applicant profile separately - optional fields
$profileStmt = db()->prepare("
    SELECT mobilenumber, sex, civilstatus, dateofbirth, 
           profile_picture, work_experience, skills
    FROM applicant_profiles 
    WHERE applicant_uid = ?
");
$profileStmt->execute([$app['applicant_uid']]);
$profile = $profileStmt->fetch();

// Parse JSON fields safely
$workExperience = [];
$skills = [];

if ($profile) {
    if (!empty($profile['work_experience'])) {
        $decoded = @json_decode($profile['work_experience'], true);
        if (is_array($decoded)) {
            $workExperience = $decoded;
        }
    }
    
    if (!empty($profile['skills'])) {
        $decoded = @json_decode($profile['skills'], true);
        if (is_array($decoded)) {
            $skills = $decoded;
        }
    }
}

$firstName = htmlspecialchars($app['firstName'] ?? '');
$lastName = htmlspecialchars($app['lastName'] ?? '');
$fullName = $firstName . ' ' . $lastName;
$email = htmlspecialchars($app['applicant_email'] ?? '');
$status = $app['status'];
$statusClass = 'status-' . str_replace(' ', '_', strtolower($status));

// Format date of birth if exists
$dateOfBirth = '';
$age = '';
if (!empty($profile['dateofbirth'])) {
    $dateOfBirth = date('F j, Y', strtotime($profile['dateofbirth']));
    $birthDate = new DateTime($profile['dateofbirth']);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application #<?= $appId ?> | <?= strtoupper(current_user()['role']) ?></title>
    <link rel="stylesheet" href="assets/utils/application_view.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1><i class="fas fa-file-alt"></i> Application #<?= $appId ?></h1>
                <p><?= $fullName ?> - <?= htmlspecialchars($app['job_title']) ?></p>
            </div>
            <div>
                <a href="applications_admin.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Applicant Photo -->
            <?php if (!empty($profile['profile_picture'])): ?>
            <div style="text-align: center; margin-bottom: 30px;">
                <img src="../<?= htmlspecialchars($profile['profile_picture']) ?>" 
                     class="applicant-photo" 
                     alt="<?= $fullName ?>"
                     onerror="this.style.display='none'">
            </div>
            <?php endif; ?>

            <!-- Application Status -->
            <div class="section">
                <h3><i class="fas fa-info-circle"></i> Application Status</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Current Status:</span>
                        <span class="status-badge <?= $statusClass ?>">
                            <?php 
                            $statusText = str_replace('_', ' ', $status);
                            if ($statusText === 'approved by president') {
                                echo 'Pending Admin Approval';
                            } else {
                                echo htmlspecialchars($statusText);
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date Applied:</span>
                        <span class="info-value"><?= date('F j, Y g:i A', strtotime($app['date_applied'])) ?></span>
                    </div>
                    <?php if ($app['approver_email']): ?>
                    <div class="info-item">
                        <span class="info-label">Last Action By:</span>
                        <span class="info-value"><?= htmlspecialchars($app['approver_firstName'] . ' ' . $app['approver_lastName']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($app['approval_rejection_reason']): ?>
                    <div class="info-item">
                        <span class="info-label">Reason:</span>
                        <span class="info-value"><?= htmlspecialchars($app['approval_rejection_reason']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Applicant Personal Information -->
            <div class="section">
                <h3><i class="fas fa-user"></i> Personal Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name:</span>
                        <span class="info-value"><?= $fullName ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?= $email ?></span>
                    </div>
                    <?php if (!empty($profile['mobilenumber'])): ?>
                    <div class="info-item">
                        <span class="info-label">Mobile Number:</span>
                        <span class="info-value"><?= htmlspecialchars($profile['mobilenumber']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($profile['sex'])): ?>
                    <div class="info-item">
                        <span class="info-label">Gender:</span>
                        <span class="info-value"><?= htmlspecialchars($profile['sex']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($profile['civilstatus'])): ?>
                    <div class="info-item">
                        <span class="info-label">Civil Status:</span>
                        <span class="info-value"><?= htmlspecialchars($profile['civilstatus']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($profile['dateofbirth'])): ?>
                    <div class="info-item">
                        <span class="info-label">Date of Birth:</span>
                        <span class="info-value"><?= $dateOfBirth ?> (<?= $age ?> years old)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

          <!-- Applicant Actions -->
<div class="section">
    <h3><i class="fas fa-user-cog"></i> Applicant Actions</h3>
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <a href="profile.php?uid=<?= htmlspecialchars($app['applicant_uid']) ?>" 
           class="btn btn-primary"
           style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;">
            <i class="fas fa-user-circle"></i> View Full Profile
        </a>
        
        <a href="personal_data.php?uid=<?= htmlspecialchars($app['applicant_uid']) ?>" 
           class="btn btn-secondary"
           style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;">
            <i class="fas fa-id-card"></i> View Personal Data
        </a>
        
        <?php if (isset($currentUser) && in_array($currentUser['role'] ?? '', ['admin', 'president'])): ?>
        <a href="applications.php?uid=<?= htmlspecialchars($app['applicant_uid']) ?>" 
           class="btn btn-secondary"
           style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;">
            <i class="fas fa-file-alt"></i> View Applications
        </a>
        <?php endif; ?>
    </div>
    <p style="margin-top: 10px; color: #666; font-size: 14px;">
        <i class="fas fa-info-circle"></i> View detailed information about this applicant
    </p>
</div>

            <!-- Work Experience -->
            <?php if (!empty($workExperience)): ?>
            <div class="section">
                <h3><i class="fas fa-briefcase"></i> Work Experience</h3>
                <?php foreach ($workExperience as $work): ?>
                    <?php if (!empty($work['position']) || !empty($work['company'])): ?>
                    <div class="work-item">
                        <?php if (!empty($work['position'])): ?>
                            <strong><?= htmlspecialchars($work['position']) ?>:</strong><br>
                        <?php endif; ?>
                        <?php if (!empty($work['company'])): ?>
                            <?= htmlspecialchars($work['company']) ?>
                        <?php endif; ?>
                        <?php if (!empty($work['duration'])): ?>
                            (<?= htmlspecialchars($work['duration']) ?>)
                        <?php endif; ?>
                        <?php if (!empty($work['description'])): ?>
                            <p style="margin-top: 5px; color: #666;"><?= nl2br(htmlspecialchars($work['description'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Skills -->
            <?php if (!empty($skills)): ?>
            <div class="section">
                <h3><i class="fas fa-tools"></i> Skills</h3>
                <div>
                    <?php foreach ($skills as $skill): ?>
                        <?php if (!empty($skill)): ?>
                            <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Job Information -->
            <div class="section">
                <h3><i class="fas fa-briefcase"></i> Job Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Position:</span>
                        <span class="info-value"><?= htmlspecialchars($app['job_title']) ?></span>
                    </div>
                    <?php if ($app['job_description']): ?>
                    <div class="info-item">
                        <span class="info-label">Description:</span>
                        <span class="info-value" style="text-align: left;"><?= nl2br(htmlspecialchars($app['job_description'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Documents -->
            <div class="section">
                <h3><i class="fas fa-paperclip"></i> Application Documents</h3>
                <?php 
                $hasDocuments = false;
                if (!empty($app['resume_path'])) $hasDocuments = true;
                if (!empty($app['cover_letter_path'])) $hasDocuments = true;
                if (!empty($app['requirements_docs'])) $hasDocuments = true;
                ?>
                
                <?php if ($hasDocuments): ?>
                <div class="documents-grid">
                    <?php if (!empty($app['resume_path'])): ?>
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="document-info">
                            <h4>Resume / CV</h4>
                            <p>Submitted resume document</p>
                        </div>
                        <div>
                            <a href="<?= htmlspecialchars(download_url($app['resume_path'])) ?>" 
                               target="_blank" 
                               class="btn btn-primary">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($app['cover_letter_path'])): ?>
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="document-info">
                            <h4>Cover Letter</h4>
                            <p>Applicant's cover letter</p>
                        </div>
                        <div>
                            <a href="<?= htmlspecialchars(download_url($app['cover_letter_path'])) ?>" 
                               target="_blank" 
                               class="btn btn-primary">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($app['requirements_docs'])): 
                        $decoded = @json_decode($app['requirements_docs'], true);
                        $files = is_array($decoded) ? $decoded : [$app['requirements_docs']];
                        foreach ($files as $index => $file):
                            if (!$file) continue;
                    ?>
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-paperclip"></i>
                        </div>
                        <div class="document-info">
                            <h4>Additional Document <?= $index + 1 ?></h4>
                            <p>Supporting document</p>
                        </div>
                        <div>
                            <a href="<?= htmlspecialchars(download_url($file)) ?>" 
                               target="_blank" 
                               class="btn btn-primary">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-file-alt"></i>
                    <p>No documents submitted with this application.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Admin Actions -->
            <?php if (current_user()['role'] === 'admin' && $status === 'approved_by_president'): ?>
            <div class="section">
                <h3><i class="fas fa-cogs"></i> Admin Actions</h3>
                <p>This application has been approved by the President and is waiting for your final decision.</p>
                
                <div class="action-buttons">
                    <form method="post" action="process_application.php" style="display: inline;">
                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                        <input type="hidden" name="application_id" value="<?= $appId ?>">
                        <input type="hidden" name="action" value="admin_approve">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-check-circle"></i> Give Final Approval
                        </button>
                    </form>
                    
                    <button type="button" class="btn btn-danger btn-lg" onclick="showRejectForm()">
                        <i class="fas fa-times-circle"></i> Reject Application
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-times-circle"></i> Reject Application</h4>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" action="process_application.php">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="application_id" value="<?= $appId ?>">
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
                        <button type="button" class="btn btn-secondary" onclick="hideRejectForm()">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('rejectModal');
        const closeBtn = document.querySelector('.close');
        
        function showRejectForm() {
            modal.style.display = 'block';
            document.getElementById('rejection_reason').focus();
        }
        
        function hideRejectForm() {
            modal.style.display = 'none';
        }
        
        if (closeBtn) {
            closeBtn.onclick = hideRejectForm;
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                hideRejectForm();
            }
        }
        
        // Confirm approval
        document.querySelectorAll('form button[type="submit"]').forEach(button => {
            if (button.textContent.includes('Final Approval')) {
                button.onclick = function(e) {
                    if (!confirm('Are you sure you want to give final approval to this application?')) {
                        e.preventDefault();
                    }
                };
            }
        });
    </script>
</body>
</html>