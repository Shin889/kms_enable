<?php
// dashboard.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/dashboard_sidebar.php'; // Include the reusable functions

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
    'messages' => 'Messages',
    'vacancy_manage' => 'Manage Vacancies',
    'my_applications' => 'My Applications',
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
    <?php render_sidebar($u, $page); ?>

    <!-- Main Content -->
    <main class="tri-content">
        <?php render_topbar($u, $page_title); ?>

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