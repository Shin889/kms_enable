<?php
// personal-data.php
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
        $stmt = db()->prepare("INSERT INTO applicant_profiles (applicant_uid, firstname, lastname, emailaddress) VALUES (?, ?, ?, ?)");
        $stmt->execute([$view_uid, $targetUser['firstName'] ?? '', $targetUser['lastName'] ?? '', $targetUser['email'] ?? '']);
        
        $stmt = db()->prepare("SELECT * FROM applicant_profiles WHERE applicant_uid = ?");
        $stmt->execute([$view_uid]);
        $personalData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Define arrays for dropdown options
$nameExtensions = [
    '' => 'Select Name Extension',
    'Jr' => 'Jr',
    'Sr' => 'Sr',
    'II' => 'II',
    'III' => 'III',
    'IV' => 'IV',
    'V' => 'V'
];

$sexOptions = [
    '' => 'Select Sex',
    'Male' => 'Male',
    'Female' => 'Female'
];

$civilStatusOptions = [
    '' => 'Select Civil Status',
    'Single' => 'Single',
    'Married' => 'Married',
    'Widowed' => 'Widowed',
    'Separated' => 'Separated',
    'Annulled' => 'Annulled'
];

$bloodTypeOptions = [
    '' => 'Select Blood Type',
    'A+' => 'A+',
    'A-' => 'A-',
    'B+' => 'B+',
    'B-' => 'B-',
    'AB+' => 'AB+',
    'AB-' => 'AB-',
    'O+' => 'O+',
    'O-' => 'O-'
];

$citizenshipOptions = [
    '' => 'Select Citizenship',
    'Filipino' => 'Filipino',
    'Dual Citizen' => 'Dual Citizen',
    'Foreigner' => 'Foreigner'
];

$citizenshipDetails = [
    '' => 'Select Details',
    'by_birth' => 'By Birth',
    'by_naturalization' => 'By Naturalization'
];

// Handle form submission (only if user can edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    try {
        // CSRF protection
        csrf_check();
        
        // Get form data
        $data = $_POST;
        
        // Handle file upload for profile picture
        $profilePicturePath = $personalData['profile_picture'] ?? '';
        if (isset($_FILES['profilepicture']) && $_FILES['profilepicture']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = accept_upload($_FILES['profilepicture'], 'profile_pictures');
            
            if ($uploadResult !== null) {
                $profilePicturePath = $uploadResult;
                
                // Delete old profile picture if exists
                if (!empty($personalData['profile_picture'])) {
                    $oldPath = __DIR__ . '/../uploads/' . $personalData['profile_picture'];
                    if (file_exists($oldPath) && is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }
            } else {
                $error = "Failed to upload profile picture. Invalid file type or upload error.";
            }
        }
        
        // Prepare the update query
        $updateFields = [
            'firstname' => $data['firstname'] ?? $targetUser['firstName'] ?? '',
            'middlename' => $data['middlename'] ?? $targetUser['middleName'] ?? '',
            'lastname' => $data['lastname'] ?? $targetUser['lastName'] ?? '',
            'nameextension' => $data['nameextension'] ?? '',
            'emailaddress' => $data['emailaddress'] ?? $targetUser['email'] ?? '',
            'sex' => $data['sex'] ?? '',
            'civilstatus' => $data['civilstatus'] ?? '',
            'dateofbirth' => $data['dateofbirth'] ?? '',
            'height' => !empty($data['height']) ? (float)$data['height'] : null,
            'weight' => !empty($data['weight']) ? (float)$data['weight'] : null,
            'bloodtype' => $data['bloodtype'] ?? '',
            'placeofbirth' => $data['placeofbirth'] ?? '',
            'citizenship' => $data['citizenship'] ?? '',
            'citizenshipdetails' => $data['citizenshipdetails'] ?? '',
            'permprovince' => $data['permprovince'] ?? '',
            'permcitymunicipality' => $data['permcitymunicipality'] ?? '',
            'permbarangay' => $data['permbarangay'] ?? '',
            'permzipcode' => $data['permzipcode'] ?? '',
            'permhouseblockno' => $data['permhouseblockno'] ?? '',
            'permstreetno' => $data['permstreetno'] ?? '',
            'permsubdivisionvillage' => $data['permsubdivisionvillage'] ?? '',
            'tempprovince' => $data['tempprovince'] ?? '',
            'tempcitymunicipality' => $data['tempcitymunicipality'] ?? '',
            'tempbarangay' => $data['tempbarangay'] ?? '',
            'tempzipcode' => $data['tempzipcode'] ?? '',
            'temphouseblockno' => $data['temphouseblockno'] ?? '',
            'tempstreetno' => $data['tempstreetno'] ?? '',
            'tempsubdivisionvillage' => $data['tempsubdivisionvillage'] ?? '',
            'mobilenumber' => $data['mobilenumber'] ?? '',
            'telephonenumber' => $data['telephonenumber'] ?? '',
            'tinno' => $data['tinno'] ?? '',
            'umidno' => $data['umidno'] ?? '',
            'pagibigno' => $data['pagibigno'] ?? '',
            'philsysno' => $data['philsysno'] ?? '',
            'agencyemployeeno' => $data['agencyemployeeno'] ?? '',
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
            'special_skills' => $data['special_skills'] ?? '',
            'non_academic' => $data['non_academic'] ?? '',
            'memberships' => $data['memberships'] ?? '',
        ];
        
        // Only update profile picture if a new one was uploaded
        if (!empty($profilePicturePath)) {
            $updateFields['profile_picture'] = $profilePicturePath;
        }
        
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
        
        $success = "Personal data saved successfully!";
        
        // Refresh the personal data
        $stmt = db()->prepare("SELECT * FROM applicant_profiles WHERE applicant_uid = ?");
        $stmt->execute([$view_uid]);
        $personalData = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = "Error saving data: " . $e->getMessage();
    }
}

// Get children data from other_details JSON if it exists
$children = [];
if (!empty($personalData['other_details'])) {
    $otherDetails = json_decode($personalData['other_details'], true);
    if (isset($otherDetails['children']) && is_array($otherDetails['children'])) {
        $children = $otherDetails['children'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $canEdit ? 'Edit Personal Data' : 'View Personal Data' ?> | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/utils/personal_data.css">
</head>
<body>
    <div class="personal-data-container">
        <div class="section-header" style="margin-bottom: 30px; border: none;">
            <i class="fas fa-user-tie fa-2x"></i>
            <div style="flex: 1;">
                <h1 style="margin: 0 0 8px;">
                    <?= $canEdit ? 'Edit Personal Information' : 'View Personal Information' ?>
                    <?php if (!$canEdit): ?>
                        <span class="mode-badge">View Only</span>
                    <?php endif; ?>
                </h1>
                <p style="margin: 0; color: var(--muted);">
                    <?php if ($canEdit): ?>
                        Update your personal details and contact information
                    <?php else: ?>
                        Viewing personal data for: <?= htmlspecialchars(($targetUser['firstName'] ?? '') . ' ' . ($targetUser['lastName'] ?? '')) ?>
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
        <form method="POST" id="personalDataForm" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <?php else: ?>
        <div id="viewOnlyData">
        <?php endif; ?>
            
            <!-- SECTION 1: PERSONAL INFORMATION -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-user-tie"></i>
                    <h2>Personal Information</h2>
                </div>
                
                <!-- Personal Details -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name <?php if ($canEdit): ?><span class="required">*</span><?php endif; ?></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="firstname" 
                                   placeholder="First Name" value="<?= htmlspecialchars($personalData['firstname'] ?? $targetUser['firstName'] ?? '') ?>" 
                                   style="text-transform: capitalize;" pattern="[a-zA-Z ]+" 
                                   oninvalid="setCustomValidity('Must contain characters only.')" 
                                   oninput="setCustomValidity('')" required>
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['firstname'] ?? $targetUser['firstName'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Middle Name</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="middlename" 
                                   placeholder="Middle Name" value="<?= htmlspecialchars($personalData['middlename'] ?? $targetUser['middleName'] ?? '') ?>" 
                                   style="text-transform: capitalize;" pattern="[a-zA-Z ]+">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['middlename'] ?? $targetUser['middleName'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Last Name <?php if ($canEdit): ?><span class="required">*</span><?php endif; ?></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="lastname" 
                                   placeholder="Last Name" value="<?= htmlspecialchars($personalData['lastname'] ?? $targetUser['lastName'] ?? '') ?>" 
                                   style="text-transform: capitalize;" pattern="[a-zA-Z ]+" required>
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['lastname'] ?? $targetUser['lastName'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Name Extension</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-angle-double-right"></i></span>
                            <?php if ($canEdit): ?>
                            <select class="form-control" name="nameextension">
                                <?php foreach ($nameExtensions as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= ($personalData['nameextension'] ?? '') === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <div class="view-only-select">
                                <span class="input-icon"><i class="fas fa-angle-double-right"></i></span>
                                <?= htmlspecialchars($nameExtensions[$personalData['nameextension'] ?? ''] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email Address <?php if ($canEdit): ?><span class="required">*</span><?php endif; ?></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="email" class="form-control" name="emailaddress" 
                                   placeholder="Email Address" value="<?= htmlspecialchars($personalData['emailaddress'] ?? $targetUser['email'] ?? '') ?>" required>
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['emailaddress'] ?? $targetUser['email'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Sex <?php if ($canEdit): ?><span class="required">*</span><?php endif; ?></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-venus-mars"></i></span>
                            <?php if ($canEdit): ?>
                            <select class="form-control" name="sex" required>
                                <?php foreach ($sexOptions as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= ($personalData['sex'] ?? '') === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <div class="view-only-select">
                                <span class="input-icon"><i class="fas fa-venus-mars"></i></span>
                                <?= htmlspecialchars($sexOptions[$personalData['sex'] ?? ''] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Civil Status</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-ring"></i></span>
                            <?php if ($canEdit): ?>
                            <select class="form-control" name="civilstatus">
                                <?php foreach ($civilStatusOptions as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= ($personalData['civilstatus'] ?? '') === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <div class="view-only-select">
                                <span class="input-icon"><i class="fas fa-ring"></i></span>
                                <?= htmlspecialchars($civilStatusOptions[$personalData['civilstatus'] ?? ''] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date of Birth <?php if ($canEdit): ?><span class="required">*</span><?php endif; ?></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-calendar"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="date" class="form-control" name="dateofbirth" 
                                   value="<?= htmlspecialchars($personalData['dateofbirth'] ?? '') ?>" required>
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= !empty($personalData['dateofbirth']) ? date('F j, Y', strtotime($personalData['dateofbirth'])) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Height (cm)</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-ruler-vertical"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="number" class="form-control" name="height" 
                                   placeholder="Height" value="<?= htmlspecialchars($personalData['height'] ?? '') ?>" step="0.01">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= !empty($personalData['height']) ? htmlspecialchars($personalData['height']) . ' cm' : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Weight (kg)</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-weight"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="number" class="form-control" name="weight" 
                                   placeholder="Weight" value="<?= htmlspecialchars($personalData['weight'] ?? '') ?>" step="0.01">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= !empty($personalData['weight']) ? htmlspecialchars($personalData['weight']) . ' kg' : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Blood Type</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-tint"></i></span>
                            <?php if ($canEdit): ?>
                            <select class="form-control" name="bloodtype">
                                <?php foreach ($bloodTypeOptions as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= ($personalData['bloodtype'] ?? '') === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <div class="view-only-select">
                                <span class="input-icon"><i class="fas fa-tint"></i></span>
                                <?= htmlspecialchars($bloodTypeOptions[$personalData['bloodtype'] ?? ''] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Place of Birth</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-map-marker-alt"></i></span>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="placeofbirth" 
                                   placeholder="Place of Birth" value="<?= htmlspecialchars($personalData['placeofbirth'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['placeofbirth'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Citizenship</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-flag"></i></span>
                            <?php if ($canEdit): ?>
                            <select class="form-control" name="citizenship">
                                <?php foreach ($citizenshipOptions as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= ($personalData['citizenship'] ?? '') === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <div class="view-only-select">
                                <span class="input-icon"><i class="fas fa-flag"></i></span>
                                <?= htmlspecialchars($citizenshipOptions[$personalData['citizenship'] ?? ''] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Citizenship Details</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-info-circle"></i></span>
                            <?php if ($canEdit): ?>
                            <select class="form-control" name="citizenshipdetails">
                                <?php foreach ($citizenshipDetails as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= ($personalData['citizenshipdetails'] ?? '') === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <div class="view-only-select">
                                <span class="input-icon"><i class="fas fa-info-circle"></i></span>
                                <?= htmlspecialchars($citizenshipDetails[$personalData['citizenshipdetails'] ?? ''] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 2: ADDRESS INFORMATION -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-map-marker-alt"></i>
                    <h2>Address Information</h2>
                </div>
                
                <!-- Permanent Address -->
                <div class="mb-4">
                    <h3 style="margin-bottom: 16px; color: var(--text); font-size: 18px;"><i class="fas fa-home me-2"></i>Permanent Address</h3>
                    <?php if ($canEdit): ?>
                    <div class="same-address-toggle">
                        <label class="toggle-switch">
                            <input type="checkbox" id="sameAddressToggle">
                            <span class="toggle-slider"></span>
                        </label>
                        <span>Same as Permanent Address</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Province</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="permprovince" 
                                   placeholder="Province" value="<?= htmlspecialchars($personalData['permprovince'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['permprovince'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">City/Municipality</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="permcitymunicipality" 
                                   placeholder="City/Municipality" value="<?= htmlspecialchars($personalData['permcitymunicipality'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['permcitymunicipality'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Barangay</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="permbarangay" 
                                   placeholder="Barangay" value="<?= htmlspecialchars($personalData['permbarangay'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['permbarangay'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Zip Code</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="permzipcode" 
                                   placeholder="Zip Code" value="<?= htmlspecialchars($personalData['permzipcode'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['permzipcode'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">House/Block No.</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="permhouseblockno" 
                                   placeholder="House/Block No." value="<?= htmlspecialchars($personalData['permhouseblockno'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['permhouseblockno'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Street Name</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="permstreetno" 
                                   placeholder="Street Name" value="<?= htmlspecialchars($personalData['permstreetno'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['permstreetno'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Subdivision/Village</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="permsubdivisionvillage" 
                                   placeholder="Subdivision/Village" value="<?= htmlspecialchars($personalData['permsubdivisionvillage'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['permsubdivisionvillage'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Temporary Address -->
                <div class="mt-4 pt-4 border-top">
                    <h3 style="margin-bottom: 16px; color: var(--text); font-size: 18px;"><i class="fas fa-building me-2"></i>Temporary Address</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Province</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" id="tempProvince" name="tempprovince" 
                                   placeholder="Province" value="<?= htmlspecialchars($personalData['tempprovince'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['tempprovince'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">City/Municipality</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" id="tempCity" name="tempcitymunicipality" 
                                   placeholder="City/Municipality" value="<?= htmlspecialchars($personalData['tempcitymunicipality'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['tempcitymunicipality'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Barangay</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" id="tempBarangay" name="tempbarangay" 
                                   placeholder="Barangay" value="<?= htmlspecialchars($personalData['tempbarangay'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['tempbarangay'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Zip Code</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" id="tempZip" name="tempzipcode" 
                                   placeholder="Zip Code" value="<?= htmlspecialchars($personalData['tempzipcode'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['tempzipcode'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">House/Block No.</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" id="tempHouse" name="temphouseblockno" 
                                   placeholder="House/Block No." value="<?= htmlspecialchars($personalData['temphouseblockno'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['temphouseblockno'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Street Name</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" id="tempStreet" name="tempstreetno" 
                                   placeholder="Street Name" value="<?= htmlspecialchars($personalData['tempstreetno'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['tempstreetno'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Subdivision/Village</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" id="tempSubdivision" name="tempsubdivisionvillage" 
                                   placeholder="Subdivision/Village" value="<?= htmlspecialchars($personalData['tempsubdivisionvillage'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($personalData['tempsubdivisionvillage'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 3: CONTACT INFORMATION & PROFILE -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-phone"></i>
                    <h2>Contact Information & Profile</h2>
                </div>
                
                <div class="form-row">
                    <!-- Profile Picture -->
                    <div class="form-group">
                        <div class="profile-picture-container">
                            <label class="form-label">Profile Picture</label>
                            <div class="profile-picture" id="profilePictureContainer">
                                <?php 
                                $profilePicPath = $personalData['profile_picture'] ?? '';
                                
                                if (!empty($profilePicPath)) {
                                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                                    $host = $_SERVER['HTTP_HOST'];
                                    $scriptPath = $_SERVER['SCRIPT_NAME'];
                                    $scriptDir = dirname($scriptPath);
                                    $baseDir = str_replace('/public', '', $scriptDir);
                                    $baseDir = rtrim($baseDir, '/');
                                    
                                    $imageUrl = $protocol . '://' . $host . $baseDir . '/uploads/' . $profilePicPath;
                                    $fullPath = realpath(__DIR__ . '/../uploads/' . $profilePicPath);
                                    
                                    if ($fullPath && file_exists($fullPath)) {
                                        $timestamp = filemtime($fullPath);
                                        echo '<img src="' . htmlspecialchars($imageUrl . '?t=' . $timestamp) . '" 
                                             alt="Profile Picture" 
                                             id="profileImagePreview"
                                             onerror="handleImageError(this)">';
                                        echo '<i class="fas fa-user" style="display: none;"></i>';
                                    } else {
                                        echo '<i class="fas fa-user"></i>';
                                    }
                                } else {
                                    echo '<i class="fas fa-user"></i>';
                                }
                                ?>
                                
                                <?php if ($canEdit): ?>
                                <label for="profilePicture" class="profile-upload-btn">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="profilePicture" name="profilepicture" accept="image/*" 
                                       class="d-none" onchange="previewImage(event)">
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">Recommended: 150x150 px, Max 2MB</small>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="form-group" style="flex: 2;">
                        <h3 style="margin-bottom: 20px; color: var(--text); font-size: 18px;"><i class="fas fa-phone me-2"></i>Contact Information</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Mobile Number</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-mobile-alt"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="tel" class="form-control" name="mobilenumber" 
                                       placeholder="09XX-XXX-XXXX" value="<?= htmlspecialchars($personalData['mobilenumber'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['mobilenumber'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Telephone Number</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-phone"></i></span>
                                <?php if ($canEdit): ?>
                                <input type="tel" class="form-control" name="telephonenumber" 
                                       placeholder="02-XXXX-XXXX" value="<?= htmlspecialchars($personalData['telephonenumber'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($personalData['telephonenumber'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 4: GOVERNMENT IDs -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-id-card"></i>
                    <h2>Government IDs</h2>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">TIN NO.</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="tinno" 
                               placeholder="TIN No." value="<?= htmlspecialchars($personalData['tinno'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($personalData['tinno'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">UMID NO.</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="umidno" 
                               placeholder="UMID No." value="<?= htmlspecialchars($personalData['umidno'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($personalData['umidno'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">PAGIBIG NO.</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="pagibigno" 
                               placeholder="PAGIBIG No." value="<?= htmlspecialchars($personalData['pagibigno'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($personalData['pagibigno'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">PHILSYS NO.</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="philsysno" 
                               placeholder="PhilSys No." value="<?= htmlspecialchars($personalData['philsysno'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($personalData['philsysno'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">AGENCY EMPLOYEE NO.</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="agencyemployeeno" 
                               placeholder="Agency Employee No." value="<?= htmlspecialchars($personalData['agencyemployeeno'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($personalData['agencyemployeeno'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
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
        // Image error handler
        function handleImageError(img) {
            console.error('Image failed to load:', img.src);
            img.style.display = 'none';
            const icon = img.nextElementSibling;
            if (icon && icon.classList.contains('fa-user')) {
                icon.style.display = 'flex';
            }
        }

        <?php if ($canEdit): ?>
        function previewImage(event) {
            const input = event.target;
            const container = document.getElementById('profilePictureContainer');
            
            if (input.files && input.files[0]) {
                // Validate file size (max 2MB)
                if (input.files[0].size > 2 * 1024 * 1024) {
                    alert('File is too large. Maximum size is 2MB.');
                    input.value = '';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(input.files[0].type)) {
                    alert('Invalid file type. Please upload an image (JPEG, PNG, GIF).');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = container.querySelector('img');
                    const icon = container.querySelector('i');
                    
                    if (img) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                        if (icon) icon.style.display = 'none';
                    } else {
                        // Hide icon
                        if (icon) icon.style.display = 'none';
                        
                        // Create new image
                        const newImg = document.createElement('img');
                        newImg.id = 'profileImagePreview';
                        newImg.src = e.target.result;
                        newImg.alt = 'Profile Picture';
                        newImg.style.width = '100%';
                        newImg.style.height = '100%';
                        newImg.style.objectFit = 'cover';
                        newImg.onerror = function() { handleImageError(this); };
                        container.prepend(newImg);
                    }
                }
                
                reader.onerror = function() {
                    alert('Error reading file. Please try another image.');
                    input.value = '';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
            
            document.getElementById('sameAddressToggle').addEventListener('change', function() {
                const isChecked = this.checked;
                const permFields = ['province', 'citymunicipality', 'barangay', 'zipcode', 'houseblockno', 'streetno', 'subdivisionvillage'];
                
                if (isChecked) {
                    permFields.forEach(field => {
                        const permValue = document.querySelector(`[name="perm${field}"]`)?.value;
                        if (permValue) {
                            const tempField = document.getElementById(`temp${field.charAt(0).toUpperCase() + field.slice(1)}`);
                            if (tempField) {
                                tempField.value = permValue;
                            }
                        }
                    });
                }
            });
            
            // Form validation
            document.getElementById('personalDataForm').addEventListener('submit', function(e) {
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
            
            // Auto-format phone numbers
            document.querySelectorAll('[name="mobilenumber"], [name="telephonenumber"]').forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    if (this.name === 'mobilenumber' && value.length > 0) {
                        value = value.substring(0, 11);
                        if (value.length > 4 && value.length <= 7) {
                            value = value.replace(/(\d{4})(\d{3})/, '$1-$2');
                        } else if (value.length > 7) {
                            value = value.replace(/(\d{4})(\d{3})(\d{4})/, '$1-$2-$3');
                        }
                    } else if (this.name === 'telephonenumber' && value.length > 0) {
                        value = value.substring(0, 10);
                        if (value.length > 2 && value.length <= 6) {
                            value = value.replace(/(\d{2})(\d{4})/, '$1-$2');
                        } else if (value.length > 6) {
                            value = value.replace(/(\d{2})(\d{4})(\d{4})/, '$1-$2-$3');
                        }
                    }
                    
                    e.target.value = value;
                });
            });
        <?php endif; ?>
    </script>
</body>
</html>