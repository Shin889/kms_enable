<?php
// work-experience.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/upload.php';

$currentUser = current_user();
if (!$currentUser) {
    header("Location: login.php");
    exit;
}

// Check if user is allowed to view/update work experience
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
$work_experiences = [];
$error = '';
$success = '';
$work_data = null;
$is_edit = false;
$work_id = null;

// Check for edit mode
if (isset($_GET['edit']) && is_numeric($_GET['edit']) && $canEdit) {
    $is_edit = true;
    $work_id = $_GET['edit'];
    
    try {
        $stmt = db()->prepare("SELECT * FROM work_experiences WHERE id = ? AND applicant_uid = ?");
        $stmt->execute([$work_id, $view_uid]);
        $work_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$work_data) {
            $error = "Work experience record not found";
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
            $checkStmt = db()->prepare("SELECT applicant_uid FROM work_experiences WHERE id = ?");
            $checkStmt->execute([$delete_id]);
            $checkData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($checkData && $checkData['applicant_uid'] === $view_uid) {
                $deleteStmt = db()->prepare("DELETE FROM work_experiences WHERE id = ?");
                $deleteStmt->execute([$delete_id]);
                $success = "Work experience deleted successfully!";
            } else {
                $error = "You are not authorized to delete this work experience";
            }
        } else {
            // Add/Update work experience (only past work experience)
            $data = $_POST;
            
            $positiontitle = $data['positiontitle'] ?? '';
            $companyofficeagency = $data['companyofficeagency'] ?? '';
            $appointmentstatus = $data['appointmentstatus'] ?? '';
            $governmentservice = isset($data['governmentservice']) ? 1 : 0;
            $fromdate = $data['fromdate'] ?? null;
            $todate = $data['todate'] ?? null;
            $is_present = 0; // Always 0 for past work experience
            
            // Validate required fields
            if (empty($positiontitle)) {
                throw new Exception("Position title is required");
            }
            
            if (empty($companyofficeagency)) {
                throw new Exception("Company/Office/Agency is required");
            }
            
            if ($is_edit && isset($data['work_id'])) {
                // Update existing work experience
                $updateStmt = db()->prepare("
                    UPDATE work_experiences 
                    SET positiontitle = ?, companyofficeagency = ?, 
                        appointmentstatus = ?, governmentservice = ?, 
                        fromdate = ?, todate = ?, is_present = ?,
                        updated_at = NOW()
                    WHERE id = ? AND applicant_uid = ?
                ");
                $updateStmt->execute([
                    $positiontitle,
                    $companyofficeagency,
                    $appointmentstatus,
                    $governmentservice,
                    $fromdate,
                    $todate,
                    $is_present,
                    $data['work_id'],
                    $view_uid
                ]);
                $success = "Work experience updated successfully!";
                $is_edit = false; // Reset edit mode after successful update
            } else {
                // Add new work experience
                $insertStmt = db()->prepare("
                    INSERT INTO work_experiences 
                    (applicant_uid, positiontitle, companyofficeagency, 
                     appointmentstatus, governmentservice, 
                     fromdate, todate, is_present, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insertStmt->execute([
                    $view_uid,
                    $positiontitle,
                    $companyofficeagency,
                    $appointmentstatus,
                    $governmentservice,
                    $fromdate,
                    $todate,
                    $is_present
                ]);
                $success = "Work experience added successfully!";
            }
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all work experiences for the user (only past)
try {
    $stmt = db()->prepare("SELECT * FROM work_experiences WHERE applicant_uid = ? AND is_present = 0 ORDER BY fromdate DESC");
    $stmt->execute([$view_uid]);
    $work_experiences = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Work Experience | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/utils/personal_data.css">
</head>
<body>
    <div class="personal-data-container">
        <div class="section-header" style="margin-bottom: 30px; border: none;">
            <i class="fas fa-briefcase fa-2x"></i>
            <div style="flex: 1;">
                <h1 style="margin: 0 0 8px;">
                    Work Experience
                    <?php if (!$canEdit): ?>
                        <span class="mode-badge">View Only</span>
                    <?php endif; ?>
                </h1>
                <p style="margin: 0; color: var(--muted);">
                    <?php if ($canEdit): ?>
                        <?= $is_edit && $work_data ? 'Edit existing work experience' : 'Add new work experience' ?>
                    <?php else: ?>
                        Viewing work experience for: <?= htmlspecialchars(($targetUser['firstName'] ?? '') . ' ' . ($targetUser['lastName'] ?? '')) ?>
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
        <form method="POST" id="workExperienceForm" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            
            <!-- Hidden field for edit mode -->
            <?php if ($is_edit && $work_data): ?>
                <input type="hidden" name="work_id" value="<?= $work_data['id'] ?>">
            <?php endif; ?>
        <?php else: ?>
        <div id="viewOnlyData">
        <?php endif; ?>
            
            <!-- SECTION 1: WORK EXPERIENCE INFORMATION -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-history"></i>
                    <h2>
                        <?= $is_edit && $work_data ? 'Edit Work Experience' : 'Add Work Experience' ?>
                        <?php if ($is_edit && $work_data): ?>
                            <span class="mode-badge" style="font-size: 12px; margin-left: 10px;">Editing</span>
                        <?php endif; ?>
                    </h2>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Position Title <?php if ($canEdit): ?><span class="required">*</span><?php endif; ?></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-user-tie"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="positiontitle" 
                                   placeholder="Position Title"
                                   value="<?= $work_data ? htmlspecialchars($work_data['positiontitle']) : '' ?>" required>
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= $work_data ? htmlspecialchars($work_data['positiontitle']) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Company/Office/Agency <?php if ($canEdit): ?><span class="required">*</span><?php endif; ?></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-building"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="companyofficeagency" 
                                   placeholder="Company/Office/Agency Name"
                                   value="<?= $work_data ? htmlspecialchars($work_data['companyofficeagency']) : '' ?>" required>
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= $work_data ? htmlspecialchars($work_data['companyofficeagency']) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Appointment Status</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-id-badge"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="appointmentstatus" 
                                   placeholder="Appointment Status (e.g., Permanent, Contractual)"
                                   value="<?= $work_data ? htmlspecialchars($work_data['appointmentstatus']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= $work_data ? htmlspecialchars($work_data['appointmentstatus']) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($canEdit): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Government Service</label>
                        <div class="same-address-toggle">
                            <label class="toggle-switch">
                                <input type="checkbox" name="governmentservice" value="1" 
                                       <?= ($work_data && $work_data['governmentservice'] == 1) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Check if this is government service</span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Government Service</label>
                        <div class="view-only-display">
                            <?= ($work_data && $work_data['governmentservice'] == 1) ? 'Yes' : 'No' ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-calendar-check"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="date" class="form-control" name="fromdate" 
                                   value="<?= $work_data ? htmlspecialchars($work_data['fromdate']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= !empty($work_data['fromdate']) ? date('F j, Y', strtotime($work_data['fromdate'])) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-calendar-times"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="date" class="form-control" name="todate" 
                                   value="<?= $work_data ? htmlspecialchars($work_data['todate']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= !empty($work_data['todate']) ? date('F j, Y', strtotime($work_data['todate'])) : 'N/A' ?>
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
                    <?php if ($is_edit && $work_data): ?>
                    <a href="work-experience.php" class="btn btn-default">
                        <i class="fas fa-times"></i> Cancel Edit
                    </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $is_edit && $work_data ? 'Update' : 'Save' ?> Work Experience
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
            
            <!-- SECTION 2: EXISTING WORK EXPERIENCES -->
            <?php if (!empty($work_experiences)): ?>
            <div class="section-card" style="margin-top: 30px;">
                <div class="section-header">
                    <i class="fas fa-list"></i>
                    <h2>Existing Work Experiences</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Position Title</th>
                                <th>Company/Office</th>
                                <th>Appointment Status</th>
                                <th>From Date</th>
                                <th>To Date</th>
                                <th>Gov't Service</th>
                                <?php if ($canEdit): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($work_experiences as $work): ?>
                            <tr>
                                <td><?= htmlspecialchars($work['positiontitle'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($work['companyofficeagency'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($work['appointmentstatus'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($work['fromdate'])): 
                                        echo date("F j, Y", strtotime($work['fromdate']));
                                    else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($work['todate'])): 
                                        echo date("F j, Y", strtotime($work['todate']));
                                    else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $work['governmentservice'] == 1 ? 'Yes' : 'No' ?>
                                </td>
                                <?php if ($canEdit): ?>
                                <td>
                                    <div class="action-buttons">
                                        <a href="work-experience.php?edit=<?= $work['id'] ?>" class="btn-icon btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn-icon btn-danger delete-btn" 
                                                data-id="<?= $work['id'] ?>" 
                                                data-name="<?= htmlspecialchars($work['positiontitle'] ?? 'Unnamed Position') ?>"
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
        document.getElementById('workExperienceForm').addEventListener('submit', function(e) {
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
                const workId = this.getAttribute('data-id');
                const workName = this.getAttribute('data-name');
                
                if (confirm('Are you sure you want to delete "' + workName + '"? This action cannot be undone!')) {
                    // Set the delete ID and submit the form
                    document.getElementById('deleteId').value = workId;
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