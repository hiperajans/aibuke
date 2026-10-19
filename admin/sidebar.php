<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
:root {
    --sb-bg: #0f0f23;
    --sb-surface: #1a1a2e;
    --sb-border: #2d2d44;
    --sb-text: #e2e8f0;
    --sb-muted: #94a3b8;
    --sb-primary: #6366f1;
    --sb-accent: #ec4899;
    --sb-success: #22c55e;
    --sb-error: #ef4444;
    --sb-warning: #f59e0b;
}

/* Desktop Sidebar */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    background: var(--sb-surface);
    border-right: 1px solid var(--sb-border);
    display: flex;
    flex-direction: column;
    z-index: 50;
    overflow: hidden;
}

.sidebar-brand {
    padding: 24px 20px 20px;
    border-bottom: 1px solid var(--sb-border);
    display: flex;
    align-items: center;
    gap: 12px;
}
.sidebar-brand img {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: 2px solid rgba(99,102,241,0.3);
    flex-shrink: 0;
}
.sidebar-brand-text { flex: 1; min-width: 0; }
.sidebar-brand h2 { font-size: 18px; font-weight: 700; margin-bottom: 2px; }
.sidebar-brand small { color: var(--sb-muted); font-size: 12px; }

.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 12px 0;
    scrollbar-width: thin;
    scrollbar-color: rgba(99,102,241,0.3) transparent;
}
.sidebar-nav::-webkit-scrollbar {
    width: 4px;
}
.sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(99,102,241,0.3);
    border-radius: 4px;
}
.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(99,102,241,0.5);
}

.nav-section { margin-bottom: 8px; }
.nav-section-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: var(--sb-muted);
    padding: 12px 20px 6px;
    opacity: 0.6;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 20px;
    color: var(--sb-muted);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}
.nav-item:hover { color: var(--sb-text); background: rgba(99,102,241,0.08); }
.nav-item.active { color: var(--sb-primary); background: rgba(99,102,241,0.08); }
.nav-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 60%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 0 3px 3px 0;
}
.nav-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}
.nav-item:hover .nav-icon { background: rgba(99,102,241,0.15); color: var(--sb-primary); }
.nav-item.active .nav-icon { background: rgba(99,102,241,0.2); color: var(--sb-primary); }

.nav-badge {
    margin-left: auto;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: rgba(239,68,68,0.15);
    color: var(--sb-error);
}

.sidebar-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--sb-border);
}
.sidebar-footer-info {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}
.sidebar-footer-info img { width: 32px; height: 32px; border-radius: 50%; border: 2px solid rgba(99,102,241,0.3); }
.sidebar-footer-info strong { color: var(--sb-text); font-size: 13px; font-weight: 600; }
.sidebar-footer-info small { color: var(--sb-muted); font-size: 12px; }
.sidebar-logout {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 10px;
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.15);
    border-radius: 10px;
    color: var(--sb-error);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}
.sidebar-logout:hover { background: rgba(239,68,68,0.18); }

/* Mobile Topbar */
.mobile-topbar {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    z-index: 100;
    background: rgba(26,26,46,0.95);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--sb-border);
    padding: 0 16px;
    align-items: center;
    gap: 12px;
}
.topbar-hamburger {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(99,102,241,0.08);
    border: 1px solid rgba(99,102,241,0.15);
    color: var(--sb-primary);
    font-size: 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.topbar-brand { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.topbar-brand img { width: 32px; height: 32px; border-radius: 50%; border: 2px solid rgba(99,102,241,0.3); }
.topbar-brand strong { font-size: 15px; font-weight: 700; color: var(--sb-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.topbar-brand small { font-size: 11px; color: var(--sb-muted); display: block; }

/* Overlay */
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 49;
    backdrop-filter: blur(2px);
}

/* Bottom Tab Bar */
.bottom-tab-bar {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 68px;
    z-index: 100;
    background: rgba(26,26,46,0.98);
    backdrop-filter: blur(20px);
    border-top: 1px solid var(--sb-border);
    padding: 0 8px;
    padding-bottom: env(safe-area-inset-bottom, 0);
}
.bottom-tab-bar .tabs { display: flex; height: 100%; align-items: center; justify-content: space-around; }
.bottom-tab-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 8px 12px;
    color: var(--sb-muted);
    text-decoration: none;
    font-size: 10px;
    font-weight: 600;
    border-radius: 12px;
    flex: 1;
    max-width: 80px;
}
.bottom-tab-item i { font-size: 18px; }
.bottom-tab-item.active { color: var(--sb-primary); }
.bottom-tab-item.active i { background: rgba(99,102,241,0.2); border-radius: 10px; padding: 6px; margin: -6px; }

/* Responsive */
@media (max-width: 768px) {
    .mobile-topbar { display: flex; }
    .bottom-tab-bar { display: block; }

    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        top: 0;
        height: 100vh;
        width: 280px;
        z-index: 200;
        box-shadow: 4px 0 24px rgba(0,0,0,0.4);
        overflow-y: auto;
    }
    .sidebar.open { transform: translateX(0); }
    .sidebar-overlay.open { display: block; }

    .sidebar-close {
        display: flex;
        align-items: center;
        justify-content: center;
        position: absolute;
        top: 14px;
        right: 14px;
        width: 36px;
        height: 36px;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 10px;
        color: var(--sb-muted);
        font-size: 16px;
        cursor: pointer;
    }

    .nav-item { padding: 13px 20px; font-size: 15px; }
    .sidebar-footer { padding: 14px 20px; }
}
</style>

<!-- Mobile Topbar -->
<div class="mobile-topbar">
    <button class="topbar-hamburger" id="topbarHamburger">
        <i class="fas fa-bars"></i>
    </button>
    <div class="topbar-brand">
        <img src="/assets/aibuke-avatar.png" alt="Aibüke">
        <div>
            <strong><?= htmlspecialchars($admin['school'] ?? 'Admin') ?></strong>
            <small>Aibüke Panel</small>
        </div>
    </div>
</div>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Desktop Sidebar -->
<nav class="sidebar" id="adminSidebar">
    <button class="sidebar-close" id="sidebarClose">
        <i class="fas fa-xmark"></i>
    </button>

    <div class="sidebar-brand">
        <img src="/assets/aibuke-avatar.png" alt="Aibüke">
        <div class="sidebar-brand-text">
            <h2>Aibüke</h2>
            <small><?= htmlspecialchars($admin['school'] ?? '') ?></small>
        </div>
    </div>

    <div class="sidebar-nav">
        <div class="nav-section">
            <a class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="/admin/dashboard.php">
                <span class="nav-icon"><i class="fas fa-chart-pie"></i></span>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-label">Yönetim</div>
            <a class="nav-item <?= $currentPage === 'students.php' ? 'active' : '' ?>" href="/admin/students.php">
                <span class="nav-icon"><i class="fas fa-users"></i></span>
                <span>Öğrenciler</span>
            </a>
            <a class="nav-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="/admin/reports.php">
                <span class="nav-icon"><i class="fas fa-file-lines"></i></span>
                <span>Raporlar</span>
            </a>
            <a class="nav-item <?= $currentPage === 'alerts.php' ? 'active' : '' ?>" href="/admin/alerts.php">
                <span class="nav-icon"><i class="fas fa-bell"></i></span>
                <span>Uyarılar</span>
                <?php if (!empty($redFlagCount) && $redFlagCount > 0): ?>
                    <span class="nav-badge"><?= (int)$redFlagCount ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-item <?= $currentPage === 'tournaments.php' ? 'active' : '' ?>" href="/admin/tournaments.php">
                <span class="nav-icon"><i class="fas fa-trophy"></i></span>
                <span>Turnuvalar</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-label">Eğitim İçerikleri</div>
            <?php if (!empty($_SESSION['super_admin_logged_in'])): ?>
            <a class="nav-item <?= $currentPage === 'question_pool.php' ? 'active' : '' ?>" href="/admin/question_pool.php">
                <span class="nav-icon"><i class="fas fa-database"></i></span>
                <span>Soru Havuzu</span>
            </a>
            <?php endif; ?>
            <a class="nav-item <?= $currentPage === 'islenen_konular.php' ? 'active' : '' ?>" href="/admin/islenen_konular.php">
                <span class="nav-icon"><i class="fas fa-chalkboard"></i></span>
                <span>İşlenen Konular</span>
            </a>
            <a class="nav-item <?= $currentPage === 'yillik_plan.php' ? 'active' : '' ?>" href="/admin/yillik_plan.php">
                <span class="nav-icon"><i class="fas fa-calendar-week"></i></span>
                <span>Yıllık Plan</span>
            </a>
            <a class="nav-item <?= $currentPage === 'takvim_yonetimi.php' ? 'active' : '' ?>" href="/admin/takvim_yonetimi.php">
                <span class="nav-icon"><i class="fas fa-calendar"></i></span>
                <span>Takvim</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-label">Ayarlar</div>
            <a class="nav-item <?= $currentPage === 'ogretmenler.php' ? 'active' : '' ?>" href="/admin/ogretmenler.php">
                <span class="nav-icon"><i class="fas fa-user-tie"></i></span>
                <span>Öğretmenler</span>
            </a>
            <a class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="/admin/settings.php">
                <span class="nav-icon"><i class="fas fa-gear"></i></span>
                <span>Ayarlar</span>
            </a>
        </div>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-footer-info">
            <img src="/assets/aibuke-avatar.png" alt="">
            <div>
                <strong><?= htmlspecialchars($admin['name'] ?? '') ?></strong>
                <small>Yönetici</small>
            </div>
        </div>
        <a href="/admin/logout.php" class="sidebar-logout">
            <i class="fas fa-right-from-bracket"></i> Çıkış Yap
        </a>
    </div>
</nav>

<!-- Bottom Tab Bar -->
<nav class="bottom-tab-bar">
    <div class="tabs">
        <a class="bottom-tab-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="/admin/dashboard.php">
            <i class="fas fa-chart-pie"></i>
            <span>Dashboard</span>
        </a>
        <a class="bottom-tab-item <?= $currentPage === 'students.php' ? 'active' : '' ?>" href="/admin/students.php">
            <i class="fas fa-users"></i>
            <span>Öğrenciler</span>
        </a>
        <a class="bottom-tab-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="/admin/reports.php">
            <i class="fas fa-file-lines"></i>
            <span>Raporlar</span>
        </a>
        <a class="bottom-tab-item <?= $currentPage === 'alerts.php' ? 'active' : '' ?>" href="/admin/alerts.php">
            <i class="fas fa-bell"></i>
            <span>Uyarılar</span>
        </a>
        <a class="bottom-tab-item" href="#" id="bottomMenuBtn">
            <i class="fas fa-bars"></i>
            <span>Menü</span>
        </a>
    </div>
</nav>

<link rel="stylesheet" href="/admin/assets/mobile.css?v=<?= filemtime(__DIR__.'/assets/mobile.css') ?>">

<script>
(function() {
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var hamburger = document.getElementById('topbarHamburger');
    var closeBtn = document.getElementById('sidebarClose');
    var bottomMenuBtn = document.getElementById('bottomMenuBtn');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('open');
    }
    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
    }
    function toggleSidebar() {
        if (!sidebar) return;
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    if (hamburger) hamburger.addEventListener('click', toggleSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (bottomMenuBtn) bottomMenuBtn.addEventListener('click', function(e) { e.preventDefault(); toggleSidebar(); });
    if (overlay) overlay.addEventListener('click', closeSidebar);

    var navItems = sidebar ? sidebar.querySelectorAll('.nav-item') : [];
    for (var i = 0; i < navItems.length; i++) {
        navItems[i].addEventListener('click', function() {
            if (window.innerWidth <= 768) closeSidebar();
        });
    }
})();
</script>
