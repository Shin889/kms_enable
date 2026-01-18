<?php
// personal_data.php - Complete profile view with paginated sections
require_once __DIR__ . '/../src/init.php';

// Helper functions
function display($data) {
    return htmlspecialchars($data ?? 'N/A', ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    if (!$date || $date === '0000-00-00' || $date === 'NULL') return 'N/A';
    return date('M d, Y', strtotime($date));
}

// Check if user is logged in
$user = current_user();
if (!$user) {
    redirect('login.php');
}

// Get applicant UID from query or session
if (isset($_GET['uid']) && in_array($user['role'], ['admin', 'president'])) {
    // Admins/presidents can view other users
    $view_uid = $_GET['uid'];
} else {
    // Applicants can only view their own data
    $view_uid = $user['uid'];
}

// Check if print mode
$isPrintMode = isset($_GET['print']) && $_GET['print'] == 1;

// Fetch user data from users table
$usr_stmt = db()->prepare("SELECT uid, email, firstName, middleName, lastName, userName, role FROM users WHERE uid = ?");
$usr_stmt->execute([$view_uid]);
$usr = $usr_stmt->fetch(PDO::FETCH_ASSOC);

if (!$usr) {
    echo "<p style='color:red;'>User not found.</p>";
    exit;
}

// Check permissions
if ($user['role'] === 'applicant' && $user['uid'] !== $view_uid) {
    // Applicants cannot view other users' data
    redirect('profile.php');
}

// Fetch data from multiple tables
try {
    // 1. Get detailed profile
    $profile_stmt = db()->prepare("SELECT * FROM applicant_profiles WHERE applicant_uid = ?");
    $profile_stmt->execute([$view_uid]);
    $profileData = $profile_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Get work experiences
    $work_stmt = db()->prepare("SELECT * FROM work_experiences WHERE applicant_uid = ? ORDER BY fromdate DESC");
    $work_stmt->execute([$view_uid]);
    $workData = $work_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Get civil service eligibilities
    $eligibility_stmt = db()->prepare("SELECT * FROM civil_service_eligibilities WHERE applicant_uid = ?");
    $eligibility_stmt->execute([$view_uid]);
    $eligibilityData = $eligibility_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Get training/learning development
    $training_stmt = db()->prepare("SELECT * FROM learning_development WHERE applicant_uid = ? ORDER BY datefrom DESC");
    $training_stmt->execute([$view_uid]);
    $trainingData = $training_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Get voluntary work
    $voluntary_stmt = db()->prepare("SELECT * FROM voluntary_work WHERE user_uid = ? ORDER BY date_from DESC");
    $voluntary_stmt->execute([$view_uid]);
    $voluntaryData = $voluntary_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 6. Get other information
    $other_stmt = db()->prepare("SELECT * FROM other_information WHERE applicant_uid = ?");
    $other_stmt->execute([$view_uid]);
    $otherData = $other_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Helper function to get full URL for profile picture (same as in profile.php)
function getProfilePictureUrl($profilePicturePath) {
    if (empty($profilePicturePath)) {
        return null;
    }
    
    // Construct full URL for the profile picture
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $scriptDir = dirname($scriptPath);
    $baseDir = str_replace('/public', '', $scriptDir);
    $baseDir = rtrim($baseDir, '/');
    
    $profilePictureUrl = $protocol . '://' . $host . $baseDir . '/uploads/' . $profilePicturePath;
    
    // Verify file exists and add timestamp
    $fullPath = realpath(__DIR__ . '/../uploads/' . $profilePicturePath);
    if ($fullPath && file_exists($fullPath)) {
        return $profilePictureUrl . '?t=' . filemtime($fullPath);
    }
    
    return $profilePictureUrl;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Data | KMS Enable Recruitment</title>
    
    <!-- Additional CSS only for what's missing -->
    <style>
        /* Additional styles not in your existing CSS */
        .tab-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: var(--bg-2);
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .tab-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .profile-section {
            display: none;
        }
        
        .profile-section.active {
            display: block;
        }
        
        .info-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .info-item {
            margin-bottom: 12px;
        }
        
        .info-label {
            font-weight: 500;
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .info-value {
            color: var(--text);
            font-size: 16px;
            padding: 8px 0;
            min-height: 24px;
            border-bottom: 1px solid var(--border);
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border);
        }
        
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border);
            margin: 0 auto 20px;
            display: block;
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
        
        .no-print {
            display: block;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .profile-section {
                display: block !important;
                page-break-inside: avoid;
            }
            
            .section-card {
                break-inside: avoid;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
            
            body {
                padding: 0;
                font-size: 12px;
            }
            
            .personal-data-container {
                max-width: 100%;
            }
            
            .info-value {
                border-bottom: 1px solid #ccc;
            }
            
            a {
                text-decoration: none;
                color: black;
            }
        }
    </style>
    
    <!-- Your existing CSS -->
    <link rel="stylesheet" href="assets/utils/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="personal-data-container <?= $isPrintMode ? 'print-mode' : '' ?>">
        <!-- Header with Profile Picture -->
        <div class="profile-header no-print">
            <?php 
            $profilePicUrl = !empty($profileData['profile_picture']) 
                ? getProfilePictureUrl($profileData['profile_picture']) 
                : null;
            ?>
            <?php if ($profilePicUrl): ?>
                <img src="<?= display($profilePicUrl) ?>" alt="Profile Picture" class="profile-picture">
            <?php else: ?>
                <div class="profile-picture" style="background: var(--bg-2); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user" style="font-size: 48px; color: var(--muted);"></i>
                </div>
            <?php endif; ?>
            <h1><?= display($usr['firstName'] . ' ' . $usr['lastName']) ?></h1>
            <p><?= display($usr['email']) ?></p>
        </div>
        
        <!-- Tab Navigation (Hidden in Print Mode) -->
        <div class="tab-nav no-print">
            <button class="tab-btn active" onclick="showSection(1)">Personal Info</button>
            <button class="tab-btn" onclick="showSection(2)">Education</button>
            <button class="tab-btn" onclick="showSection(3)">Work Experience</button>
            <button class="tab-btn" onclick="showSection(4)">Eligibilities</button>
            <button class="tab-btn" onclick="showSection(5)">Training</button>
            <button class="tab-btn" onclick="showSection(6)">Voluntary Work</button>
            <button class="tab-btn" onclick="showSection(7)">Other Info</button>
        </div>
        
        <!-- Section 1: Personal Information -->
        <div id="section-1" class="profile-section active">
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-user"></i> Personal Information</h2>
                </div>
                
                <div class="info-group">
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value">
                            <?= display(trim(
                                ($profileData['firstname'] ?? $usr['firstName']) . ' ' . 
                                ($profileData['middlename'] ?? $usr['middleName']) . ' ' . 
                                ($profileData['lastname'] ?? $usr['lastName'])
                            )) ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?= formatDate($profileData['dateofbirth'] ?? null) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Gender</div>
                        <div class="info-value"><?= display($profileData['sex']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Civil Status</div>
                        <div class="info-value"><?= display($profileData['civilstatus']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Place of Birth</div>
                        <div class="info-value"><?= display($profileData['placeofbirth']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Citizenship</div>
                        <div class="info-value"><?= display($profileData['citizenship']) ?></div>
                    </div>
                </div>
                
                <div class="info-group">
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?= display($profileData['emailaddress'] ?? $usr['email']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Mobile Number</div>
                        <div class="info-value"><?= display($profileData['mobilenumber']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Telephone Number</div>
                        <div class="info-value"><?= display($profileData['telephonenumber']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Height</div>
                        <div class="info-value"><?= display($profileData['height']) ?> cm</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Weight</div>
                        <div class="info-value"><?= display($profileData['weight']) ?> kg</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Blood Type</div>
                        <div class="info-value"><?= display($profileData['bloodtype']) ?></div>
                    </div>
                </div>
                
                <!-- Permanent Address -->
                <div class="section-header" style="margin-top: 30px;">
                    <h3><i class="fas fa-home"></i> Permanent Address</h3>
                </div>
                <div class="info-group">
                    <div class="info-item">
                        <div class="info-label">House/Block No.</div>
                        <div class="info-value"><?= display($profileData['permhouseblockno']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Street</div>
                        <div class="info-value"><?= display($profileData['permstreetno']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Subdivision/Village</div>
                        <div class="info-value"><?= display($profileData['permsubdivisionvillage']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Barangay</div>
                        <div class="info-value"><?= display($profileData['permbarangay']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">City/Municipality</div>
                        <div class="info-value"><?= display($profileData['permcitymunicipality']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Province</div>
                        <div class="info-value"><?= display($profileData['permprovince']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ZIP Code</div>
                        <div class="info-value"><?= display($profileData['permzipcode']) ?></div>
                    </div>
                </div>
                
                <!-- Temporary Address -->
                <div class="section-header" style="margin-top: 30px;">
                    <h3><i class="fas fa-map-marker-alt"></i> Temporary Address</h3>
                </div>
                <div class="info-group">
                    <div class="info-item">
                        <div class="info-label">House/Block No.</div>
                        <div class="info-value"><?= display($profileData['temphouseblockno']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Street</div>
                        <div class="info-value"><?= display($profileData['tempstreetno']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Subdivision/Village</div>
                        <div class="info-value"><?= display($profileData['tempsubdivisionvillage']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Barangay</div>
                        <div class="info-value"><?= display($profileData['tempbarangay']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">City/Municipality</div>
                        <div class="info-value"><?= display($profileData['tempcitymunicipality']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Province</div>
                        <div class="info-value"><?= display($profileData['tempprovince']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ZIP Code</div>
                        <div class="info-value"><?= display($profileData['tempzipcode']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Section 2: Educational Background -->
        <div id="section-2" class="profile-section">
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-graduation-cap"></i> Educational Background</h2>
                </div>
                
                <div class="education-levels">
                    <!-- Elementary -->
                    <div class="education-level">
                        <h4>Elementary</h4>
                        <div class="info-group">
                            <div class="info-item">
                                <div class="info-label">School</div>
                                <div class="info-value"><?= display($profileData['elementary_school']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Degree/Course</div>
                                <div class="info-value"><?= display($profileData['elementary_degree']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Year Graduated</div>
                                <div class="info-value"><?= display($profileData['elementary_year']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Secondary -->
                    <div class="education-level">
                        <h4>Secondary</h4>
                        <div class="info-group">
                            <div class="info-item">
                                <div class="info-label">School</div>
                                <div class="info-value"><?= display($profileData['secondary_school']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Degree/Course</div>
                                <div class="info-value"><?= display($profileData['secondary_degree']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Year Graduated</div>
                                <div class="info-value"><?= display($profileData['secondary_year']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- College -->
                    <div class="education-level">
                        <h4>College</h4>
                        <div class="info-group">
                            <div class="info-item">
                                <div class="info-label">School</div>
                                <div class="info-value"><?= display($profileData['college_school']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Degree/Course</div>
                                <div class="info-value"><?= display($profileData['college_degree']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Year Graduated</div>
                                <div class="info-value"><?= display($profileData['college_year']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Section 3: Work Experience -->
        <div id="section-3" class="profile-section">
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-briefcase"></i> Work Experience</h2>
                </div>
                
                <?php if (empty($workData)): ?>
                    <div class="info-item">
                        <div class="info-value" style="text-align: center; color: var(--muted); font-style: italic;">
                            No work experience recorded
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($workData as $work): ?>
                        <div class="education-level" style="margin-bottom: 20px;">
                            <div class="info-group">
                                <div class="info-item">
                                    <div class="info-label">Position Title</div>
                                    <div class="info-value"><?= display($work['positiontitle']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Company/Agency</div>
                                    <div class="info-value"><?= display($work['companyofficeagency']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Appointment Status</div>
                                    <div class="info-value"><?= display($work['appointmentstatus']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Government Service</div>
                                    <div class="info-value"><?= $work['governmentservice'] ? 'Yes' : 'No' ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Date</div>
                                    <div class="info-value">
                                        <?= formatDate($work['fromdate']) ?> - 
                                        <?= $work['is_present'] ? 'Present' : formatDate($work['todate']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Section 4: Civil Service Eligibilities -->
        <div id="section-4" class="profile-section">
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-file-certificate"></i> Civil Service Eligibilities</h2>
                </div>
                
                <?php if (empty($eligibilityData)): ?>
                    <div class="info-item">
                        <div class="info-value" style="text-align: center; color: var(--muted); font-style: italic;">
                            No civil service eligibilities recorded
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($eligibilityData as $eligibility): ?>
                        <div class="education-level" style="margin-bottom: 20px;">
                            <div class="info-group">
                                <div class="info-item">
                                    <div class="info-label">Eligibility Name</div>
                                    <div class="info-value"><?= display($eligibility['eligibilityname']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Rating</div>
                                    <div class="info-value"><?= display($eligibility['rating']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Date of Examination</div>
                                    <div class="info-value"><?= formatDate($eligibility['dateofexamination']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Place of Examination</div>
                                    <div class="info-value"><?= display($eligibility['placeofexamination']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">License Number</div>
                                    <div class="info-value"><?= display($eligibility['licensenumber']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Section 5: Training Programs -->
        <div id="section-5" class="profile-section">
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-chalkboard-teacher"></i> Training & Learning Development</h2>
                </div>
                
                <?php if (empty($trainingData)): ?>
                    <div class="info-item">
                        <div class="info-value" style="text-align: center; color: var(--muted); font-style: italic;">
                            No training programs recorded
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($trainingData as $training): ?>
                        <div class="education-level" style="margin-bottom: 20px;">
                            <div class="info-group">
                                <div class="info-item">
                                    <div class="info-label">Training Title</div>
                                    <div class="info-value"><?= display($training['titleoflearning']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Date</div>
                                    <div class="info-value">
                                        <?= formatDate($training['datefrom']) ?> - <?= formatDate($training['dateto']) ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Hours</div>
                                    <div class="info-value"><?= display($training['numberofhours']) ?> hours</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Type of ID</div>
                                    <div class="info-value"><?= display($training['typeofid']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Conducted By</div>
                                    <div class="info-value"><?= display($training['conducted']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Section 6: Voluntary Work -->
        <div id="section-6" class="profile-section">
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-hands-helping"></i> Voluntary Work</h2>
                </div>
                
                <?php if (empty($voluntaryData)): ?>
                    <div class="info-item">
                        <div class="info-value" style="text-align: center; color: var(--muted); font-style: italic;">
                            No voluntary work recorded
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($voluntaryData as $voluntary): ?>
                        <div class="education-level" style="margin-bottom: 20px;">
                            <div class="info-group">
                                <div class="info-item">
                                    <div class="info-label">Organization</div>
                                    <div class="info-value"><?= display($voluntary['organization']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Position</div>
                                    <div class="info-value"><?= display($voluntary['position']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Date</div>
                                    <div class="info-value">
                                        <?= formatDate($voluntary['date_from']) ?> - <?= formatDate($voluntary['date_to']) ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Hours</div>
                                    <div class="info-value"><?= display($voluntary['number_of_hours']) ?> hours</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Section 7: Other Information -->
        <div id="section-7" class="profile-section">
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-info-circle"></i> Other Information</h2>
                </div>
                
                <?php if (!$otherData): ?>
                    <div class="info-item">
                        <div class="info-value" style="text-align: center; color: var(--muted); font-style: italic;">
                            No other information recorded
                        </div>
                    </div>
                <?php else: ?>
                    <div class="info-group">
                        <div class="info-item">
                            <div class="info-label">Skills & Hobbies</div>
                            <div class="info-value"><?= nl2br(display($otherData['skillshobbies'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Recognition/Awards</div>
                            <div class="info-value"><?= nl2br(display($otherData['recognition'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Organizations</div>
                            <div class="info-value"><?= nl2br(display($otherData['organizations'])) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Action Bar (Hidden in Print Mode) -->
        <div class="action-bar no-print">
            <div>
                <a href="profile.php<?= $user['uid'] !== $view_uid ? '?uid=' . $view_uid : '' ?>" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
                <button class="btn btn-default" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Profile
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Tab navigation functionality
        function showSection(sectionNumber) {
            // Hide all sections
            document.querySelectorAll('.profile-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById('section-' + sectionNumber).classList.add('active');
            
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn')[sectionNumber - 1].classList.add('active');
            
            // Scroll to top of section
            document.getElementById('section-' + sectionNumber).scrollIntoView({behavior: 'smooth'});
        }
        
        // If in print mode, show all sections
        <?php if ($isPrintMode): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.profile-section').forEach(section => {
                section.style.display = 'block';
                section.classList.add('active');
            });
            
            // Remove active class from tabs
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.style.display = 'none';
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>