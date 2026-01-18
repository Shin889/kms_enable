<?php
// voluntary-work.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/upload.php';

$currentUser = current_user();
if (!$currentUser) {
    header("Location: login.php");
    exit;
}

// Check if user is allowed to view voluntary work data
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
$voluntaryWorkData = [];
$error = '';
$success = '';

// Check if voluntary work data exists
try {
    $stmt = db()->prepare("SELECT * FROM voluntary_work WHERE user_uid = ? ORDER BY date_from DESC");
    $stmt->execute([$view_uid]);
    $voluntaryWorkData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist, we'll create it when needed
}

// Handle form submission (only if user can edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    try {
        // CSRF protection
        csrf_check();
        
        // Get form data
        $data = $_POST;
        
        // Validate required fields
        $requiredFields = ['organization', 'date_from', 'position'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }
        
        // Validate dates
        if (!empty($data['date_from']) && !empty($data['date_to'])) {
            $dateFrom = strtotime($data['date_from']);
            $dateTo = strtotime($data['date_to']);
            if ($dateTo < $dateFrom) {
                throw new Exception("Date To cannot be earlier than Date From.");
            }
        }
        
        // Validate number of hours
        if (!empty($data['number_of_hours']) && (!is_numeric($data['number_of_hours']) || $data['number_of_hours'] < 0)) {
            throw new Exception("Number of hours must be a positive number.");
        }
        
        // Check if this is an update or new entry
        $voluntaryWorkId = $data['voluntary_work_id'] ?? null;
        
        if ($voluntaryWorkId) {
            // Update existing record
            $stmt = db()->prepare("UPDATE voluntary_work SET 
                organization = ?, 
                date_from = ?, 
                date_to = ?, 
                number_of_hours = ?, 
                position = ?, 
                updated_at = NOW() 
                WHERE id = ? AND user_uid = ?");
            
            $stmt->execute([
                $data['organization'],
                $data['date_from'],
                !empty($data['date_to']) ? $data['date_to'] : null,
                !empty($data['number_of_hours']) ? (int)$data['number_of_hours'] : null,
                $data['position'],
                $voluntaryWorkId,
                $view_uid
            ]);
            
            $success = "Voluntary work updated successfully!";
        } else {
            // Insert new record
            $stmt = db()->prepare("INSERT INTO voluntary_work 
                (user_uid, organization, date_from, date_to, number_of_hours, position, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                $view_uid,
                $data['organization'],
                $data['date_from'],
                !empty($data['date_to']) ? $data['date_to'] : null,
                !empty($data['number_of_hours']) ? (int)$data['number_of_hours'] : null,
                $data['position']
            ]);
            
            $success = "Voluntary work added successfully!";
        }
        
        // Refresh the voluntary work data
        $stmt = db()->prepare("SELECT * FROM voluntary_work WHERE user_uid = ? ORDER BY date_from DESC");
        $stmt->execute([$view_uid]);
        $voluntaryWorkData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle delete request
if (isset($_GET['delete']) && $canEdit) {
    try {
        $deleteId = (int)$_GET['delete'];
        
        $stmt = db()->prepare("DELETE FROM voluntary_work WHERE id = ? AND user_uid = ?");
        $stmt->execute([$deleteId, $view_uid]);
        
        if ($stmt->rowCount() > 0) {
            $success = "Voluntary work deleted successfully!";
            
            // Refresh the voluntary work data
            $stmt = db()->prepare("SELECT * FROM voluntary_work WHERE user_uid = ? ORDER BY date_from DESC");
            $stmt->execute([$view_uid]);
            $voluntaryWorkData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {
        $error = "Error deleting record: " . $e->getMessage();
    }
}

// Handle edit request
$editData = null;
if (isset($_GET['edit']) && $canEdit) {
    try {
        $editId = (int)$_GET['edit'];
        
        $stmt = db()->prepare("SELECT * FROM voluntary_work WHERE id = ? AND user_uid = ?");
        $stmt->execute([$editId, $view_uid]);
        $editData = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = "Error loading record for editing: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $canEdit ? 'Edit Voluntary Work' : 'View Voluntary Work' ?> | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
   <link rel="stylesheet" href="assets/utils/personal_data.css">
</head>
<body>
    <div class="personal-data-container">
        <div class="section-header" style="margin-bottom: 30px; border: none;">
            <i class="fas fa-hand-holding-heart fa-2x"></i>
            <div style="flex: 1;">
                <h1 style="margin: 0 0 8px;">
                    <i class="fas fa-hands-helping me-2"></i>
                    <?= $canEdit ? ($editData ? 'Edit Voluntary Work' : 'Add Voluntary Work') : 'View Voluntary Work' ?>
                    <?php if (!$canEdit): ?>
                        <span class="mode-badge">View Only</span>
                    <?php endif; ?>
                </h1>
                <p style="margin: 0; color: var(--muted);">
                    <?php if ($canEdit): ?>
                        <?= $editData ? 'Update your voluntary work details' : 'Add your voluntary work experience' ?>
                    <?php else: ?>
                        Viewing voluntary work for: <?= htmlspecialchars(($targetUser['firstName'] ?? '') . ' ' . ($targetUser['lastName'] ?? '')) ?>
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
        
        <!-- VOLUNTARY WORK FORM -->
        <?php if ($canEdit): ?>
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-hands-helping"></i>
                <h2><?= $editData ? 'Edit Voluntary Work Details' : 'Add New Voluntary Work' ?></h2>
                <?php if ($editData): ?>
                    <a href="voluntary-work.php<?= $view_uid !== $currentUser['uid'] ? '?uid=' . $view_uid : '' ?>" 
                       class="btn btn-default btn-sm">
                        <i class="fas fa-plus"></i> Add New
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="POST" id="voluntaryWorkForm">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <?php if ($editData): ?>
                    <input type="hidden" name="voluntary_work_id" value="<?= $editData['id'] ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Organization <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-users-cog"></i></span>
                            <input type="text" class="form-control" name="organization" 
                                   placeholder="Organization Name" 
                                   value="<?= htmlspecialchars($editData['organization'] ?? '') ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Position / Nature of Work <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                            <input type="text" class="form-control" name="position" 
                                   placeholder="Position / Nature of Work" 
                                   value="<?= htmlspecialchars($editData['position'] ?? '') ?>" 
                                   required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date From <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-calendar-check"></i></span>
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?= htmlspecialchars($editData['date_from'] ?? '') ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date To</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-calendar-times"></i></span>
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?= htmlspecialchars($editData['date_to'] ?? '') ?>">
                        </div>
                        <small class="text-muted">Leave empty if ongoing</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Number of Hours</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-hourglass-half"></i></span>
                            <input type="number" class="form-control" name="number_of_hours" 
                                   placeholder="Number of Hours Rendered" 
                                   value="<?= htmlspecialchars($editData['number_of_hours'] ?? '') ?>" 
                                   min="0" step="1">
                        </div>
                    </div>
                </div>
                
                <div class="action-bar mt-4">
                    <div>
                        <i class="fas fa-info-circle"></i>
                        <small class="text-muted">Please fill in all required fields marked with *</small>
                    </div>
                    <div>
                        <button type="reset" class="btn btn-default">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= $editData ? 'Update' : 'Save' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- VOLUNTARY WORK LIST -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-list"></i>
                <h2>Voluntary Work History</h2>
                <?php if ($canEdit && !$editData): ?>
                    <div>
                        <button type="button" class="btn btn-primary btn-sm" onclick="toggleTips()">
                            <i class="fas fa-lightbulb"></i> Tips
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($voluntaryWorkData)): ?>
                <div class="text-center p-4">
                    <i class="fas fa-hands-helping fa-3x mb-3" style="color: var(--muted); opacity: 0.5;"></i>
                    <h3>No Voluntary Work Recorded</h3>
                    <p class="text-muted"><?= $canEdit ? 'Add your first voluntary work experience to showcase your community involvement.' : 'No voluntary work history available.' ?></p>
                    <?php if ($canEdit && !$editData): ?>
                        <p class="text-muted">Click the form above to add your voluntary work.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="voluntary-work-list">
                    <?php foreach ($voluntaryWorkData as $index => $work): 
                        $dateFrom = !empty($work['date_from']) ? date('F Y', strtotime($work['date_from'])) : 'N/A';
                        $dateTo = !empty($work['date_to']) ? date('F Y', strtotime($work['date_to'])) : 'Present';
                        $duration = calculateDuration($work['date_from'], $work['date_to']);
                    ?>
                    <div class="voluntary-work-item">
                        <div class="work-icon">
                            <i class="fas fa-hands-helping"></i>
                        </div>
                        <div class="work-content">
                            <div class="work-header">
                                <h4><?= htmlspecialchars($work['organization']) ?></h4>
                                <span class="work-date"><?= $dateFrom ?> - <?= $dateTo ?></span>
                            </div>
                            <div class="work-details">
                                <div class="work-position">
                                    <i class="fas fa-user-tag"></i>
                                    <?= htmlspecialchars($work['position']) ?>
                                </div>
                                <?php if (!empty($work['number_of_hours'])): ?>
                                <div class="work-hours">
                                    <i class="fas fa-hourglass-half"></i>
                                    <?= htmlspecialchars($work['number_of_hours']) ?> hours
                                </div>
                                <?php endif; ?>
                                <div class="work-duration">
                                    <i class="fas fa-clock"></i>
                                    <?= $duration ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($canEdit): ?>
                        <div class="work-actions">
                            <a href="voluntary-work.php?edit=<?= $work['id'] ?><?= $view_uid !== $currentUser['uid'] ? '&uid=' . $view_uid : '' ?>" 
                               class="btn-action btn-edit" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="voluntary-work.php?delete=<?= $work['id'] ?><?= $view_uid !== $currentUser['uid'] ? '&uid=' . $view_uid : '' ?>" 
                               class="btn-action btn-delete" 
                               title="Delete"
                               onclick="return confirm('Are you sure you want to delete this voluntary work record?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- TIPS CARD (hidden by default) -->
        <?php if ($canEdit && !$editData): ?>
        <div class="section-card tips-card" id="tipsCard" style="display: none;">
            <div class="section-header">
                <i class="fas fa-lightbulb"></i>
                <h2>Tips for Voluntary Work</h2>
                <button type="button" class="btn btn-default btn-sm" onclick="toggleTips()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            
            <div class="tips-content">
                <div class="tip-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Be Specific</strong>
                        <p>Instead of "Helped at event," say "Organized food distribution for 200 families"</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Quantify Your Impact</strong>
                        <p>Include numbers: "Raised $5,000 for charity" or "Tutored 15 students weekly"</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Show Leadership</strong>
                        <p>Highlight any leadership roles: "Team Leader" or "Event Coordinator"</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Include Relevant Skills</strong>
                        <p>Mention skills developed: "Public speaking," "Team management," "Event planning"</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Be Honest</strong>
                        <p>Only include volunteer work you actually participated in</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!$canEdit): ?>
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
        <?php endif; ?>
    </div>
    
    <style>
    .voluntary-work-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .voluntary-work-item {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        padding: 20px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 10px;
        transition: all 0.2s;
    }
    
    .voluntary-work-item:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-color: var(--primary-light);
    }
    
    .work-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: var(--primary);
        flex-shrink: 0;
    }
    
    .work-content {
        flex: 1;
        min-width: 0;
    }
    
    .work-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }
    
    .work-header h4 {
        margin: 0;
        font-size: 16px;
        color: var(--text);
    }
    
    .work-date {
        background: var(--primary-light);
        color: var(--primary);
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
    }
    
    .work-details {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .work-position, .work-hours, .work-duration {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 14px;
        color: var(--text-light);
    }
    
    .work-position i {
        color: var(--primary);
    }
    
    .work-hours i {
        color: var(--warning);
    }
    
    .work-duration i {
        color: var(--success);
    }
    
    .work-actions {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }
    
    .btn-action {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .btn-edit {
        background: var(--primary-light);
        color: var(--primary);
        border: 1px solid var(--primary-light);
    }
    
    .btn-edit:hover {
        background: var(--primary);
        color: white;
    }
    
    .btn-delete {
        background: #ffebee;
        color: #f44336;
        border: 1px solid #ffcdd2;
    }
    
    .btn-delete:hover {
        background: #f44336;
        color: white;
    }
    
    .tips-card {
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .tips-content {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .tip-item {
        display: flex;
        gap: 15px;
        padding: 15px;
        background: var(--bg-light);
        border-radius: 8px;
        border-left: 4px solid var(--primary);
    }
    
    .tip-item i {
        color: var(--primary);
        font-size: 18px;
        margin-top: 2px;
    }
    
    .tip-item strong {
        display: block;
        margin-bottom: 5px;
        color: var(--text);
    }
    
    .tip-item p {
        margin: 0;
        color: var(--text-light);
        font-size: 14px;
        line-height: 1.5;
    }
    </style>
    
    <script>
        // Form validation
        document.getElementById('voluntaryWorkForm')?.addEventListener('submit', function(e) {
            const organization = this.querySelector('[name="organization"]');
            const position = this.querySelector('[name="position"]');
            const dateFrom = this.querySelector('[name="date_from"]');
            const dateTo = this.querySelector('[name="date_to"]');
            
            let isValid = true;
            
            // Check required fields
            if (!organization.value.trim()) {
                organization.style.borderColor = 'var(--danger)';
                isValid = false;
            }
            
            if (!position.value.trim()) {
                position.style.borderColor = 'var(--danger)';
                isValid = false;
            }
            
            if (!dateFrom.value) {
                dateFrom.style.borderColor = 'var(--danger)';
                isValid = false;
            }
            
            // Check date logic
            if (dateFrom.value && dateTo.value) {
                const fromDate = new Date(dateFrom.value);
                const toDate = new Date(dateTo.value);
                
                if (toDate < fromDate) {
                    dateTo.style.borderColor = 'var(--danger)';
                    isValid = false;
                    alert('Date To cannot be earlier than Date From.');
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
            }
        });
        
        // Clear error when user starts typing
        document.querySelectorAll('#voluntaryWorkForm input').forEach(input => {
            input.addEventListener('input', function() {
                this.style.borderColor = '';
            });
        });
        
        // Toggle tips card
        function toggleTips() {
            const tipsCard = document.getElementById('tipsCard');
            if (tipsCard.style.display === 'none' || tipsCard.style.display === '') {
                tipsCard.style.display = 'block';
                tipsCard.scrollIntoView({ behavior: 'smooth' });
            } else {
                tipsCard.style.display = 'none';
            }
        }
        
        // Number of hours input validation
        const hoursInput = document.querySelector('[name="number_of_hours"]');
        if (hoursInput) {
            hoursInput.addEventListener('input', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        }
    </script>
</body>
</html>

<?php
// Helper function to calculate duration
function calculateDuration($dateFrom, $dateTo) {
    if (empty($dateFrom)) return 'N/A';
    
    $from = new DateTime($dateFrom);
    
    if (empty($dateTo)) {
        $to = new DateTime();
        $isCurrent = true;
    } else {
        $to = new DateTime($dateTo);
        $isCurrent = false;
    }
    
    $interval = $from->diff($to);
    $years = $interval->y;
    $months = $interval->m;
    
    $durationParts = [];
    if ($years > 0) {
        $durationParts[] = $years . ' ' . ($years === 1 ? 'year' : 'years');
    }
    if ($months > 0 || $years === 0) {
        $durationParts[] = $months . ' ' . ($months === 1 ? 'month' : 'months');
    }
    
    $duration = implode(', ', $durationParts);
    
    if ($isCurrent) {
        $duration .= ' (Current)';
    }
    
    return $duration ?: 'Less than 1 month';
}
?>