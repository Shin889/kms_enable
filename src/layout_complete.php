<?php
// src/layout_complete.php - Complete sidebar layout
// This file should NOT be included directly - use the functions below

function render_sidebar_layout($current_user, $page_title = '', $include_breadcrumb = true, $content_html = '') {
    if (!$current_user || !is_array($current_user)) {
        header("Location: login.php");
        exit;
    }
    
    $user_name = htmlspecialchars(trim($current_user['firstName'] . ' ' . $current_user['lastName']));
    $user_role = htmlspecialchars(ucfirst($current_user['role']));
    
    // Get profile picture URL
    $profilePictureUrl = '';
    if ($current_user['role'] === 'applicant') {
        try {
            $stmt = db()->prepare("SELECT profile_picture FROM applicant_profiles WHERE applicant_uid = ?");
            $stmt->execute([$current_user['uid']]);
            $profileData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!empty($profileData['profile_picture'])) {
                $profilePicPath = $profileData['profile_picture'];
                
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $scriptPath = $_SERVER['SCRIPT_NAME'];
                $scriptDir = dirname($scriptPath);
                $baseDir = str_replace('/public', '', $scriptDir);
                $baseDir = rtrim($baseDir, '/');
                
                $profilePictureUrl = $protocol . '://' . $host . $baseDir . '/uploads/' . $profilePicPath;
                
                $fullPath = realpath(__DIR__ . '/../uploads/' . $profilePicPath);
                if ($fullPath && file_exists($fullPath)) {
                    $profilePictureUrl .= '?t=' . filemtime($fullPath);
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching profile picture: " . $e->getMessage());
        }
    }
    
    // Determine active page for sidebar highlighting
    $current_script = basename($_SERVER['PHP_SELF']);
    $active_page = '';
    
    // Map standalone pages to their parent dashboard pages for highlighting
    $page_mapping = [
        'application_view.php' => 'applications_review',
        'personal_data.php' => 'personal_data',
        'profile.php' => 'profile',
        'card.php' => 'applications_overview',
        'interview_schedule.php' => 'applications_review',
        'finalize_application.php' => 'applications_review',
        'process_application.php' => 'applications_review',
        'account_settings.php' => 'profile',
        'alt_manage.php' => 'vacancy_manage',
        'approvals.php' => 'approvals',
    ];
    
    if (isset($page_mapping[$current_script])) {
        $active_page = $page_mapping[$current_script];
    }
    
    // Define which sidebar links should be shown based on role
    $show_vacancies = true;
    $show_applications_overview = $current_user['role'] === 'president';
    $show_applications_admin = $current_user['role'] === 'admin';
    $show_messages = in_array($current_user['role'], ['president', 'admin']);
    $show_vacancy_manage = $current_user['role'] === 'admin';
    $show_my_applications = $current_user['role'] === 'applicant';
    $show_personal_data = $current_user['role'] === 'applicant';
    $show_approvals = $current_user['role'] === 'president';
    
    // Start output
    ob_start();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($page_title) ?> | KMS Enable Recruitment</title>
        <link rel="stylesheet" href="assets/utils/dashboard.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <?php
        $css_file = str_replace('.php', '.css', $current_script);
        $css_path = "assets/utils/{$css_file}";
        if (file_exists(__DIR__ . '/../public/' . $css_path)) {
            echo "<link rel=\"stylesheet\" href=\"{$css_path}\">";
        }
        ?>
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
                    <?php if (!empty($profilePictureUrl) && $current_user['role'] === 'applicant'): ?>
                        <div class="user-avatar profile-picture-avatar">
                            <img src="<?= htmlspecialchars($profilePictureUrl) ?>" 
                                alt="<?= htmlspecialchars($user_name) ?>"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        </div>
                    <?php else: ?>
                        <div class="user-avatar">
                            <?= strtoupper(substr($current_user['firstName'], 0, 1) . substr($current_user['lastName'], 0, 1)) ?>
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
                            <!-- Job Vacancies - Always visible -->
                            <a href="dashboard.php?page=vacancies" 
                            class="nav-link <?= ($active_page === 'vacancies' || $current_script === 'dashboard.php') ? 'active' : '' ?>">
                                <div class="nav-icon"><i class="fas fa-briefcase"></i></div>
                                <div class="nav-label">Job Vacancies</div>
                            </a>
                            
                            <?php if ($show_applications_overview): ?>
                                <a href="dashboard.php?page=applications_overview" 
                                class="nav-link <?= $active_page === 'applications_overview' ? 'active' : '' ?>">
                                    <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
                                    <div class="nav-label">Applications Overview</div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($show_applications_admin): ?>
                                <a href="dashboard.php?page=applications_admin" 
                                class="nav-link <?= $active_page === 'applications_admin' ? 'active' : '' ?>">
                                    <div class="nav-icon"><i class="fas fa-file-contract"></i></div>
                                    <div class="nav-label">Applications</div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($show_messages): ?>
                                <a href="dashboard.php?page=messages" 
                                class="nav-link <?= $active_page === 'messages' ? 'active' : '' ?>">
                                    <div class="nav-icon"><i class="fas fa-envelope"></i></div>
                                    <div class="nav-label">Messages</div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($show_vacancy_manage): ?>
                                <a href="dashboard.php?page=vacancy_manage" 
                                class="nav-link <?= $active_page === 'vacancy_manage' ? 'active' : '' ?>">
                                    <div class="nav-icon"><i class="fas fa-edit"></i></div>
                                    <div class="nav-label">Manage Vacancies</div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($show_my_applications): ?>
                                <a href="dashboard.php?page=my_applications" 
                                class="nav-link <?= $active_page === 'my_applications' ? 'active' : '' ?>">
                                    <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
                                    <div class="nav-label">My Applications</div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($show_personal_data): ?>
                                <a href="dashboard.php?page=personal_data" 
                                class="nav-link <?= $active_page === 'personal_data' ? 'active' : '' ?>">
                                    <div class="nav-icon"><i class="fas fa-user-tie"></i></div>
                                    <div class="nav-label">Personal Data</div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($show_approvals): ?>
                                <a href="dashboard.php?page=approvals" 
                                class="nav-link <?= $active_page === 'approvals' ? 'active' : '' ?>">
                                    <div class="nav-icon"><i class="fas fa-check-circle"></i></div>
                                    <div class="nav-label">Pending Approvals</div>
                                </a>
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

            <!-- Main Content -->
            <main class="tri-content">
                <?php if ($include_breadcrumb): ?>
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
                                <?php if (!empty($profilePictureUrl) && $current_user['role'] === 'applicant'): ?>
                                    <div class="user-avatar profile-picture-avatar" style="width: 32px; height: 32px; position: relative;">
                                        <img src="<?= htmlspecialchars($profilePictureUrl) ?>" 
                                            alt="<?= htmlspecialchars($user_name) ?>"
                                            style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    </div>
                                <?php else: ?>
                                    <div class="user-avatar" style="width: 32px; height: 32px; font-size: 14px;">
                                        <?= strtoupper(substr($current_user['firstName'], 0, 1) . substr($current_user['lastName'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="nav-label"><?= $user_name ?></div>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="content-wrapper">
                    <div class="page-content">
                        <?= $content_html ?>
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
                    });
                }
                
                // Close mobile menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (sidebar && mobileMenuToggle) {
                        if (!sidebar.contains(event.target) && !mobileMenuToggle.contains(event.target)) {
                            sidebar.classList.remove('mobile-open');
                        }
                    }
                });
                
                // Close mobile menu on resize to desktop
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 1024 && sidebar) {
                        sidebar.classList.remove('mobile-open');
                    }
                });
                
                // Auto-collapse sidebar on mobile
                if (window.innerWidth <= 768 && sidebar) {
                    sidebar.classList.add('collapsed');
                    localStorage.setItem('sidebarCollapsed', 'true');
                }
            });
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Helper function to render layout immediately
function render_layout($current_user, $page_title = '', $include_breadcrumb = true, $content_html = '') {
    echo render_sidebar_layout($current_user, $page_title, $include_breadcrumb, $content_html);
}
?>