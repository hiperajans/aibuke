/* Admin Panel - Mobile Sidebar Toggle */
(function() {
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('sidebarOverlay');

    if (!sidebar) return;

    function closeSidebar() {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    }

    // Overlay click to close
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Close on nav item click (mobile only)
    var navItems = sidebar.querySelectorAll('.nav-item');
    for (var i = 0; i < navItems.length; i++) {
        navItems[i].addEventListener('click', function() {
            if (window.innerWidth <= 768) closeSidebar();
        });
    }

    // Sidebar close button (X)
    var closeBtn = sidebar.querySelector('.sidebar-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            closeSidebar();
        });
    }

    // Swipe left to close sidebar
    var touchStartX = 0;
    sidebar.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });
    sidebar.addEventListener('touchend', function(e) {
        var diff = touchStartX - e.changedTouches[0].clientX;
        if (diff > 60) closeSidebar();
    }, { passive: true });
})();
