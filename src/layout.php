<?php
// ... your existing code ...

// Close the layout manually since we can't call render_sidebar_footer()
?>
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