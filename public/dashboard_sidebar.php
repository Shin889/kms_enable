<?php
function get_profile_picture_url($user) {
    $profilePictureUrl = '';
    if ($user['role'] === 'applicant') {
        try {
            $stmt = db()->prepare("SELECT profile_picture FROM applicant_profiles WHERE applicant_uid = ?");
            $stmt->execute([$user['uid']]);
            $profileData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!empty($profileData['profile_picture'])) {
                $profilePicPath = $profileData['profile_picture'];
                
                // Construct full URL for the profile picture
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $scriptPath = $_SERVER['SCRIPT_NAME'];
                $scriptDir = dirname($scriptPath);
                $baseDir = str_replace('/public', '', $scriptDir);
                $baseDir = rtrim($baseDir, '/');
                
                $profilePictureUrl = $protocol . '://' . $host . $baseDir . '/uploads/' . $profilePicPath;
                
                // Verify file exists
                $fullPath = realpath(__DIR__ . '/../uploads/' . $profilePicPath);
                if ($fullPath && file_exists($fullPath)) {
                    $profilePictureUrl .= '?t=' . filemtime($fullPath);
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching profile picture: " . $e->getMessage());
        }
    }
    return $profilePictureUrl;
}

function render_sidebar($user, $current_page = '') {
    $user_name = htmlspecialchars(trim($user['firstName'] . ' ' . $user['lastName']));
    $user_role = htmlspecialchars(ucfirst($user['role']));
    $profilePictureUrl = get_profile_picture_url($user);
    ?>
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php?page=vacancies" class="sidebar-logo">
                <div class="logo-text">KMS Enable</div>
            </a>
        </div>

        <div class="user-info">
            <?php if (!empty($profilePictureUrl) && $user['role'] === 'applicant'): ?>
                <div class="user-avatar profile-picture-avatar">
                    <img src="<?= htmlspecialchars($profilePictureUrl) ?>" 
                         alt="<?= htmlspecialchars($user_name) ?>"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <!-- <div class="avatar-fallback">
                        <?= strtoupper(substr($user['firstName'], 0, 1) . substr($user['lastName'], 0, 1)) ?>
                    </div> -->
                </div>
            <?php else: ?>
                <div class="user-avatar">
                    <?= strtoupper(substr($user['firstName'], 0, 1) . substr($user['lastName'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="user-details">
                <div class="user-name"><?= $user_name ?></div>
                <div class="user-role"><?= $user_role ?></div>
            </div>
        </div>

        <div class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-title">Main Navigation</div>
                <div class="nav-links">
                    <a href="dashboard.php?page=vacancies" 
                       class="nav-link <?= $current_page === 'vacancies' ? 'active' : '' ?>">
                        <div class="nav-icon"><i class="fas fa-briefcase"></i></div>
                        <div class="nav-label">Job Vacancies</div>
                    </a>
                    
                    <?php if ($user['role'] === 'president'): ?>
                        <a href="dashboard.php?page=applications_overview" 
                           class="nav-link <?= $current_page === 'applications_overview' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
                            <div class="nav-label">Applications Overview</div>
                        </a>
                        <a href="dashboard.php?page=messages" 
                           class="nav-link <?= $current_page === 'messages' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-envelope"></i></div>
                            <div class="nav-label">Messages</div>
                        </a>
                    
                    <?php elseif ($user['role'] === 'admin'): ?>
                        <a href="dashboard.php?page=applications_admin" 
                           class="nav-link <?= $current_page === 'applications_admin' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-file-contract"></i></div>
                            <div class="nav-label">Applications</div>
                        </a>
                        <a href="dashboard.php?page=messages" 
                           class="nav-link <?= $current_page === 'messages' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-envelope"></i></div>
                            <div class="nav-label">Messages</div>
                        </a>
                        <a href="dashboard.php?page=vacancy_manage" 
                           class="nav-link <?= $current_page === 'vacancy_manage' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-edit"></i></div>
                            <div class="nav-label">Manage Vacancies</div>
                        </a>
                    
                    <?php else: // For applicants ?>
                        <a href="dashboard.php?page=my_applications" 
                           class="nav-link <?= $current_page === 'my_applications' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
                            <div class="nav-label">My Applications</div>
                        </a>
                        
                        <div class="nav-item has-sub <?= in_array($current_page, ['personal_data', 'personal_information', 'family_background', 'educational_background', 'civil_service_eligibility', 'work_experience', 'voluntary_work', 'learning_and_development', 'other_information']) ? 'active open' : '' ?>">
                            <a href="javascript:;" class="nav-link menu-toggle">
                                <div class="nav-icon"><i class="fas fa-user-tie"></i></div>
                                <div class="nav-label">Personal Data</div>
                                <div class="menu-caret">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </a>
                            <div class="submenu">
                                <a href="dashboard.php?page=personal_information" 
                                   class="submenu-link <?= $current_page === 'personal_information' ? 'active' : '' ?>">
                                    <div class="submenu-icon"><i class="fas fa-user"></i></div>
                                    <div class="submenu-label">Personal Information</div>
                                </a>
                                <a href="dashboard.php?page=family_background" 
                                   class="submenu-link <?= $current_page === 'family_background' ? 'active' : '' ?>">
                                    <div class="submenu-icon"><i class="fas fa-user-friends"></i></div>
                                    <div class="submenu-label">Family Background</div>
                                </a>
                                <a href="dashboard.php?page=educational_background" 
                                   class="submenu-link <?= $current_page === 'educational_background' ? 'active' : '' ?>">
                                    <div class="submenu-icon"><i class="fas fa-school"></i></div>
                                    <div class="submenu-label">Educational Background</div>
                                </a>
                                <a href="dashboard.php?page=civil_service_eligibility" 
                                   class="submenu-link <?= $current_page === 'civil_service_eligibility' ? 'active' : '' ?>">
                                    <div class="submenu-icon"><i class="fas fa-id-card"></i></div>
                                    <div class="submenu-label">Civil Service Eligibility</div>
                                </a>
                                <a href="dashboard.php?page=work_experience" 
                                   class="submenu-link <?= $current_page === 'work_experience' ? 'active' : '' ?>">
                                    <div class="submenu-icon"><i class="fas fa-briefcase"></i></div>
                                    <div class="submenu-label">Work Experience</div>
                                </a>
                                <a href="dashboard.php?page=voluntary_work" 
                                   class="submenu-link <?= $current_page === 'voluntary_work' ? 'active' : '' ?>">
                                    <div class="submenu-icon"><i class="fas fa-hand-holding-heart"></i></div>
                                    <div class="submenu-label">Voluntary Work</div>
                                </a>
                                <a href="dashboard.php?page=learning_and_development" 
                                   class="submenu-link <?= $current_page === 'learning_and_development' ? 'active' : '' ?>">
                                    <div class="submenu-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                                    <div class="submenu-label">Learning & Development</div>
                                </a>
                                <a href="dashboard.php?page=other_information" 
                                   class="submenu-link <?= $current_page === 'other_information' ? 'active' : '' ?>">
                                    <div class="submenu-icon"><i class="fas fa-info-circle"></i></div>
                                    <div class="submenu-label">Other Information</div>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="sidebar-footer">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-chevron-left"></i>
            </button>
            <a href="logout.php" class="logout-link">
                <div class="logout-icon"><i class="fas fa-sign-out-alt"></i></div>
                <div class="logout-label">Logout</div>
            </a>
        </div>
    </nav>
    <?php
}

function render_topbar($user, $page_title = '') {
    $user_name = htmlspecialchars(trim($user['firstName'] . ' ' . $user['lastName']));
    $profilePictureUrl = get_profile_picture_url($user);
    ?>
    <div class="topbar">
        <div class="page-info">
            <h1 class="page-title"><?= htmlspecialchars($page_title) ?></h1>
            <div class="breadcrumb">
                <a href="dashboard.php?page=vacancies">Dashboard</a>
                <span class="separator">/</span>
                <span><?= htmlspecialchars($page_title) ?></span>
            </div>
        </div>
        
        <div class="topbar-actions">
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="user-menu">
                <a href="profile.php" class="nav-link" style="padding: 8px 12px; background: var(--bg-2); border-radius: 8px;">
                    <?php if (!empty($profilePictureUrl) && $user['role'] === 'applicant'): ?>
                        <div class="user-avatar profile-picture-avatar" style="width: 32px; height: 32px; position: relative;">
                            <img src="<?= htmlspecialchars($profilePictureUrl) ?>" 
                                 alt="<?= htmlspecialchars($user_name) ?>"
                                 style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        </div>
                    <?php else: ?>
                        <div class="user-avatar" style="width: 32px; height: 32px; font-size: 14px;">
                            <?= strtoupper(substr($user['firstName'], 0, 1) . substr($user['lastName'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="nav-label"><?= $user_name ?></div>
                </a>
            </div>
        </div>
    </div>
    <?php
}
?>