<?php
// other-information.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/upload.php';

$currentUser = current_user();
if (!$currentUser) {
    header("Location: login.php");
    exit;
}

// Check if user is allowed to view/update other information
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
$other_data = [];
$error = '';
$success = '';

// Check if other information exists
try {
    $stmt = db()->prepare("SELECT * FROM other_information WHERE applicant_uid = ?");
    $stmt->execute([$view_uid]);
    $other_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no data exists, create empty record (only for applicants editing their own data)
    if (!$other_data && $canEdit) {
        $stmt = db()->prepare("INSERT INTO other_information (applicant_uid) VALUES (?)");
        $stmt->execute([$view_uid]);
        
        $stmt = db()->prepare("SELECT * FROM other_information WHERE applicant_uid = ?");
        $stmt->execute([$view_uid]);
        $other_data = $stmt->fetch(PDO::FETCH_ASSOC);
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
        
        // Prepare the update query for other information fields
        $updateFields = [
            'skillshobbies' => $data['skillshobbies'] ?? '',
            'recognition' => $data['recognition'] ?? '',
            'organizations' => $data['organizations'] ?? '',
            'relatedaffinityb3rddegree' => isset($data['relatedaffinityb3rddegree']) ? 1 : 0,
            'relatedaffinityb4thdegree' => isset($data['relatedaffinityb4thdegree']) ? 1 : 0,
            'relatedaffinitydetails' => $data['relatedaffinitydetails'] ?? '',
            'administrativeoffense' => isset($data['administrativeoffense']) ? 1 : 0,
            'administrativeoffensedetails' => $data['administrativeoffensedetails'] ?? '',
            'criminallychargedbeforecourt' => isset($data['criminallychargedbeforecourt']) ? 1 : 0,
            'criminallychargeddetails' => $data['criminallychargeddetails'] ?? '',
            'criminallychargeddatefiled' => !empty($data['criminallychargeddatefiled']) ? $data['criminallychargeddatefiled'] : null,
            'criminallychargedstatuscase' => $data['criminallychargedstatuscase'] ?? '',
            'crimeorviolation' => isset($data['crimeorviolation']) ? 1 : 0,
            'crimedetails' => $data['crimedetails'] ?? '',
            'separatedfromservice' => isset($data['separatedfromservice']) ? 1 : 0,
            'separatedfromservicedetails' => $data['separatedfromservicedetails'] ?? '',
            'localelectionlastyear' => isset($data['localelectionlastyear']) ? 1 : 0,
            'localelectiondetails' => $data['localelectiondetails'] ?? '',
            'resignedtocampaignlast3months' => isset($data['resignedtocampaignlast3months']) ? 1 : 0,
            'resignedtocampaigndetails' => $data['resignedtocampaigndetails'] ?? '',
            'acquiredimmigrantstatus' => isset($data['acquiredimmigrantstatus']) ? 1 : 0,
            'immigrantstatuscountry' => $data['immigrantstatuscountry'] ?? '',
            'indigenousgroup' => isset($data['indigenousgroup']) ? 1 : 0,
            'indigenousgroupidno' => $data['indigenousgroupidno'] ?? '',
            'personwithdisability' => isset($data['personwithdisability']) ? 1 : 0,
            'personwithdisabilityidno' => $data['personwithdisabilityidno'] ?? '',
            'soloparent' => isset($data['soloparent']) ? 1 : 0,
            'soloparentidno' => $data['soloparentidno'] ?? '',
            'ref1name' => $data['ref1name'] ?? '',
            'ref1address' => $data['ref1address'] ?? '',
            'ref1telno' => $data['ref1telno'] ?? '',
            'ref2name' => $data['ref2name'] ?? '',
            'ref2address' => $data['ref2address'] ?? '',
            'ref2telno' => $data['ref2telno'] ?? '',
            'ref3name' => $data['ref3name'] ?? '',
            'ref3address' => $data['ref3address'] ?? '',
            'ref3telno' => $data['ref3telno'] ?? '',
            'goviddescription' => $data['goviddescription'] ?? '',
            'govidnumber' => $data['govidnumber'] ?? '',
            'goviddateplaceissued' => $data['goviddateplaceissued'] ?? ''
        ];
        
        if ($other_data) {
            // Update existing record
            $setClauses = [];
            $params = [];
            foreach ($updateFields as $field => $value) {
                $setClauses[] = "`$field` = ?";
                $params[] = $value;
            }
            $params[] = $view_uid;
            
            $sql = "UPDATE other_information SET " . implode(', ', $setClauses) . ", updated_at = NOW() WHERE applicant_uid = ?";
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
        } else {
            // Insert new record
            $fields = array_keys($updateFields);
            $placeholders = array_fill(0, count($fields), '?');
            $values = array_values($updateFields);
            $values[] = $view_uid;
            
            $sql = "INSERT INTO other_information (" . implode(', ', $fields) . ", applicant_uid, created_at, updated_at) 
                    VALUES (" . implode(', ', $placeholders) . ", ?, NOW(), NOW())";
            $stmt = db()->prepare($sql);
            $stmt->execute($values);
        }
        
        $success = "Other information saved successfully!";
        
        // Refresh the data
        $stmt = db()->prepare("SELECT * FROM other_information WHERE applicant_uid = ?");
        $stmt->execute([$view_uid]);
        $other_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
    <title><?= $canEdit ? 'Edit Other Information' : 'View Other Information' ?> | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/utils/personal_data.css">
</head>
<body>
    <div class="personal-data-container">
        <div class="section-header" style="margin-bottom: 30px; border: none;">
            <i class="fas fa-info-circle fa-2x"></i>
            <div style="flex: 1;">
                <h1 style="margin: 0 0 8px;">
                    <?= $canEdit ? 'Edit Other Information' : 'View Other Information' ?>
                    <?php if (!$canEdit): ?>
                        <span class="mode-badge">View Only</span>
                    <?php endif; ?>
                </h1>
                <p style="margin: 0; color: var(--muted);">
                    <?php if ($canEdit): ?>
                        Update your other information, references, and declarations
                    <?php else: ?>
                        Viewing other information for: <?= htmlspecialchars(($targetUser['firstName'] ?? '') . ' ' . ($targetUser['lastName'] ?? '')) ?>
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
        <form method="POST" id="otherInformationForm" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <?php else: ?>
        <div id="viewOnlyData">
        <?php endif; ?>
            
            <!-- SECTION 1: SKILLS, RECOGNITION & ORGANIZATIONS -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-info-circle"></i>
                    <h2>Skills, Recognition & Organizations</h2>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Skills and Hobbies</label>
                        <?php if ($canEdit): ?>
                        <textarea class="form-control" name="skillshobbies" rows="4" 
                                  placeholder="List your skills and hobbies"><?= htmlspecialchars($other_data['skillshobbies'] ?? '') ?></textarea>
                        <?php else: ?>
                        <div class="view-only-textarea">
                            <?= nl2br(htmlspecialchars($other_data['skillshobbies'] ?? 'No skills/hobbies listed')) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Recognition/Awards</label>
                        <?php if ($canEdit): ?>
                        <textarea class="form-control" name="recognition" rows="4" 
                                  placeholder="List your recognitions and awards"><?= htmlspecialchars($other_data['recognition'] ?? '') ?></textarea>
                        <?php else: ?>
                        <div class="view-only-textarea">
                            <?= nl2br(htmlspecialchars($other_data['recognition'] ?? 'No recognitions listed')) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Organizations</label>
                        <?php if ($canEdit): ?>
                        <textarea class="form-control" name="organizations" rows="4" 
                                  placeholder="List organizations you belong to"><?= htmlspecialchars($other_data['organizations'] ?? '') ?></textarea>
                        <?php else: ?>
                        <div class="view-only-textarea">
                            <?= nl2br(htmlspecialchars($other_data['organizations'] ?? 'No organizations listed')) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 2: RELATIONSHIP DECLARATION -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-users"></i>
                    <h2>Relationship Declaration</h2>
                </div>
                
                <div class="form-group">
                    <p style="margin-bottom: 15px; color: var(--text); font-size: 16px;">
                        Are you related by consanguinity or affinity to the appointing or recommending authority, 
                        or to chief of bureau or office or to the person who has immediate supervision over you 
                        in the Bureau or Department where you will be appointed?
                    </p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="relatedaffinityb3rddegree" value="1" 
                                           <?= ($other_data['relatedaffinityb3rddegree'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['relatedaffinityb3rddegree'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>a. within the third degree?</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="relatedaffinityb4thdegree" value="1" 
                                           <?= ($other_data['relatedaffinityb4thdegree'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['relatedaffinityb4thdegree'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>b. within the fourth degree (for LGU - Career Employees)?</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">If YES to (a) or (b), give details:</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="relatedaffinitydetails" 
                               placeholder="Relationship Details"
                               value="<?= htmlspecialchars($other_data['relatedaffinitydetails'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($other_data['relatedaffinitydetails'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 3: ADMINISTRATIVE OFFENSE -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-gavel"></i>
                    <h2>Administrative Offense</h2>
                </div>
                
                <div class="form-group">
                    <p style="margin-bottom: 15px; color: var(--text); font-size: 16px;">
                        Have you ever been found guilty of any administrative offense?
                    </p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="administrativeoffense" value="1" 
                                           <?= ($other_data['administrativeoffense'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['administrativeoffense'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Yes</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">If YES, give details:</label>
                        <?php if ($canEdit): ?>
                        <textarea class="form-control" name="administrativeoffensedetails" rows="3" 
                                  placeholder="Administrative Offense Details"><?= htmlspecialchars($other_data['administrativeoffensedetails'] ?? '') ?></textarea>
                        <?php else: ?>
                        <div class="view-only-textarea">
                            <?= nl2br(htmlspecialchars($other_data['administrativeoffensedetails'] ?? 'N/A')) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 4: CRIMINAL CHARGES -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-balance-scale"></i>
                    <h2>Criminal Charges</h2>
                </div>
                
                <div class="form-group">
                    <p style="margin-bottom: 15px; color: var(--text); font-size: 16px;">
                        Have you been criminally charged before any court?
                    </p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="criminallychargedbeforecourt" value="1" 
                                           <?= ($other_data['criminallychargedbeforecourt'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['criminallychargedbeforecourt'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Yes</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">If YES, give details (Nature of Offense/Case):</label>
                        <?php if ($canEdit): ?>
                        <textarea class="form-control" name="criminallychargeddetails" rows="3" 
                                  placeholder="Nature of Offense/Case"><?= htmlspecialchars($other_data['criminallychargeddetails'] ?? '') ?></textarea>
                        <?php else: ?>
                        <div class="view-only-textarea">
                            <?= nl2br(htmlspecialchars($other_data['criminallychargeddetails'] ?? 'N/A')) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date Filed</label>
                            <?php if ($canEdit): ?>
                            <input type="date" class="form-control" name="criminallychargeddatefiled" 
                                   value="<?= htmlspecialchars($other_data['criminallychargeddatefiled'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= !empty($other_data['criminallychargeddatefiled']) ? date('F j, Y', strtotime($other_data['criminallychargeddatefiled'])) : 'N/A' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status of Case</label>
                            <?php if ($canEdit): ?>
                            <input type="text" class="form-control" name="criminallychargedstatuscase" 
                                   placeholder="Status of Case"
                                   value="<?= htmlspecialchars($other_data['criminallychargedstatuscase'] ?? '') ?>">
                            <?php else: ?>
                            <div class="view-only-display">
                                <?= htmlspecialchars($other_data['criminallychargedstatuscase'] ?? 'N/A') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 5: CONVICTION RECORD -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Conviction Record</h2>
                </div>
                
                <div class="form-group">
                    <p style="margin-bottom: 15px; color: var(--text); font-size: 16px;">
                        Have you ever been convicted of any crime or violation of any law, decree, ordinance 
                        or regulation by any court or tribunal?
                    </p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="crimeorviolation" value="1" 
                                           <?= ($other_data['crimeorviolation'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['crimeorviolation'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Yes</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">If YES, give details:</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="crimedetails" 
                               placeholder="Crime Details"
                               value="<?= htmlspecialchars($other_data['crimedetails'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($other_data['crimedetails'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 6: SEPARATION FROM SERVICE -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-user-slash"></i>
                    <h2>Separation from Service</h2>
                </div>
                
                <div class="form-group">
                    <p style="margin-bottom: 15px; color: var(--text); font-size: 16px;">
                        Have you ever been separated from the service in any of the following modes: 
                        resignation, retirement, dropped from the rolls, dismissal, termination, 
                        end of term, finished contract or phased out (abolition) in the public or private sector?
                    </p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="separatedfromservice" value="1" 
                                           <?= ($other_data['separatedfromservice'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['separatedfromservice'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Yes</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">If YES, give details (Mode of Separation):</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="separatedfromservicedetails" 
                               placeholder="Mode of Separation Details"
                               value="<?= htmlspecialchars($other_data['separatedfromservicedetails'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($other_data['separatedfromservicedetails'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 7: ELECTION CANDIDACY -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-vote-yea"></i>
                    <h2>Election Candidacy</h2>
                </div>
                
                <div class="form-group">
                    <p style="margin-bottom: 15px; color: var(--text); font-size: 16px;">
                        Have you ever been a candidate in a national or local election held within 
                        the last year (except Barangay election)?
                    </p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="localelectionlastyear" value="1" 
                                           <?= ($other_data['localelectionlastyear'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['localelectionlastyear'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Yes</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">If YES, give details (Position/Date):</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="localelectiondetails" 
                               placeholder="Position, Date, etc."
                               value="<?= htmlspecialchars($other_data['localelectiondetails'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($other_data['localelectiondetails'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 8: RESIGNATION FOR CAMPAIGN -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-person-booth"></i>
                    <h2>Resignation for Campaign</h2>
                </div>
                
                <div class="form-group">
                    <p style="margin-bottom: 15px; color: var(--text); font-size: 16px;">
                        Have you resigned from the government service during the three (3)-month period 
                        before the last election to promote/actively campaign for a national or local candidate?
                    </p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="resignedtocampaignlast3months" value="1" 
                                           <?= ($other_data['resignedtocampaignlast3months'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['resignedtocampaignlast3months'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Yes</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">If YES, give details:</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="resignedtocampaigndetails" 
                               placeholder="Resigned to Campaign Details"
                               value="<?= htmlspecialchars($other_data['resignedtocampaigndetails'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($other_data['resignedtocampaigndetails'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 9: IMMIGRANT STATUS -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-passport"></i>
                    <h2>Immigrant Status</h2>
                </div>
                
                <div class="form-group">
                    <p style="margin-bottom: 15px; color: var(--text); font-size: 16px;">
                        Have you acquired the status of an immigrant or permanent resident of another country?
                    </p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="acquiredimmigrantstatus" value="1" 
                                           <?= ($other_data['acquiredimmigrantstatus'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['acquiredimmigrantstatus'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Yes</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">If YES, specify country:</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="immigrantstatuscountry" 
                               placeholder="Country"
                               value="<?= htmlspecialchars($other_data['immigrantstatuscountry'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($other_data['immigrantstatuscountry'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 10: SPECIAL GROUPS -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-hands-helping"></i>
                    <h2>Special Groups</h2>
                </div>
                
                <div class="form-group">
                    <p style="margin-bottom: 15px; color: var(--text); font-size: 16px;">
                        Pursuant to: (a)Indigenous People's Act(RA 8371); (b)Magna Carta for Disabled Persons(RA 7277, as amended); 
                        and (c)Expanded Solo Parents Welfare Act(RA 11861). Please answer the following items:
                    </p>
                    
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <h4 style="margin-bottom: 10px; color: var(--text); font-size: 16px;">
                                Are you a member of any indigenous group?
                            </h4>
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="indigenousgroup" value="1" 
                                           <?= ($other_data['indigenousgroup'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['indigenousgroup'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Yes</span>
                            </div>
                            <div style="margin-top: 10px;">
                                <label class="form-label">If YES, specify details/ID No.:</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="indigenousgroupidno" 
                                       placeholder="ID No. (If available)"
                                       value="<?= htmlspecialchars($other_data['indigenousgroupidno'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($other_data['indigenousgroupidno'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <h4 style="margin-bottom: 10px; color: var(--text); font-size: 16px;">
                                Are you a person with disability?
                            </h4>
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="personwithdisability" value="1" 
                                           <?= ($other_data['personwithdisability'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['personwithdisability'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Yes</span>
                            </div>
                            <div style="margin-top: 10px;">
                                <label class="form-label">If YES, specify details/ID No.:</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="personwithdisabilityidno" 
                                       placeholder="ID No. (If available)"
                                       value="<?= htmlspecialchars($other_data['personwithdisabilityidno'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($other_data['personwithdisabilityidno'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <h4 style="margin-bottom: 10px; color: var(--text); font-size: 16px;">
                                Are you a solo parent?
                            </h4>
                            <div class="same-address-toggle">
                                <label class="toggle-switch">
                                    <?php if ($canEdit): ?>
                                    <input type="checkbox" name="soloparent" value="1" 
                                           <?= ($other_data['soloparent'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" disabled <?= ($other_data['soloparent'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Yes</span>
                            </div>
                            <div style="margin-top: 10px;">
                                <label class="form-label">If YES, specify details/ID No.:</label>
                                <?php if ($canEdit): ?>
                                <input type="text" class="form-control" name="soloparentidno" 
                                       placeholder="ID No. (If available)"
                                       value="<?= htmlspecialchars($other_data['soloparentidno'] ?? '') ?>">
                                <?php else: ?>
                                <div class="view-only-display">
                                    <?= htmlspecialchars($other_data['soloparentidno'] ?? 'N/A') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 11: REFERENCES -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-handshake"></i>
                    <h2>References (At least three)</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 33%;">NAME</th>
                                <th style="width: 33%;">OFFICE / RESIDENTIAL ADDRESS</th>
                                <th style="width: 34%;">CONTACT NO.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Reference 1 -->
                            <tr>
                                <td>
                                    <?php if ($canEdit): ?>
                                    <input type="text" class="form-control" name="ref1name" 
                                           placeholder="Reference 1 Name"
                                           value="<?= htmlspecialchars($other_data['ref1name'] ?? '') ?>">
                                    <?php else: ?>
                                    <div class="view-only-display">
                                        <?= htmlspecialchars($other_data['ref1name'] ?? 'N/A') ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canEdit): ?>
                                    <input type="text" class="form-control" name="ref1address" 
                                           placeholder="Reference 1 Address"
                                           value="<?= htmlspecialchars($other_data['ref1address'] ?? '') ?>">
                                    <?php else: ?>
                                    <div class="view-only-display">
                                        <?= htmlspecialchars($other_data['ref1address'] ?? 'N/A') ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canEdit): ?>
                                    <input type="text" class="form-control" name="ref1telno" 
                                           placeholder="Reference 1 Contact No."
                                           value="<?= htmlspecialchars($other_data['ref1telno'] ?? '') ?>">
                                    <?php else: ?>
                                    <div class="view-only-display">
                                        <?= htmlspecialchars($other_data['ref1telno'] ?? 'N/A') ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Reference 2 -->
                            <tr>
                                <td>
                                    <?php if ($canEdit): ?>
                                    <input type="text" class="form-control" name="ref2name" 
                                           placeholder="Reference 2 Name"
                                           value="<?= htmlspecialchars($other_data['ref2name'] ?? '') ?>">
                                    <?php else: ?>
                                    <div class="view-only-display">
                                        <?= htmlspecialchars($other_data['ref2name'] ?? 'N/A') ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canEdit): ?>
                                    <input type="text" class="form-control" name="ref2address" 
                                           placeholder="Reference 2 Address"
                                           value="<?= htmlspecialchars($other_data['ref2address'] ?? '') ?>">
                                    <?php else: ?>
                                    <div class="view-only-display">
                                        <?= htmlspecialchars($other_data['ref2address'] ?? 'N/A') ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canEdit): ?>
                                    <input type="text" class="form-control" name="ref2telno" 
                                           placeholder="Reference 2 Contact No."
                                           value="<?= htmlspecialchars($other_data['ref2telno'] ?? '') ?>">
                                    <?php else: ?>
                                    <div class="view-only-display">
                                        <?= htmlspecialchars($other_data['ref2telno'] ?? 'N/A') ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Reference 3 -->
                            <tr>
                                <td>
                                    <?php if ($canEdit): ?>
                                    <input type="text" class="form-control" name="ref3name" 
                                           placeholder="Reference 3 Name"
                                           value="<?= htmlspecialchars($other_data['ref3name'] ?? '') ?>">
                                    <?php else: ?>
                                    <div class="view-only-display">
                                        <?= htmlspecialchars($other_data['ref3name'] ?? 'N/A') ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canEdit): ?>
                                    <input type="text" class="form-control" name="ref3address" 
                                           placeholder="Reference 3 Address"
                                           value="<?= htmlspecialchars($other_data['ref3address'] ?? '') ?>">
                                    <?php else: ?>
                                    <div class="view-only-display">
                                        <?= htmlspecialchars($other_data['ref3address'] ?? 'N/A') ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canEdit): ?>
                                    <input type="text" class="form-control" name="ref3telno" 
                                           placeholder="Reference 3 Contact No."
                                           value="<?= htmlspecialchars($other_data['ref3telno'] ?? '') ?>">
                                    <?php else: ?>
                                    <div class="view-only-display">
                                        <?= htmlspecialchars($other_data['ref3telno'] ?? 'N/A') ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- SECTION 12: GOVERNMENT ID -->
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-id-card"></i>
                    <h2>Government Issued ID</h2>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Gov ID Description</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="goviddescription" 
                               placeholder="Gov ID Description"
                               value="<?= htmlspecialchars($other_data['goviddescription'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($other_data['goviddescription'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Gov ID Number</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="govidnumber" 
                               placeholder="Gov ID Number"
                               value="<?= htmlspecialchars($other_data['govidnumber'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($other_data['govidnumber'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Gov ID Date/Place Issued</label>
                        <?php if ($canEdit): ?>
                        <input type="text" class="form-control" name="goviddateplaceissued" 
                               placeholder="Date and Place Issued"
                               value="<?= htmlspecialchars($other_data['goviddateplaceissued'] ?? '') ?>">
                        <?php else: ?>
                        <div class="view-only-display">
                            <?= htmlspecialchars($other_data['goviddateplaceissued'] ?? 'N/A') ?>
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
        <?php if ($canEdit): ?>
        // Form validation
        document.getElementById('otherInformationForm').addEventListener('submit', function(e) {
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
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }
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