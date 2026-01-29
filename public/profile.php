<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/dashboard_sidebar.php';
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

$user = current_user();
if (!$user) {
    redirect('login.php');
}

$uid = $user['uid'];

$view_uid = isset($_GET['uid']) && in_array($user['role'], ['president', 'admin'])
    ? $_GET['uid']
    : $uid;

$usr_stmt = db()->prepare("SELECT uid, email, firstName, middleName, lastName, userName FROM users WHERE uid=?");
$usr_stmt->execute([$view_uid]);
$usr = $usr_stmt->fetch(PDO::FETCH_ASSOC);

$profile_stmt = db()->prepare("SELECT * FROM applicant_profiles WHERE applicant_uid=?");
$profile_stmt->execute([$view_uid]);
$profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);

$employment_stmt = db()->prepare("SELECT * FROM employee_tracking WHERE applicant_uid=?");
$employment_stmt->execute([$view_uid]);
$employment = $employment_stmt->fetch(PDO::FETCH_ASSOC);

if (!$usr) {
    echo "<p style='color:red;'>User not found.</p>";
    exit;
}

if (!$profile) {
    $profile = [];
}

if (!$employment) {
    $employment = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uid === $view_uid && $user['role'] === 'applicant') {
    csrf_check();

    $profile_pic_path = $profile['profile_picture'] ?? null;
    if (!empty($_FILES['profile_picture']['name'])) {
        $profile_pic_path = accept_upload($_FILES['profile_picture'], 'profile_pictures');
    }

    $req_json = $profile['requirements_docs'] ?? null;
    if (!empty($_FILES['requirements']['name'][0])) {
        $uploaded = [];
        foreach ($_FILES['requirements']['name'] as $i => $name) {
            $file = [
                'name' => $_FILES['requirements']['name'][$i],
                'type' => $_FILES['requirements']['type'][$i],
                'tmp_name' => $_FILES['requirements']['tmp_name'][$i],
                'error' => $_FILES['requirements']['error'][$i],
                'size' => $_FILES['requirements']['size'][$i]
            ];
            $uploaded[] = accept_upload($file, 'requirements');
        }
        $req_json = json_encode($uploaded);
    }

    db()->prepare("UPDATE users SET firstName=?, middleName=?, lastName=?, userName=? WHERE uid=?")
        ->execute([$_POST['firstName'], $_POST['middleName'], $_POST['lastName'], $_POST['userName'], $uid]);

    $skills = !empty($_POST['skills']) ? json_encode([$_POST['skills']]) : json_encode([]);
    $qualifications = !empty($_POST['qualifications']) ? json_encode([$_POST['qualifications']]) : json_encode([]);

    if (!empty($profile)) {
        db()->prepare("UPDATE applicant_profiles 
            SET skills=?, qualifications=?, other_details=?, letter_of_intent=?, profile_picture=?, requirements_docs=? 
            WHERE applicant_uid=?")
            ->execute([
                $skills,
                $qualifications,
                $_POST['other_details'],
                $_POST['letter_of_intent'],
                $profile_pic_path,
                $req_json,
                $uid
            ]);
    } else {
        db()->prepare("INSERT INTO applicant_profiles 
            (applicant_uid, skills, qualifications, other_details, letter_of_intent, profile_picture, requirements_docs) 
            VALUES (?,?,?,?,?,?,?)")
            ->execute([
                $uid,
                $skills,
                $qualifications,
                $_POST['other_details'],
                $_POST['letter_of_intent'],
                $profile_pic_path,
                $req_json
            ]);
    }

    redirect("profile.php");
}

$page_title = $uid === $view_uid ? 'My Profile' : 'Applicant Profile';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $uid === $view_uid ? 'My Profile' : 'Applicant Profile' ?> | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="assets/utils/dashboard.css">
    <link rel="stylesheet" href="assets/utils/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="dashboard-layout">
        <?php render_sidebar($user, ''); ?>
        
        <main class="tri-content" style="min-height: 100vh; display: flex; flex-direction: column;">
            <?php render_topbar($user, $page_title); ?>

            <div class="content-wrapper" style="flex: 1; padding: 20px;">
                <div class="page-content"><div style="width: 100%; height: 100%;">
                        <div class="container" style="width: 100%; max-width: 1200px; margin: 0 auto;">
                            <div class="header">
                                <h3><?= $page_title ?></h3>
                                <?php if ($uid !== $view_uid): ?>
                                    <span class="view-mode"><i class="fas fa-eye"></i> View Only</span>
                                <?php endif; ?>
                            </div>
                        <div class="profile-container" style="display: flex; gap: 24px; min-height: 600px;">
                            <div class="leftbar" style="flex: 0 0 300px;">
                                <div class="profile-header">
                                    <div class="profile-pic">
                                        <?php 
                                        $profilePicUrl = !empty($profile['profile_picture']) 
                                            ? getProfilePictureUrl($profile['profile_picture']) 
                                            : null;
                                        
                                        if (!empty($profilePicUrl)): ?>
                                            <img src="<?= htmlspecialchars($profilePicUrl) ?>" 
                                                alt="Profile Picture" 
                                                style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <!-- <div style="display: none; width: 100%; height: 100%; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                                                <i class="fas fa-user"></i>
                                            </div> -->
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="profile-name">
                                        <?= htmlspecialchars(trim(($usr['firstName'] ?? '') . ' ' . ($usr['lastName'] ?? ''))) ?>
                                    </div>
                                    <div class="profile-role">
                                        <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                    </div>
                                </div>

                                <div class="profile-stats">
                                    <div class="stat-item">
                                        <div class="stat-number">1</div>
                                        <div class="stat-label">Profile</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number">
                                            <?php 
                                            $st = db()->prepare("SELECT COUNT(*) FROM applications WHERE applicant_uid=?");
                                            $st->execute([$view_uid]);
                                            echo $st->fetchColumn();
                                            ?>
                                        </div>
                                        <div class="stat-label">Applications</div>
                                    </div>
                                </div>

                                <div class="contact-info">
                                    <div class="contact-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?= htmlspecialchars($usr['email'] ?? '') ?></span>
                                    </div>
                                    <div class="contact-item">
                                        <i class="fas fa-user-circle"></i>
                                        <span><?= htmlspecialchars($usr['userName'] ?? '') ?></span>
                                    </div>
                                </div>

                                <div class="sidebar-actions" style="margin-top: 20px; padding: 15px; background: var(--light); border-radius: 8px;">
                                    <h5 style="margin-top: 0; font-size: 14px; color: var(--dark);">
                                        <i class="fas fa-tasks"></i> Quick Actions
                                    </h5>
                                    
                                    <?php if ($uid === $view_uid): ?>
                                        <!-- For viewing own profile -->
                                        <a href="personal_data.php" class="sidebar-btn" style="display: flex; align-items: center; padding: 10px; background: white; border-radius: 6px; margin-bottom: 8px; text-decoration: none; color: var(--dark); border: 1px solid var(--border);">
                                            <i class="fas fa-id-card" style="margin-right: 10px; color: var(--primary);"></i>
                                            <span>Personal Data</span>
                                        </a>
                                    <?php elseif (in_array($user['role'], ['admin', 'president'])): ?>
                                        <!-- For admins/presidents viewing other profiles -->
                                        <a href="personal_data.php?uid=<?= $view_uid ?>" class="sidebar-btn" style="display: flex; align-items: center; padding: 10px; background: white; border-radius: 6px; margin-bottom: 8px; text-decoration: none; color: var(--dark); border: 1px solid var(--border);">
                                            <i class="fas fa-id-card" style="margin-right: 10px; color: var(--primary);"></i>
                                            <span>View Personal Data</span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="my_applications.php" class="sidebar-btn" style="display: flex; align-items: center; padding: 10px; background: white; border-radius: 6px; text-decoration: none; color: var(--dark); border: 1px solid var(--border);">
                                        <i class="fas fa-file-alt" style="margin-right: 10px; color: var(--primary);"></i>
                                        <span>My Applications</span>
                                    </a>
                                </div>
                            </div>

                            <div class="main-content" style="flex: 1;">
                                <?php if ($uid === $view_uid && $user['role'] === 'applicant'): ?>
                                    <div class="section">
                                        <h4><i class="fas fa-edit"></i> Edit Profile</h4>
                                        <form method="post" enctype="multipart/form-data">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            
                                            <div class="form-grid">
                                                <div class="form-group">
                                                    <label>First Name <span>*</span></label>
                                                    <input type="text" name="firstName" value="<?= htmlspecialchars($usr['firstName'] ?? '') ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Middle Name</label>
                                                    <input type="text" name="middleName" value="<?= htmlspecialchars($usr['middleName'] ?? '') ?>">
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Last Name <span>*</span></label>
                                                    <input type="text" name="lastName" value="<?= htmlspecialchars($usr['lastName'] ?? '') ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Username</label>
                                                    <input type="text" name="userName" value="<?= htmlspecialchars($usr['userName'] ?? '') ?>">
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Email</label>
                                                    <input type="text" value="<?= htmlspecialchars($usr['email'] ?? '') ?>" disabled>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Profile Picture</label>
                                                    <div class="file-upload">
                                                        <i class="fas fa-cloud-upload-alt"></i>
                                                        <p>Click to upload profile picture</p>
                                                        <span>PNG, JPG, GIF up to 2MB</span>
                                                        <input type="file" name="profile_picture" accept="image/*">
                                                    </div>
                                                    <?php if (!empty($profile['profile_picture'])): 
                                                        $currentProfilePicUrl = getProfilePictureUrl($profile['profile_picture']);
                                                    ?>
                                                        <div class="file-list">
                                                            <div class="file-item">
                                                                <i class="fas fa-image"></i>
                                                                <?php if (!empty($currentProfilePicUrl)): ?>
                                                                    <a href="<?= htmlspecialchars($currentProfilePicUrl) ?>" target="_blank">
                                                                        Current Profile Picture
                                                                    </a>
                                                                <?php else: ?>
                                                                    <span>Current Profile Picture</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="form-group full-width">
                                                <?php
                                                $skills = !empty($profile['skills']) ? json_decode($profile['skills'], true) : [];
                                                $qualifications = !empty($profile['qualifications']) ? json_decode($profile['qualifications'], true) : [];
                                                ?>
                                                <label>Skills (comma-separated)</label>
                                                <textarea name="skills" placeholder="e.g., PHP, JavaScript, Project Management"><?= htmlspecialchars(implode(', ', $skills)) ?></textarea>
                                            </div>
                                            
                                            <div class="form-group full-width">
                                                <label>Qualifications (comma-separated)</label>
                                                <textarea name="qualifications" placeholder="e.g., Bachelor's Degree in Computer Science, Certified Scrum Master"><?= htmlspecialchars(implode(', ', $qualifications)) ?></textarea>
                                            </div>
                                            
                                            <div class="form-group full-width">
                                                <label>Other Details</label>
                                                <textarea name="other_details" placeholder="Additional information about yourself"><?= htmlspecialchars($profile['other_details'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <div class="form-group full-width">
                                                <label>Letter of Intent</label>
                                                <textarea name="letter_of_intent" placeholder="Write your letter of intent here"><?= htmlspecialchars($profile['letter_of_intent'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <div class="form-group full-width">
                                                <label>Requirements Documents</label>
                                                <div class="file-upload">
                                                    <i class="fas fa-file-upload"></i>
                                                    <p>Click to upload requirements documents</p>
                                                    <span>PDF, DOC, DOCX up to 5MB each</span>
                                                    <input type="file" name="requirements[]" multiple accept=".pdf,.doc,.docx">
                                                </div>
                                                <?php if (!empty($profile['requirements_docs'])):
                                                    $reqs = json_decode($profile['requirements_docs'], true); ?>
                                                    <div class="file-list">
                                                        <?php foreach ($reqs as $r): 
                                                            $reqUrl = getProfilePictureUrl($r);
                                                        ?>
                                                            <div class="file-item">
                                                                <i class="fas fa-file-pdf"></i>
                                                                <a href="<?= htmlspecialchars($reqUrl) ?>" target="_blank"><?= basename($r) ?></a>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="action-buttons">
                                                <button type="submit" class="btn">
                                                    <i class="fas fa-save"></i> Save Profile
                                                </button>
                                                
                                                <a href="dashboard.php" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="section">
                                        <h4><i class="fas fa-user"></i> Profile Information</h4>
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <h5>Full Name</h5>
                                                <p><?= htmlspecialchars(trim(($usr['firstName'] ?? '') . ' ' . ($usr['middleName'] ?? '') . ' ' . ($usr['lastName'] ?? ''))) ?></p>
                                            </div>
                                            <div class="info-item">
                                                <h5>Email</h5>
                                                <p><?= htmlspecialchars($usr['email'] ?? '') ?></p>
                                            </div>
                                            <div class="info-item">
                                                <h5>Username</h5>
                                                <p><?= htmlspecialchars($usr['userName'] ?? 'N/A') ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="section" style="margin-top: 20px; padding: 0; border: none; box-shadow: none;">
                                            <h5 style="font-size: 16px; margin-bottom: 12px;">Skills</h5>
                                            <div class="skills-list">
                                                <?php
                                                $skills = !empty($profile['skills']) ? json_decode($profile['skills'], true) : [];
                                                foreach ($skills as $skill):
                                                    $skill = trim($skill);
                                                    if (!empty($skill)):
                                                ?>
                                                    <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                                                <?php endif; endforeach; ?>
                                                <?php if (empty($skills) || (count($skills) === 1 && empty(trim($skills[0])))): ?>
                                                    <span style="color: var(--muted); font-style: italic;">No skills listed</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="section" style="margin-top: 20px; padding: 0; border: none; box-shadow: none;">
                                            <h5 style="font-size: 16px; margin-bottom: 12px;">Qualifications</h5>
                                            <div class="skills-list">
                                                <?php
                                                $qualifications = !empty($profile['qualifications']) ? json_decode($profile['qualifications'], true) : [];
                                                foreach ($qualifications as $qual):
                                                    $qual = trim($qual);
                                                    if (!empty($qual)):
                                                ?>
                                                    <span class="skill-tag"><?= htmlspecialchars($qual) ?></span>
                                                <?php endif; endforeach; ?>
                                                <?php if (empty($qualifications) || (count($qualifications) === 1 && empty(trim($qualifications[0])))): ?>
                                                    <span style="color: var(--muted); font-style: italic;">No qualifications listed</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($profile['other_details'])): ?>
                                        <div class="info-item" style="margin-top: 20px;">
                                            <h5>Other Details</h5>
                                            <p><?= nl2br(htmlspecialchars($profile['other_details'])) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($profile['letter_of_intent'])): ?>
                                        <div class="info-item" style="margin-top: 20px;">
                                            <h5>Letter of Intent</h5>
                                            <p><?= nl2br(htmlspecialchars($profile['letter_of_intent'])) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($profile['requirements_docs'])):
                                            $reqs = json_decode($profile['requirements_docs'], true); ?>
                                            <div class="section" style="margin-top: 20px;">
                                                <h5 style="font-size: 16px; margin-bottom: 12px;">Requirements Documents</h5>
                                                <div class="file-list">
                                                    <?php foreach ($reqs as $r): 
                                                        $reqUrl = getProfilePictureUrl($r);
                                                    ?>
                                                        <div class="file-item">
                                                            <i class="fas fa-file-pdf"></i>
                                                            <a href="<?= htmlspecialchars($reqUrl) ?>" target="_blank"><?= basename($r) ?></a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($employment)): ?>
                                    <div class="section">
                                        <h4><i class="fas fa-briefcase"></i> Employment Status</h4>
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <h5>Status</h5>
                                                <p>
                                                    <span class="status-badge status-employed">
                                                        <?= ucfirst(str_replace('_', ' ', $employment['employment_status'])) ?>
                                                    </span>
                                                </p>
                                            </div>
                                            <?php if (!empty($employment['start_date'])): ?>
                                            <div class="info-item">
                                                <h5>Start Date</h5>
                                                <p><?= date('M j, Y', strtotime($employment['start_date'])) ?></p>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($employment['monitoring_start_date'])): ?>
                                            <div="info-item">
                                                <h5>Monitoring Start</h5>
                                                <p><?= date('M j, Y', strtotime($employment['monitoring_start_date'])) ?></p>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($employment['promotion_history'])): ?>
                                            <div class="info-item" style="grid-column: 1 / -1;">
                                                <h5>Promotion History</h5>
                                                <p><?= nl2br(htmlspecialchars($employment['promotion_history'])) ?></p>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($employment['remarks'])): ?>
                                            <div class="info-item" style="grid-column: 1 / -1;">
                                                <h5>Remarks</h5>
                                                <p><?= nl2br(htmlspecialchars($employment['remarks'])) ?></p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="action-buttons">
                                    <?php if ($uid !== $view_uid && in_array($user['role'], ['president', 'admin'])): ?>
                                        <a href="applications.php?uid=<?= $view_uid ?>" class="btn">
                                            <i class="fas fa-file-alt"></i> View Applications
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        
        // Check for saved sidebar state
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            if (sidebarToggle) {
                sidebarToggle.querySelector('i').classList.remove('fa-chevron-left');
                sidebarToggle.querySelector('i').classList.add('fa-chevron-right');
            }
        }
        
        // Toggle sidebar on desktop
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                
                // Update toggle icon
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                    localStorage.setItem('sidebarCollapsed', 'true');
                    
                    // Close all submenus when collapsing sidebar
                    closeAllSubmenus();
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                    localStorage.setItem('sidebarCollapsed', 'false');
                }
            });
        }
        
        // Toggle mobile menu
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-open');
                
                // Close all submenus when closing mobile menu
                if (!sidebar.classList.contains('mobile-open')) {
                    closeAllSubmenus();
                }
            });
        }
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (sidebar && mobileMenuToggle) {
                if (!sidebar.contains(event.target) && !mobileMenuToggle.contains(event.target)) {
                    sidebar.classList.remove('mobile-open');
                    closeAllSubmenus();
                }
            }
        });
        
        // Function to close all submenus
        function closeAllSubmenus() {
            document.querySelectorAll('.nav-item.has-sub.open').forEach(function(item) {
                item.classList.remove('open');
            });
        }
        
        // Auto-collapse sidebar on mobile (initial load)
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            localStorage.setItem('sidebarCollapsed', 'true');
        }
        
        // SUBMENU FUNCTIONALITY (if you have submenus)
        document.querySelectorAll('.nav-item.has-sub .menu-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const parent = this.closest('.nav-item.has-sub');
                const wasOpen = parent.classList.contains('open');
                
                // Close all other submenus first
                closeAllSubmenus();
                
                // If it wasn't open, open it (toggle behavior)
                if (!wasOpen) {
                    parent.classList.add('open');
                }
                
                // For mobile, ensure sidebar stays open when interacting with submenu
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('mobile-open');
                }
            });
        });
    });
    </script>
</body>
</html>