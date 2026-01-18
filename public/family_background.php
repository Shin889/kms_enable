<?php
// family-background.php
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
        
        // Prepare the update query for family background fields
        $updateFields = [
            'spousesurname' => $data['spousesurname'] ?? '',
            'spousefirstname' => $data['spousefirstname'] ?? '',
            'spousemiddlename' => $data['spousemiddlename'] ?? '',
            'spouseoccupation' => $data['spouseoccupation'] ?? '',
            'spouseemployer' => $data['spouseemployer'] ?? '',
            'spousebusinessaddress' => $data['spousebusinessaddress'] ?? '',
            'spousetelephone' => $data['spousetelephone'] ?? '',
            'fathersurname' => $data['fathersurname'] ?? '',
            'fatherfirstname' => $data['fatherfirstname'] ?? '',
            'fathermiddlename' => $data['fathermiddlename'] ?? '',
            'fatheroccupation' => $data['fatheroccupation'] ?? '',
            'mothersurname' => $data['mothersurname'] ?? '',
            'motherfirstname' => $data['motherfirstname'] ?? '',
            'mothermiddlename' => $data['mothermiddlename'] ?? '',
            'motheroccupation' => $data['motheroccupation'] ?? '',
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
        
        $success = "Family background information saved successfully!";
        
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
    <title><?= $canEdit ? 'Edit Family Background' : 'View Family Background' ?> | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/utils/personal_data.css">
</head>
<body>
    <div class="personal-data-container">
        <div class="section-header" style="margin-bottom: 30px; border: none;">
            <i class="fas fa-house-user fa-2x"></i>
            <div style="flex: 1;">
                <h1 style="margin: 0 0 8px;">
                    <?= $canEdit ? 'Edit Family Background' : 'View Family Background' ?>
                    <?php if (!$canEdit): ?>
                        <span class="mode-badge">View Only</span>
                    <?php endif; ?>
                </h1>
                <p style="margin: 0; color: var(--muted);">
                    <?php if ($canEdit): ?>
                        Update your family background information
                    <?php else: ?>
                        Viewing family background for: <?= htmlspecialchars(($targetUser['firstName'] ?? '') . ' ' . ($targetUser['lastName'] ?? '')) ?>
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
        <form method="POST" id="familyBackgroundForm">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <?php else: ?>
        <div id="viewOnlyData">
        <?php endif; ?>
            
            <!-- FAMILY BACKGROUND SECTION -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-house-user"></i>
                    <h2>Family Background</h2>
                </div>
                
                <!-- Spouse Information -->
                <div class="mb-4">
                    <h3 style="margin-bottom: 16px; color: var(--text); font-size: 18px;"><i class="fas fa-heart me-2"></i>Spouse Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Spouse Surname</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="spousesurname" 
                                       placeholder="Spouse Surname" value="<?= htmlspecialchars($personalData['spousesurname'] ?? '') ?>" 
                                       style="text-transform: capitalize;" pattern="[a-zA-Z ]+">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['spousesurname'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Spouse First Name</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="spousefirstname" 
                                       placeholder="Spouse First Name" value="<?= htmlspecialchars($personalData['spousefirstname'] ?? '') ?>" 
                                       style="text-transform: capitalize;" pattern="[a-zA-Z ]+">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['spousefirstname'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Spouse Middle Name</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="spousemiddlename" 
                                       placeholder="Spouse Middle Name" value="<?= htmlspecialchars($personalData['spousemiddlename'] ?? '') ?>" 
                                       style="text-transform: capitalize;" pattern="[a-zA-Z ]*">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['spousemiddlename'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Occupation</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-briefcase"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="spouseoccupation" 
                                       placeholder="Occupation" value="<?= htmlspecialchars($personalData['spouseoccupation'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['spouseoccupation'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Employer</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-building"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="spouseemployer" 
                                       placeholder="Employer" value="<?= htmlspecialchars($personalData['spouseemployer'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['spouseemployer'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Business Address</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-map-marked-alt"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="spousebusinessaddress" 
                                       placeholder="Business Address" value="<?= htmlspecialchars($personalData['spousebusinessaddress'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['spousebusinessaddress'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Telephone</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-phone-square-alt"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="tel" class="form-control" name="spousetelephone" 
                                       placeholder="Telephone Number" value="<?= htmlspecialchars($personalData['spousetelephone'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['spousetelephone'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Father's Information -->
                <div class="mb-4">
                    <h3 style="margin-bottom: 16px; color: var(--text); font-size: 18px;"><i class="fas fa-male me-2"></i>Father's Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Father Surname</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="fathersurname" 
                                       placeholder="Father Surname" value="<?= htmlspecialchars($personalData['fathersurname'] ?? '') ?>" 
                                       style="text-transform: capitalize;" pattern="[a-zA-Z ]+">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['fathersurname'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Father First Name</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="fatherfirstname" 
                                       placeholder="Father First Name" value="<?= htmlspecialchars($personalData['fatherfirstname'] ?? '') ?>" 
                                       style="text-transform: capitalize;" pattern="[a-zA-Z ]+">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['fatherfirstname'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Father Middle Name</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="fathermiddlename" 
                                       placeholder="Father Middle Name" value="<?= htmlspecialchars($personalData['fathermiddlename'] ?? '') ?>" 
                                       style="text-transform: capitalize;" pattern="[a-zA-Z ]*">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['fathermiddlename'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Occupation</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-hard-hat"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="fatheroccupation" 
                                       placeholder="Father's Occupation" value="<?= htmlspecialchars($personalData['fatheroccupation'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['fatheroccupation'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mother's Information -->
                <div class="mb-4">
                    <h3 style="margin-bottom: 16px; color: var(--text); font-size: 18px;"><i class="fas fa-female me-2"></i>Mother's Information (Maiden Name)</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Mother Surname</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="mothersurname" 
                                       placeholder="Mother Surname" value="<?= htmlspecialchars($personalData['mothersurname'] ?? '') ?>" 
                                       style="text-transform: capitalize;" pattern="[a-zA-Z ]+">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['mothersurname'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Mother First Name</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="motherfirstname" 
                                       placeholder="Mother First Name" value="<?= htmlspecialchars($personalData['motherfirstname'] ?? '') ?>" 
                                       style="text-transform: capitalize;" pattern="[a-zA-Z ]+">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['motherfirstname'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Mother Middle Name</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="mothermiddlename" 
                                       placeholder="Mother Middle Name" value="<?= htmlspecialchars($personalData['mothermiddlename'] ?? '') ?>" 
                                       style="text-transform: capitalize;" pattern="[a-zA-Z ]*">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['mothermiddlename'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Occupation</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-female"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="motheroccupation" 
                                       placeholder="Mother's Occupation" value="<?= htmlspecialchars($personalData['motheroccupation'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['motheroccupation'] ?? 'N/A') ?>
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
        document.getElementById('familyBackgroundForm').addEventListener('submit', function(e) {
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
        <?php endif; ?>
    </script>
</body>
</html>