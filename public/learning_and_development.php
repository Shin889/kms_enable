<?php
// learning-development.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/upload.php';

$currentUser = current_user();
if (!$currentUser) {
    header("Location: login.php");
    exit;
}

// Check if user is allowed to view/update learning & development
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
$learning_data = null;
$is_edit = false;
$learning_id = null;
$learnings = [];
$error = '';
$success = '';

// Check for edit mode
if (isset($_GET['edit']) && is_numeric($_GET['edit']) && $canEdit) {
    $is_edit = true;
    $learning_id = $_GET['edit'];
    
    try {
        $stmt = db()->prepare("SELECT * FROM learning_development WHERE id = ? AND applicant_uid = ?");
        $stmt->execute([$learning_id, $view_uid]);
        $learning_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$learning_data) {
            $error = "Learning & Development record not found";
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
            $checkStmt = db()->prepare("SELECT applicant_uid FROM learning_development WHERE id = ?");
            $checkStmt->execute([$delete_id]);
            $checkData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($checkData && $checkData['applicant_uid'] === $view_uid) {
                $deleteStmt = db()->prepare("DELETE FROM learning_development WHERE id = ?");
                $deleteStmt->execute([$delete_id]);
                $success = "Learning & Development record deleted successfully!";
            } else {
                $error = "You are not authorized to delete this record";
            }
        } else {
            // Add/Update learning & development
            $data = $_POST;
            $title_of_learning = $data['titleoflearning'] ?? '';
            $date_from = $data['datefrom'] ?? null;
            $date_to = $data['dateto'] ?? null;
            $number_of_hours = $data['numberofhours'] ?? '';
            $type_of_id = $data['typeofid'] ?? '';
            $conducted_by = $data['conducted'] ?? '';
            
            // Validate required fields
            if (empty($title_of_learning)) {
                throw new Exception("Title of learning/training program is required");
            }
            
            if ($is_edit && isset($data['learning_id'])) {
                // Update existing record
                $updateStmt = db()->prepare("
                    UPDATE learning_development 
                    SET titleoflearning = ?, datefrom = ?, dateto = ?, 
                        numberofhours = ?, typeofid = ?, conducted = ?,
                        updated_at = NOW()
                    WHERE id = ? AND applicant_uid = ?
                ");
                $updateStmt->execute([
                    $title_of_learning,
                    $date_from,
                    $date_to,
                    $number_of_hours,
                    $type_of_id,
                    $conducted_by,
                    $data['learning_id'],
                    $view_uid
                ]);
                $success = "Learning & Development record updated successfully!";
                $is_edit = false; // Reset edit mode after successful update
            } else {
                // Add new record
                $insertStmt = db()->prepare("
                    INSERT INTO learning_development 
                    (applicant_uid, titleoflearning, datefrom, dateto, 
                     numberofhours, typeofid, conducted, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insertStmt->execute([
                    $view_uid,
                    $title_of_learning,
                    $date_from,
                    $date_to,
                    $number_of_hours,
                    $type_of_id,
                    $conducted_by
                ]);
                $success = "Learning & Development record added successfully!";
            }
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all learning & development records for the user
try {
    $stmt = db()->prepare("SELECT * FROM learning_development WHERE applicant_uid = ? ORDER BY datefrom DESC");
    $stmt->execute([$view_uid]);
    $learnings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Learning & Development | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/utils/personal_data.css">
</head>
<body>
    <div class="personal-data-container">
        <div class="section-header" style="margin-bottom: 30px; border: none;">
            <i class="fas fa-chalkboard-teacher fa-2x"></i>
            <div style="flex: 1;">
                <h1 style="margin: 0 0 8px;">
                    Learning and Development
                    <?php if (!$canEdit): ?>
                        <span class="mode-badge">View Only</span>
                    <?php endif; ?>
                </h1>
                <p style="margin: 0; color: var(--muted);">
                    <?php if ($canEdit): ?>
                        <?= $is_edit && $learning_data ? 'Edit existing training/seminar' : 'Add new learning & development record' ?>
                    <?php else: ?>
                        Viewing learning & development for: <?= htmlspecialchars(($targetUser['firstName'] ?? '') . ' ' . ($targetUser['lastName'] ?? '')) ?>
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
        <form method="POST" id="learningForm" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            
            <!-- Hidden field for edit mode -->
            <?php if ($is_edit && $learning_data): ?>
                <input type="hidden" name="learning_id" value="<?= $learning_data['id'] ?>">
            <?php endif; ?>
        <?php else: ?>
        <div id="viewOnlyData">
        <?php endif; ?>
            
            <!-- SECTION 1: LEARNING & DEVELOPMENT INFORMATION -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-book-open"></i>
                    <h2>
                        <?= $is_edit && $learning_data ? 'Edit Training/Seminar' : 'Add Training/Seminar' ?>
                        <?php if ($is_edit && $learning_data): ?>
                            <span class="mode-badge" style="font-size: 12px; margin-left: 10px;">Editing</span>
                        <?php endif; ?>
                    </h2>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Title of Learning/Training Program <?php if ($canEdit): ?><span class="required">*</span><?php endif; ?></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-scroll"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="titleoflearning" 
                                   placeholder="Title of Learning and Development Intervention"
                                   value="<?= $learning_data ? htmlspecialchars($learning_data['titleoflearning']) : '' ?>" required>
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= $learning_data ? htmlspecialchars($learning_data['titleoflearning']) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date From</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="date" class="form-control" name="datefrom" 
                                   value="<?= $learning_data ? htmlspecialchars($learning_data['datefrom']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= !empty($learning_data['datefrom']) ? date('F j, Y', strtotime($learning_data['datefrom'])) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date To</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="date" class="form-control" name="dateto" 
                                   value="<?= $learning_data ? htmlspecialchars($learning_data['dateto']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= !empty($learning_data['dateto']) ? date('F j, Y', strtotime($learning_data['dateto'])) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Number of Hours</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-clock"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="numberofhours" 
                                   placeholder="Number of Hours"
                                   value="<?= $learning_data ? htmlspecialchars($learning_data['numberofhours']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= $learning_data ? htmlspecialchars($learning_data['numberofhours']) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Type of ID (Type of Learning/Development)</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-tags"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="typeofid" 
                                   placeholder="Type of Learning/Development"
                                   value="<?= $learning_data ? htmlspecialchars($learning_data['typeofid']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= $learning_data ? htmlspecialchars($learning_data['typeofid']) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Conducted/Sponsored By</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-building-user"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="conducted" 
                                   placeholder="Conducted/Sponsored By"
                                   value="<?= $learning_data ? htmlspecialchars($learning_data['conducted']) : '' ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= $learning_data ? htmlspecialchars($learning_data['conducted']) : 'N/A' ?>
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
                    <?php if ($is_edit && $learning_data): ?>
                    <a href="learning-development.php" class="btn btn-default">
                        <i class="fas fa-times"></i> Cancel Edit
                    </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $is_edit && $learning_data ? 'Update' : 'Save' ?> Record
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
            
            <!-- SECTION 2: EXISTING LEARNING & DEVELOPMENT RECORDS -->
            <?php if (!empty($learnings)): ?>
            <div class="section-card" style="margin-top: 30px;">
                <div class="section-header">
                    <i class="fas fa-list"></i>
                    <h2>Existing Learning & Development Records</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title of Learning/Training</th>
                                <th>Date From</th>
                                <th>Date To</th>
                                <th>Hours</th>
                                <th>Type of Learning</th>
                                <th>Conducted By</th>
                                <?php if ($canEdit): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($learnings as $learning): ?>
                            <tr>
                                <td><?= htmlspecialchars($learning['titleoflearning'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($learning['datefrom'])): 
                                        echo date("M j, Y", strtotime($learning['datefrom']));
                                    else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($learning['dateto'])): 
                                        echo date("M j, Y", strtotime($learning['dateto']));
                                    else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($learning['numberofhours'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($learning['typeofid'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($learning['conducted'] ?? 'N/A') ?></td>
                                <?php if ($canEdit): ?>
                                <td>
                                    <div class="action-buttons">
                                        <a href="learning-development.php?edit=<?= $learning['id'] ?>" class="btn-icon btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn-icon btn-danger delete-btn" 
                                                data-id="<?= $learning['id'] ?>" 
                                                data-name="<?= htmlspecialchars($learning['titleoflearning'] ?? 'Unnamed Training') ?>"
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
        document.getElementById('learningForm').addEventListener('submit', function(e) {
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
                const learningId = this.getAttribute('data-id');
                const learningName = this.getAttribute('data-name');
                
                if (confirm('Are you sure you want to delete "' + learningName + '"? This action cannot be undone!')) {
                    // Set the delete ID and submit the form
                    document.getElementById('deleteId').value = learningId;
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