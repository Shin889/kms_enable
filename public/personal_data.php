<?php
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
    <style>
        :root {
            --primary: #3b82f6;
            --primary-600: #2563eb;
            --primary-light: #dbeafe;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --bg: #f8fafc;
            --bg-2: #f1f5f9;
            --card: #ffffff;
            --input: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.5;
            padding: 20px;
        }
        
        .personal-data-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .section-card {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        
        .section-card:hover {
            border-color: var(--primary);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }
        
        .section-header i {
            font-size: 24px;
            color: var(--primary);
            margin-right: 12px;
            width: 40px;
            text-align: center;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: var(--text);
        }
        
        .section-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text);
            font-size: 14px;
        }
        
        .form-label .required {
            color: var(--danger);
        }
        
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-group .input-icon {
            position: absolute;
            left: 12px;
            color: var(--muted);
            z-index: 2;
        }
        
        .input-group .form-control {
            padding-left: 40px;
            width: 100%;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--input);
            color: var(--text);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }
        
        .profile-picture-container {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border);
            background: var(--bg-2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            overflow: hidden;
            position: relative;
        }
        
        .profile-picture i {
            font-size: 48px;
            color: var(--muted);
        }
        
        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-upload-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid var(--card);
        }
        
        .same-address-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            cursor: pointer;
        }
        
        .toggle-switch {
            position: relative;
            width: 40px;
            height: 20px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border);
            transition: .4s;
            border-radius: 20px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--primary);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }
        
        .children-container {
            margin-top: 16px;
        }
        
        .child-entry {
            background: var(--bg-2);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid var(--border);
        }
        
        .child-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .add-child-btn, .remove-child-btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        
        .add-child-btn {
            background: var(--primary);
            color: white;
        }
        
        .add-child-btn:hover {
            background: var(--primary-600);
        }
        
        .remove-child-btn {
            background: var(--danger);
            color: white;
        }
        
        .remove-child-btn:hover {
            background: #dc2626;
        }
        
        .education-levels {
            display: grid;
            gap: 24px;
            margin-top: 24px;
        }
        
        .education-level {
            background: var(--bg-2);
            border-radius: 8px;
            padding: 20px;
            border: 1px solid var(--border);
        }
        
        .education-level h4 {
            margin: 0 0 16px;
            color: var(--text);
            font-size: 16px;
            font-weight: 600;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .action-bar {
            position: sticky;
            bottom: 0;
            background: var(--card);
            padding: 20px;
            border-top: 1px solid var(--border);
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-default {
            background: var(--bg-2);
            color: var(--text);
            border: 1px solid var(--border);
        }
        
        .btn-default:hover {
            background: var(--border);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-600);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
        }
        
        .view-only-display {
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-2);
            color: var(--text);
            font-size: 14px;
            min-height: 46px;
            display: flex;
            align-items: center;
        }
        
        .view-only-select {
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-2);
            color: var(--text);
            font-size: 14px;
            min-height: 46px;
            display: flex;
            align-items: center;
            padding-left: 40px;
            position: relative;
        }
        
        .view-only-select .input-icon {
            position: absolute;
            left: 12px;
            color: var(--muted);
        }
        
        .mode-badge {
            display: inline-block;
            padding: 4px 12px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        @media (max-width: 768px) {
            .section-card {
                padding: 16px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .action-bar {
                flex-direction: column;
                gap: 12px;
            }
            
            .action-bar > div {
                width: 100%;
                display: flex;
                gap: 12px;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
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
            
            <!-- SECTION 5: FAMILY BACKGROUND -->
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
            
            <!-- SECTION 7: OTHER INFORMATION -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-info-circle"></i>
                    <h2>Other Information</h2>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Special Skills</label>
                        <?php if ($canEdit): ?>
                        <textarea class="form-control" name="special_skills" rows="3" 
                                  placeholder="Special skills and hobbies"><?= htmlspecialchars($personalData['special_skills'] ?? '') ?></textarea>
                        <?php else: ?>
                        <div class="view-only-display" style="min-height: 100px; align-items: flex-start; padding-top: 12px;">
                            <?= nl2br(htmlspecialchars($personalData['special_skills'] ?? 'N/A')) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Non-Academic Distinctions</label>
                        <?php if ($canEdit): ?>
                        <textarea class="form-control" name="non_academic" rows="3" 
                                  placeholder="Non-academic distinctions"><?= htmlspecialchars($personalData['non_academic'] ?? '') ?></textarea>
                        <?php else: ?>
                        <div class="view-only-display" style="min-height: 100px; align-items: flex-start; padding-top: 12px;">
                            <?= nl2br(htmlspecialchars($personalData['non_academic'] ?? 'N/A')) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Membership in Associations</label>
                        <?php if ($canEdit): ?>
                        <textarea class="form-control" name="memberships" rows="3" 
                                  placeholder="Membership in associations/organizations"><?= htmlspecialchars($personalData['memberships'] ?? '') ?></textarea>
                        <?php else: ?>
                        <div class="view-only-display" style="min-height: 100px; align-items: flex-start; padding-top: 12px;">
                            <?= nl2br(htmlspecialchars($personalData['memberships'] ?? 'N/A')) ?>
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