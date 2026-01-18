<?php
// civil-service-eligibility.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/upload.php';

$currentUser = current_user();
if (!$currentUser) {
    header("Location: login.php");
    exit;
}

// Check if user is allowed to view/update civil service eligibility
$allowedRoles = ['applicant', 'admin', 'president'];
if (!in_array($currentUser['role'], $allowedRoles)) {
    header("Location: dashboard.php");
    exit;
}

// Determine which user's data to show
if ($currentUser['role'] === 'applicant') {
    // Applicants can only view/edit their own data
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
$eligibility_data = null;
$is_edit = false;
$eligibility_id = null;
$eligibilities = [];
$error = '';
$success = '';

// Check for edit mode
if (isset($_GET['edit']) && is_numeric($_GET['edit']) && $canEdit) {
    $is_edit = true;
    $eligibility_id = $_GET['edit'];
    
    try {
        $stmt = db()->prepare("SELECT * FROM civil_service_eligibilities WHERE id = ? AND applicant_uid = ?");
        $stmt->execute([$eligibility_id, $view_uid]);
        $eligibility_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$eligibility_data) {
            $error = "Eligibility record not found";
            $is_edit = false;
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
        $is_edit = false;
    }
}

// Handle form submission (only if user can edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    try {
        // CSRF protection
        csrf_check();
        
        // Check if delete request
        if (isset($_POST['delete_id'])) {
            $delete_id = $_POST['delete_id'];
            
            // Verify ownership
            $checkStmt = db()->prepare("SELECT applicant_uid FROM civil_service_eligibilities WHERE id = ?");
            $checkStmt->execute([$delete_id]);
            $checkData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($checkData && $checkData['applicant_uid'] === $view_uid) {
                $deleteStmt = db()->prepare("DELETE FROM civil_service_eligibilities WHERE id = ?");
                $deleteStmt->execute([$delete_id]);
                $success = "Eligibility deleted successfully!";
            } else {
                $error = "You are not authorized to delete this eligibility";
            }
        } else {
            // Add/Update eligibility
            $data = $_POST;
            $eligibility_name = $data['eligibilityname'] ?? '';
            $rating = $data['rating'] ?? '';
            $date_of_examination = $data['dateofexamination'] ?? null;
            $place_of_examination = $data['placeofexamination'] ?? '';
            $license_number = $data['licensenumber'] ?? '';
            $license_validity = $data['licensevalidity'] ?? null;
            
            // Validate required fields
            if (empty($eligibility_name)) {
                throw new Exception("Eligibility name is required");
            }
            
            if ($is_edit && isset($data['eligibility_id'])) {
                // Update existing eligibility
                $updateStmt = db()->prepare("
                    UPDATE civil_service_eligibilities 
                    SET eligibilityname = ?, rating = ?, dateofexamination = ?, 
                        placeofexamination = ?, licensenumber = ?, licensevalidity = ?,
                        updated_at = NOW()
                    WHERE id = ? AND applicant_uid = ?
                ");
                $updateStmt->execute([
                    $eligibility_name,
                    $rating,
                    $date_of_examination,
                    $place_of_examination,
                    $license_number,
                    $license_validity,
                    $data['eligibility_id'],
                    $view_uid
                ]);
                $success = "Eligibility updated successfully!";
                $is_edit = false; // Reset edit mode after successful update
            } else {
                // Add new eligibility
                $insertStmt = db()->prepare("
                    INSERT INTO civil_service_eligibilities 
                    (applicant_uid, eligibilityname, rating, dateofexamination, 
                     placeofexamination, licensenumber, licensevalidity, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insertStmt->execute([
                    $view_uid,
                    $eligibility_name,
                    $rating,
                    $date_of_examination,
                    $place_of_examination,
                    $license_number,
                    $license_validity
                ]);
                $success = "Eligibility added successfully!";
            }
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all eligibilities for the user
try {
    $stmt = db()->prepare("SELECT * FROM civil_service_eligibilities WHERE applicant_uid = ? ORDER BY created_at DESC");
    $stmt->execute([$view_uid]);
    $eligibilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Civil Service Eligibility | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/utils/personal_data.css">
</head>
<body>
    <div class="personal-data-container">
        <div class="section-header" style="margin-bottom: 30px; border: none;">
            <i class="fas fa-certificate fa-2x"></i>
            <div style="flex: 1;">
                <h1 style="margin: 0 0 8px;">
                    Civil Service Eligibility
                    <?php if (!$canEdit): ?>
                        <span class="mode-badge">View Only</span>
                    <?php endif; ?>
                </h1>
                <p style="margin: 0; color: var(--muted);">
                    <?php if ($canEdit): ?>
                        <?= $is_edit && $eligibility_data ? 'Edit existing eligibility' : 'Add new civil service eligibility' ?>
                    <?php else: ?>
                        Viewing civil service eligibilities for: <?= htmlspecialchars(($targetUser['firstName'] ?? '') . ' ' . ($targetUser['lastName'] ?? '')) ?>
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
        <form method="POST" id="eligibilityForm" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            
            <!-- Hidden field for edit mode -->
            <?php if ($is_edit && $eligibility_data): ?>
                <input type="hidden" name="eligibility_id" value="<?= $eligibility_data['id'] ?>">
            <?php endif; ?>
        <?php else: ?>
        <div id="viewOnlyData">
        <?php endif; ?>
            
            <!-- SECTION 1: ELIGIBILITY INFORMATION -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-file-invoice"></i>
                    <h2>
                        <?= $is_edit && $eligibility_data ? 'Edit Eligibility' : 'Add New Eligibility' ?>
                        <?php if ($is_edit && $eligibility_data): ?>
                            <span class="mode-badge" style="font-size: 12px; margin-left: 10px;">Editing</span>
                        <?php endif; ?>
                    </h2>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">CES/CSEE/CAREER SERVICE/RA 1080 (BOARD/ BAR)/UNDER SPECIAL LAWS/CATEGORY II/ IV ELIGIBILITY and ELIGIBILITIES FOR UNIFORMED PERSONNEL <?php if ($canEdit): ?><span class="required">*</span><?php endif; ?></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-award"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="eligibilityname" 
                                   placeholder="Eligibility Name (e.g., Career Service Professional)"
                                   value="<?= $eligibility_data ? htmlspecialchars($eligibility_data['eligibilityname']) : '' ?>" required>
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= $eligibility_data ? htmlspecialchars($eligibility_data['eligibilityname']) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Rating</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-percent"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="rating" 
                                   placeholder="Rating (e.g., 85.00)"
                                   value="<?= $eligibility_data ? htmlspecialchars($eligibility_data['rating']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= $eligibility_data ? htmlspecialchars($eligibility_data['rating']) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date of Examination</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="date" class="form-control" name="dateofexamination" 
                                   placeholder="Date of Examination"
                                   value="<?= $eligibility_data ? htmlspecialchars($eligibility_data['dateofexamination']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= !empty($eligibility_data['dateofexamination']) ? date('F j, Y', strtotime($eligibility_data['dateofexamination'])) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Place of Examination</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-map-marker-alt"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="placeofexamination" 
                                   placeholder="Place of Examination"
                                   value="<?= $eligibility_data ? htmlspecialchars($eligibility_data['placeofexamination']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= $eligibility_data ? htmlspecialchars($eligibility_data['placeofexamination']) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">License Number (If Applicable)</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-id-card"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="licensenumber" 
                                   placeholder="License Number"
                                   value="<?= $eligibility_data ? htmlspecialchars($eligibility_data['licensenumber']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= $eligibility_data ? htmlspecialchars($eligibility_data['licensenumber']) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">License Validity (If Applicable)</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-shield-alt"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="date" class="form-control" name="licensevalidity" 
                                   placeholder="License Validity/Expiry Date"
                                   value="<?= $eligibility_data ? htmlspecialchars($eligibility_data['licensevalidity']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= !empty($eligibility_data['licensevalidity']) ? date('F j, Y', strtotime($eligibility_data['licensevalidity'])) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
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
                    <?php if ($is_edit && $eligibility_data): ?>
                    <a href="civil-service-eligibility.php" class="btn btn-default">
                        <i class="fas fa-times"></i> Cancel Edit
                    </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $is_edit && $eligibility_data ? 'Update' : 'Save' ?> Eligibility
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
            
            <!-- SECTION 2: EXISTING ELIGIBILITIES -->
            <?php if (!empty($eligibilities)): ?>
            <div class="section-card" style="margin-top: 30px;">
                <div class="section-header">
                    <i class="fas fa-list"></i>
                    <h2>Existing Civil Service Eligibilities</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Eligibility Name</th>
                                <th>Rating</th>
                                <th>Date of Examination</th>
                                <th>Place of Examination</th>
                                <th>License Number</th>
                                <th>License Validity</th>
                                <?php if ($canEdit): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eligibilities as $eligibility): ?>
                            <tr>
                                <td><?= htmlspecialchars($eligibility['eligibilityname'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($eligibility['rating'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($eligibility['dateofexamination'])): 
                                        echo date("F j, Y", strtotime($eligibility['dateofexamination']));
                                    else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($eligibility['placeofexamination'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($eligibility['licensenumber'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($eligibility['licensevalidity'])): 
                                        echo date("F j, Y", strtotime($eligibility['licensevalidity']));
                                    else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <?php if ($canEdit): ?>
                                <td>
                                    <div class="action-buttons">
                                        <a href="civil-service-eligibility.php?edit=<?= $eligibility['id'] ?>" class="btn-icon btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn-icon btn-danger delete-btn" 
                                                data-id="<?= $eligibility['id'] ?>" 
                                                data-name="<?= htmlspecialchars($eligibility['eligibilityname'] ?? 'Unnamed Eligibility') ?>"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
    </div>
    
    <!-- Hidden form for delete -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <input type="hidden" name="delete_id" id="deleteId">
    </form>
    
    <script>
        <?php if ($canEdit): ?>
        // Form validation
        document.getElementById('eligibilityForm').addEventListener('submit', function(e) {
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
            } else {
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            }
        });
        
        // Handle delete buttons
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const eligibilityId = this.getAttribute('data-id');
                const eligibilityName = this.getAttribute('data-name');
                
                if (confirm('Are you sure you want to delete "' + eligibilityName + '"? This action cannot be undone!')) {
                    // Set the delete ID and submit the form
                    document.getElementById('deleteId').value = eligibilityId;
                    document.getElementById('deleteForm').submit();
                }
            });
        });
        <?php endif; ?>
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>