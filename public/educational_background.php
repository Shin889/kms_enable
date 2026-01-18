<?php
// educational-background.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/upload.php';

$currentUser = current_user();
if (!$currentUser) {
    header("Location: login.php");
    exit;
}

// Check if user is allowed to view personal data
$allowedRoles = ['applicant', 'admin', 'president'];
if (!in_array($currentUser['role'], $allowedRoles)) {
    header("Location: dashboard.php");
    exit;
}

// Determine which user's data to show
if ($currentUser['role'] === 'applicant') {
    // Applicants can only view their own data
    $view_uid = $currentUser['uid'];
    $canEdit = true;
} else {
    // Admins/Presidents can view other users' data if uid is provided
    $view_uid = isset($_GET['uid']) ? $_GET['uid'] : $currentUser['uid'];
    $canEdit = false; // Admins can view but not edit other users' data
}

// Get user UID
if (!$view_uid) {
    die("Error: Could not identify user UID");
}

// Get user details
try {
    $userStmt = db()->prepare("SELECT * FROM users WHERE uid = ?");
    $userStmt->execute([$view_uid]);
    $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$targetUser) {
        die("Error: User not found");
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Initialize variables
$personalData = [];
$error = '';
$success = '';

// Check if applicant profile exists
try {
    $stmt = db()->prepare("SELECT * FROM applicant_profiles WHERE applicant_uid = ?");
    $stmt->execute([$view_uid]);
    $personalData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no profile exists, create a basic one (only for applicants editing their own data)
    if (!$personalData && $canEdit) {
        $stmt = db()->prepare("INSERT INTO applicant_profiles (applicant_uid) VALUES (?)");
        $stmt->execute([$view_uid]);
        
        $stmt = db()->prepare("SELECT * FROM applicant_profiles WHERE applicant_uid = ?");
        $stmt->execute([$view_uid]);
        $personalData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submission (only if user can edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    try {
        // CSRF protection
        csrf_check();
        
        // Get form data
        $data = $_POST;
        
        // Prepare the update query for educational background fields
        $updateFields = [
            'elementary_school' => $data['elementary_school'] ?? '',
            'elementary_degree' => $data['elementary_degree'] ?? '',
            'elementary_year' => $data['elementary_year'] ?? '',
            'elementary_honors' => $data['elementary_honors'] ?? '',
            'secondary_school' => $data['secondary_school'] ?? '',
            'secondary_degree' => $data['secondary_degree'] ?? '',
            'secondary_year' => $data['secondary_year'] ?? '',
            'secondary_honors' => $data['secondary_honors'] ?? '',
            'vocational_school' => $data['vocational_school'] ?? '',
            'vocational_degree' => $data['vocational_degree'] ?? '',
            'vocational_year' => $data['vocational_year'] ?? '',
            'vocational_honors' => $data['vocational_honors'] ?? '',
            'college_school' => $data['college_school'] ?? '',
            'college_degree' => $data['college_degree'] ?? '',
            'college_year' => $data['college_year'] ?? '',
            'college_honors' => $data['college_honors'] ?? '',
            'graduate_school' => $data['graduate_school'] ?? '',
            'graduate_degree' => $data['graduate_degree'] ?? '',
            'graduate_year' => $data['graduate_year'] ?? '',
            'graduate_honors' => $data['graduate_honors'] ?? '',
        ];
        
        // Build the SQL update query
        $setClauses = [];
        $params = [];
        foreach ($updateFields as $field => $value) {
            $setClauses[] = "`$field` = ?";
            $params[] = $value;
        }
        $params[] = $view_uid;
        
        $sql = "UPDATE applicant_profiles SET " . implode(', ', $setClauses) . " WHERE applicant_uid = ?";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        
        $success = "Educational background information saved successfully!";
        
        // Refresh the personal data
        $stmt = db()->prepare("SELECT * FROM applicant_profiles WHERE applicant_uid = ?");
        $stmt->execute([$view_uid]);
        $personalData = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = "Error saving data: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $canEdit ? 'Edit Educational Background' : 'View Educational Background' ?> | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/utils/personal_data.css">
</head>
<body>
    <div class="personal-data-container">
        <div class="section-header" style="margin-bottom: 30px; border: none;">
            <i class="fas fa-graduation-cap fa-2x"></i>
            <div style="flex: 1;">
                <h1 style="margin: 0 0 8px;">
                    <?= $canEdit ? 'Edit Educational Background' : 'View Educational Background' ?>
                    <?php if (!$canEdit): ?>
                        <span class="mode-badge">View Only</span>
                    <?php endif; ?>
                </h1>
                <p style="margin: 0; color: var(--muted);">
                    <?php if ($canEdit): ?>
                        Update your educational background information
                    <?php else: ?>
                        Viewing educational background for: <?= htmlspecialchars(($targetUser['firstName'] ?? '') . ' ' . ($targetUser['lastName'] ?? '')) ?>
                        <?php if (in_array($currentUser['role'], ['admin', 'president']) && $view_uid !== $currentUser['uid']): ?>
                            <a href="profile.php?uid=<?= $view_uid ?>" style="margin-left: 10px; font-size: 14px; color: var(--primary); text-decoration: none;">
                                <i class="fas fa-arrow-left"></i> Back to Profile
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($canEdit): ?>
        <form method="POST" id="educationalBackgroundForm">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <?php else: ?>
        <div id="viewOnlyData">
        <?php endif; ?>
            
            <!-- SECTION 6: EDUCATIONAL BACKGROUND -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-graduation-cap"></i>
                    <h2>Educational Background</h2>
                </div>
                
                <div class="education-levels">
                    <!-- Elementary -->
                    <div class="education-level">
                        <h4>Elementary</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">School Name</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="elementary_school" 
                                       placeholder="School Name" value="<?= htmlspecialchars($personalData['elementary_school'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['elementary_school'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Degree/Course</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="elementary_degree" 
                                       placeholder="Degree/Course" value="<?= htmlspecialchars($personalData['elementary_degree'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['elementary_degree'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Year Graduated</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="elementary_year" 
                                       placeholder="Year" value="<?= htmlspecialchars($personalData['elementary_year'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['elementary_year'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Honors/Awards</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="elementary_honors" 
                                       placeholder="Honors/Awards" value="<?= htmlspecialchars($personalData['elementary_honors'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['elementary_honors'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Secondary -->
                    <div class="education-level">
                        <h4>Secondary</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">School Name</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="secondary_school" 
                                       placeholder="School Name" value="<?= htmlspecialchars($personalData['secondary_school'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['secondary_school'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Degree/Course</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="secondary_degree" 
                                       placeholder="Degree/Course" value="<?= htmlspecialchars($personalData['secondary_degree'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['secondary_degree'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Year Graduated</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="secondary_year" 
                                       placeholder="Year" value="<?= htmlspecialchars($personalData['secondary_year'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['secondary_year'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Honors/Awards</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="secondary_honors" 
                                       placeholder="Honors/Awards" value="<?= htmlspecialchars($personalData['secondary_honors'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['secondary_honors'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vocational -->
                    <div class="education-level">
                        <h4>Vocational/Trade Course</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">School Name</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="vocational_school" 
                                       placeholder="School Name" value="<?= htmlspecialchars($personalData['vocational_school'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['vocational_school'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Degree/Course</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="vocational_degree" 
                                       placeholder="Degree/Course" value="<?= htmlspecialchars($personalData['vocational_degree'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['vocational_degree'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Year Graduated</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="vocational_year" 
                                       placeholder="Year" value="<?= htmlspecialchars($personalData['vocational_year'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['vocational_year'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Honors/Awards</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="vocational_honors" 
                                       placeholder="Honors/Awards" value="<?= htmlspecialchars($personalData['vocational_honors'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['vocational_honors'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- College -->
                    <div class="education-level">
                        <h4>College</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">School Name</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="college_school" 
                                       placeholder="School Name" value="<?= htmlspecialchars($personalData['college_school'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['college_school'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Degree/Course</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="college_degree" 
                                       placeholder="Degree/Course" value="<?= htmlspecialchars($personalData['college_degree'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['college_degree'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Year Graduated</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="college_year" 
                                       placeholder="Year" value="<?= htmlspecialchars($personalData['college_year'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['college_year'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Honors/Awards</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="college_honors" 
                                       placeholder="Honors/Awards" value="<?= htmlspecialchars($personalData['college_honors'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['college_honors'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Graduate Studies -->
                    <div class="education-level">
                        <h4>Graduate Studies</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">School Name</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="graduate_school" 
                                       placeholder="School Name" value="<?= htmlspecialchars($personalData['graduate_school'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['graduate_school'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Degree/Course</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="graduate_degree" 
                                       placeholder="Degree/Course" value="<?= htmlspecialchars($personalData['graduate_degree'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['graduate_degree'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Year Graduated</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="graduate_year" 
                                       placeholder="Year" value="<?= htmlspecialchars($personalData['graduate_year'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['graduate_year'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Honors/Awards</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="graduate_honors" 
                                       placeholder="Honors/Awards" value="<?= htmlspecialchars($personalData['graduate_honors'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['graduate_honors'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($canEdit): ?>
            <!-- ACTION BAR (only show for editable forms) -->
            <div class="action-bar">
                <div>
                    <i class="fas fa-info-circle"></i>
                    <small class="text-muted">Please review all information before saving</small>
                </div>
                <div>
                    <button type="reset" class="btn btn-default">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
            </form>
            <?php else: ?>
            <!-- Back button for view-only mode -->
            <div class="action-bar">
                <div>
                    <i class="fas fa-info-circle"></i>
                    <small class="text-muted">View only mode - You cannot edit this user's data</small>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-default">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <?php if (in_array($currentUser['role'], ['admin', 'president'])): ?>
                    <a href="profile.php?uid=<?= $view_uid ?>" class="btn btn-primary">
                        <i class="fas fa-user"></i> View Full Profile
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            </div>
            <?php endif; ?>
    </div>
    
    <script>
        <?php if ($canEdit): ?>
        // Form validation
        document.getElementById('educationalBackgroundForm').addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'var(--danger)';
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *');
            }
        });
        
        // Year validation
        document.querySelectorAll('input[name$="_year"]').forEach(input => {
            input.addEventListener('blur', function() {
                const value = this.value.trim();
                if (value && !/^\d{4}$/.test(value)) {
                    this.style.borderColor = 'var(--danger)';
                    alert('Year should be in YYYY format (e.g., 2015)');
                } else {
                    this.style.borderColor = '';
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>