<?php
require_once __DIR__ . '/../src/init.php';
$u = current_user();

if (!$u) {
    header("Location: index.php");
    exit;
}

$allowed_pages = [
    'vacancies',
    'applications_overview',
    'applications_review',
    'applications_overview',
    'applications_admin',
   /*  'employee_tracking', */
    'messages',
    'vacancy_manage',
    'my_applications',
    'approvals',
    'personal_data',
    'personal_information', 
    'family_background', 
    'educational_background',
    'civil_service_eligibility',
    'work_experience',  
    'voluntary_work',
    'learning_and_development',
    'other_information'
];

$page = $_GET['page'] ?? 'vacancies';
if (!in_array($page, $allowed_pages)) {
    $page = 'vacancies';
}

// Set page titles
$page_titles = [
    'vacancies' => 'Job Vacancies',
    'applications_overview' => 'Applications Overview',
    'applications_review' => 'Applications Review',
    'applications_admin' => 'Admin Applications',
    /* 'employee_tracking' => 'Employee Tracking', */
    'messages' => 'Messages',
    'vacancy_manage' => 'Manage Vacancies',
    'my_applications' => 'My Applications',
    'profile' => 'My Profile',
    'approvals' => 'Pending Approvals',
    'personal_data' => 'Personal Data',
    'personal_information' => 'Personal Information',
    'family_background' => 'Family Background',
    'educational_background' => 'Educational Background',
    'civil_service_eligibility' => 'Civil Service Eligibility',
    'work_experience' => 'Work Experience',
    'voluntary_work' => 'Voluntary Work',
    'learning_and_development' => 'Learning and Development',
    'other_information' => 'Other Information'
];

$page_title = $page_titles[$page] ?? 'Dashboard';
$user_name = htmlspecialchars(trim($u['firstName'] . ' ' . $u['lastName']));
$user_role = htmlspecialchars(ucfirst($u['role']));

$profilePictureUrl = '';
if ($u['role'] === 'applicant') {
    try {
        $stmt = db()->prepare("SELECT profile_picture FROM applicant_profiles WHERE applicant_uid = ?");
        $stmt->execute([$u['uid']]);
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
        // Silently fail - use default avatar
        error_log("Error fetching profile picture: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?> | KMS Enable Recruitment</title>
  <link rel="stylesheet" href="assets/utils/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <div class="dashboard-layout">
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php?page=vacancies" class="sidebar-logo">
                <div class="logo-text">KMS Enable</div>
            </a>
        </div>

        <div class="user-info">
    <?php if (!empty($profilePictureUrl) && $u['role'] === 'applicant'): ?>
        <div class="user-avatar profile-picture-avatar">
            <img src="<?= htmlspecialchars($profilePictureUrl) ?>" 
                 alt="<?= htmlspecialchars($user_name) ?>"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <!-- <div class="avatar-fallback">
                <?= strtoupper(substr($u['firstName'], 0, 1) . substr($u['lastName'], 0, 1)) ?>
            </div> -->
        </div>
    <?php else: ?>
        <div class="user-avatar">
            <?= strtoupper(substr($u['firstName'], 0, 1) . substr($u['lastName'], 0, 1)) ?>
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
                       class="nav-link <?= $page === 'vacancies' ? 'active' : '' ?>">
                        <div class="nav-icon"><i class="fas fa-briefcase"></i></div>
                        <div class="nav-label">Job Vacancies</div>
                    </a>
                    
                    <?php if ($u['role'] === 'president'): ?>
                        <a href="dashboard.php?page=applications_overview" 
                           class="nav-link <?= $page === 'applications_overview' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
                            <div class="nav-label">Applications Overview</div>
                        </a>
                       <!--  <a href="dashboard.php?page=employee_tracking" 
                           class="nav-link <?= $page === 'employee_tracking' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-users"></i></div>
                            <div class="nav-label">Employee Tracking</div>
                        </a> -->
                        <a href="dashboard.php?page=messages" 
                           class="nav-link <?= $page === 'messages' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-envelope"></i></div>
                            <div class="nav-label">Messages</div>
                        </a>
                    
                    <?php elseif ($u['role'] === 'admin'): ?>
                        <a href="dashboard.php?page=applications_admin" 
                           class="nav-link <?= $page === 'applications_admin' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-file-contract"></i></div>
                            <div class="nav-label">Applications</div>
                        </a>
                     <!--    <a href="dashboard.php?page=employee_tracking" 
                           class="nav-link <?= $page === 'employee_tracking' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-users"></i></div>
                            <div class="nav-label">Employee Tracking</div>
                        </a> -->
                        <a href="dashboard.php?page=messages" 
                           class="nav-link <?= $page === 'messages' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-envelope"></i></div>
                            <div class="nav-label">Messages</div>
                        </a>
                        <a href="dashboard.php?page=vacancy_manage" 
                           class="nav-link <?= $page === 'vacancy_manage' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-edit"></i></div>
                            <div class="nav-label">Manage Vacancies</div>
                        </a>
                    
                    <?php else: // For applicants ?>
                        <a href="dashboard.php?page=my_applications" 
                           class="nav-link <?= $page === 'my_applications' ? 'active' : '' ?>">
                            <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
                            <div class="nav-label">My Applications</div>
                        </a>
                        <!-- <a href="dashboard.php?page=personal_data" 
                            class="nav-link <?= $page === 'personal_data' ? 'active' : '' ?>">
                                <div class="nav-icon"><i class="fas fa-user-tie"></i></div>
                                <div class="nav-label">Personal Data</div>
                            </a> -->
                        <div class="nav-item has-sub <?= in_array($page, ['personal_data', 'personal_information', 'family_background', 'educational_background', 'civil_service_eligibility', 'work_experience', 'voluntary_work', 'learning_and_development', 'other_information']) ? 'active open' : '' ?>">
                    <a href="javascript:;" class="nav-link menu-toggle">
                        <div class="nav-icon"><i class="fas fa-user-tie"></i></div>
                        <div class="nav-label">Personal Data</div>
                        <div class="menu-caret">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </a>
                    <div class="submenu">
                        <a href="dashboard.php?page=personal_information" 
                           class="submenu-link <?= $page === 'personal_information' ? 'active' : '' ?>">
                            <div class="submenu-icon"><i class="fas fa-user"></i></div>
                            <div class="submenu-label">Personal Information</div>
                        </a>
                        <a href="dashboard.php?page=family_background" 
                           class="submenu-link <?= $page === 'family_background' ? 'active' : '' ?>">
                            <div class="submenu-icon"><i class="fas fa-user-friends"></i></div>
                            <div class="submenu-label">Family Background</div>
                        </a>
                        <a href="dashboard.php?page=educational_background" 
                           class="submenu-link <?= $page === 'educational_background' ? 'active' : '' ?>">
                            <div class="submenu-icon"><i class="fas fa-school"></i></div>
                            <div class="submenu-label">Educational Background</div>
                        </a>
                        <a href="dashboard.php?page=civil_service_eligibility" 
                           class="submenu-link <?= $page === 'civil_service_eligibility' ? 'active' : '' ?>">
                            <div class="submenu-icon"><i class="fas fa-id-card"></i></div>
                            <div class="submenu-label">Civil Service Eligibility</div>
                        </a>
                        <a href="dashboard.php?page=work_experience" 
                           class="submenu-link <?= $page === 'work_experience' ? 'active' : '' ?>">
                            <div class="submenu-icon"><i class="fas fa-briefcase"></i></div>
                            <div class="submenu-label">Work Experience</div>
                        </a>
                        <a href="dashboard.php?page=voluntary_work" 
                           class="submenu-link <?= $page === 'voluntary_work' ? 'active' : '' ?>">
                            <div class="submenu-icon"><i class="fas fa-hand-holding-heart"></i></div>
                            <div class="submenu-label">Voluntary Work</div>
                        </a>
                        <a href="dashboard.php?page=learning_and_development" 
                           class="submenu-link <?= $page === 'learning_and_development' ? 'active' : '' ?>">
                            <div class="submenu-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                            <div class="submenu-label">Learning & Development</div>
                        </a>
                        <a href="dashboard.php?page=other_information" 
                           class="submenu-link <?= $page === 'other_information' ? 'active' : '' ?>">
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
            <!-- LOGOUT BUTTON - ALWAYS VISIBLE -->
            <a href="logout.php" class="logout-link">
                <div class="logout-icon"><i class="fas fa-sign-out-alt"></i></div>
                <div class="logout-label">Logout</div>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="tri-content">
        <div class="topbar">
            <div class="page-info">
                <h1 class="page-title"><?= $page_title ?></h1>
                <div class="breadcrumb">
                    <a href="dashboard.php?page=vacancies">Dashboard</a>
                    <span class="separator">/</span>
                    <span><?= $page_title ?></span>
                </div>
            </div>
            
            <div class="topbar-actions">
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
               <div class="user-menu">
    <a href="profile.php" class="nav-link" style="padding: 8px 12px; background: var(--bg-2); border-radius: 8px;">
        <?php if (!empty($profilePictureUrl) && $u['role'] === 'applicant'): ?>
            <div class="user-avatar profile-picture-avatar" style="width: 32px; height: 32px; position: relative;">
                <img src="<?= htmlspecialchars($profilePictureUrl) ?>" 
                     alt="<?= htmlspecialchars($user_name) ?>"
                     style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <!-- <div class="avatar-fallback" style="display: none; width: 100%; height: 100%; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                    <?= strtoupper(substr($u['firstName'], 0, 1) . substr($u['lastName'], 0, 1)) ?>
                </div> -->
            </div>
        <?php else: ?>
            <div class="user-avatar" style="width: 32px; height: 32px; font-size: 14px;">
                <?= strtoupper(substr($u['firstName'], 0, 1) . substr($u['lastName'], 0, 1)) ?>
            </div>
        <?php endif; ?>
        <div class="nav-label"><?= $user_name ?></div>
    </a>
</div>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="page-content">
                <?php 
                // Always show content for applicants
                $page_file = __DIR__ . "/{$page}.php";
                if (file_exists($page_file)) {
                    include $page_file;
                } else {
                    // Fallback for applicants if page doesn't exist
                    if ($u['role'] === 'applicant') {
                        if ($page === 'vacancies') {
                            // Include the vacancies.php file
                            include __DIR__ . '/vacancies.php';
                        } elseif ($page === 'my_applications') {
                            echo '<div class="alert alert-info">My Applications page will be available soon.</div>';
                        } elseif ($page === 'profile') {
                            echo '<div class="alert alert-info">Profile page will be available soon.</div>';
                        }
                    } else {
                        echo '<div class="alert alert-error">Page not found: ' . htmlspecialchars($page) . '</div>';
                    }
                }
                ?>
            </div>
        </div>
    </main>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    
    // Check for saved sidebar state
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
        sidebarToggle.querySelector('i').classList.remove('fa-chevron-left');
        sidebarToggle.querySelector('i').classList.add('fa-chevron-right');
    }
    
    // Toggle sidebar on desktop
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
    
    // Toggle mobile menu
    mobileMenuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('mobile-open');
        
        // Close all submenus when closing mobile menu
        if (!sidebar.classList.contains('mobile-open')) {
            closeAllSubmenus();
        }
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (!sidebar.contains(event.target) && !mobileMenuToggle.contains(event.target)) {
            sidebar.classList.remove('mobile-open');
            closeAllSubmenus();
        }
    });
    
    // Close mobile menu on resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            sidebar.classList.remove('mobile-open');
            closeAllSubmenus();
        }
        
        // Auto-collapse sidebar on mobile
        if (window.innerWidth <= 768) {
            if (!sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('collapsed');
                sidebarToggle.querySelector('i').classList.remove('fa-chevron-left');
                sidebarToggle.querySelector('i').classList.add('fa-chevron-right');
                localStorage.setItem('sidebarCollapsed', 'true');
            }
            closeAllSubmenus();
        } else {
            // On desktop, only collapse if previously saved as collapsed
            if (localStorage.getItem('sidebarCollapsed') !== 'true') {
                sidebar.classList.remove('collapsed');
                sidebarToggle.querySelector('i').classList.remove('fa-chevron-right');
                sidebarToggle.querySelector('i').classList.add('fa-chevron-left');
            }
        }
    });
    
    // Auto-collapse sidebar on mobile (initial load)
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        localStorage.setItem('sidebarCollapsed', 'true');
    }
    
    // Fix for applicant pages
    const pageContent = document.querySelector('.page-content');
    if (pageContent && pageContent.innerHTML.trim() === '') {
        // If content is empty, show a message
        pageContent.innerHTML = `
            <div class="alert alert-info">
                <h3>Welcome, <?= $user_name ?>!</h3>
                <p>Please select a page from the sidebar to get started.</p>
            </div>
        `;
    }
    
    // SUBMENU FUNCTIONALITY
    
    // Toggle submenus
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
    
    // Close submenus when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            if (!event.target.closest('.nav-item.has-sub')) {
                closeAllSubmenus();
            }
        } else {
            // On desktop, close submenus when clicking outside, unless sidebar is collapsed
            if (!sidebar.classList.contains('collapsed') && 
                !event.target.closest('.nav-item.has-sub')) {
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
    
    // Auto-open parent menu if child is active
    document.querySelectorAll('.submenu-link.active').forEach(function(activeLink) {
        const parentItem = activeLink.closest('.nav-item.has-sub');
        if (parentItem && !parentItem.classList.contains('open')) {
            parentItem.classList.add('open');
        }
    });
    
    // Prevent submenu links from closing sidebar on mobile
    document.querySelectorAll('.submenu-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                // Keep sidebar open on mobile for better UX
                // sidebar.classList.remove('mobile-open');
                closeAllSubmenus();
            }
        });
    });
});
</script>
</body>
</html>