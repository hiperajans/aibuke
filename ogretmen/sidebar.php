<?php
/**
 * Aibuke - Öğretmen Sidebar (Paylaşımlı)
 * Mobilde drawer, masaüstünde fixed sidebar.
 * Kategoriler: Planlama, Evraklar, Notlar, Performans, Özel Eğitim, Diğer
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$sinifOgretmeni = $_SESSION['ogretmen_sinif_ogretmeni'] ?? null;

// Aktif sayfanın hangi section'da olduğunu bul (mobilde o section açık başlar)
$activeSection = '';
$sections = [
    'planlama' => ['panel.php','yillik_plan.php'],
    'evraklar' => ['zumre.php','kulup.php','veli_toplanti.php','maarif_rapor.php'],
    'notlar' => ['not_takip.php','not_ortalama.php','kazanim_degerlendirme.php','surec_degerlendirme.php'],
    'performans' => ['performans.php','proje.php','urun_dosyasi.php','ders_ici.php','kriterler.php'],
    'ozel_egitim' => ['bep.php','bep_kaba.php','iyep_turkce.php','iyep_matematik.php'],
    'diger' => ['rehberlik.php','nobet.php','egzersiz.php','okul_temelli.php','dosyalarim.php','aday.php'],
];
foreach ($sections as $sec => $pages) {
    if (in_array($currentPage, $pages)) { $activeSection = $sec; break; }
}
?>
<style>
/* ── Sidebar Design Tokens ──────────────────────────────── */
:root {
    --sb-bg:           #06061a;
    --sb-surface:      rgba(10,10,32,0.92);
    --sb-surface-hover:rgba(99,102,241,0.07);
    --sb-border:       rgba(99,102,241,0.09);
    --sb-border-hover: rgba(99,102,241,0.2);
    --sb-text:         #e9eaf6;
    --sb-muted:        #6b7a99;
    --sb-primary:      #6366f1;
    --sb-accent:       #818cf8;
    --sb-gradient:     linear-gradient(135deg,#6366f1 0%,#8b5cf6 60%,#a78bfa 100%);
    --sb-warning:      #fbbf24;
    --sb-error:        #f87171;
    --sb-success:      #34d399;
}

/* ── Sidebar Shell ──────────────────────────────────────── */
.sidebar {
    position: fixed; top: 0; left: 0;
    width: 260px; height: 100vh;
    background: var(--sb-surface);
    border-right: 1px solid var(--sb-border);
    display: flex; flex-direction: column;
    z-index: 50;
    overflow-y: auto; overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: rgba(99,102,241,0.1) transparent;
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
}
.sidebar::-webkit-scrollbar { width: 3px; }
.sidebar::-webkit-scrollbar-track { background: transparent; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.1); border-radius: 2px; }

/* Aurora top glow */
.sidebar::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 180px;
    background: radial-gradient(ellipse at 50% -30%, rgba(99,102,241,0.1) 0%, transparent 70%);
    pointer-events: none; z-index: 0;
}

/* ── Brand ──────────────────────────────────────────────── */
.sidebar-brand {
    padding: 22px 18px 18px;
    background: linear-gradient(180deg, rgba(99,102,241,0.07) 0%, transparent 100%);
    border-bottom: 1px solid var(--sb-border);
    flex-shrink: 0;
    position: relative; z-index: 1;
}
.sidebar-brand-icon {
    width: 42px; height: 42px; border-radius: 13px;
    background: var(--sb-gradient);
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; color: #fff; margin-bottom: 13px;
    box-shadow: 0 6px 20px rgba(99,102,241,0.4);
}
.sidebar-brand h2 {
    font-size: 14px; font-weight: 700; color: var(--sb-text);
    margin-bottom: 2px; letter-spacing: -0.3px;
}
.sidebar-brand-name { font-size: 11.5px; color: var(--sb-muted); font-weight: 500; }
.sidebar-brand-ders {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 10px; font-weight: 700; color: var(--sb-accent);
    background: rgba(129,140,248,0.1); border: 1px solid rgba(129,140,248,0.14);
    padding: 4px 11px; border-radius: 100px; margin-top: 9px;
    letter-spacing: 0.3px;
}

/* ── Navigation ─────────────────────────────────────────── */
.sidebar-nav { padding: 10px 8px; flex: 1; position: relative; z-index: 1; }

.nav-section { margin-bottom: 2px; }
.nav-section-toggle {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 12px; border-radius: 11px; cursor: pointer;
    background: none; border: none; width: 100%; text-align: left;
    font-family: inherit; transition: background 0.2s;
}
.nav-section-toggle:hover { background: var(--sb-surface-hover); }

.nav-section-label {
    font-size: 10px; font-weight: 700; color: var(--sb-muted);
    text-transform: uppercase; letter-spacing: 0.9px;
    display: flex; align-items: center; gap: 8px;
}
.nav-section-label i { font-size: 11px; color: var(--sb-accent); opacity: 0.6; }

.nav-section-arrow {
    font-size: 9px; color: var(--sb-muted);
    transition: transform 0.25s ease; opacity: 0.35;
}
.nav-section.open .nav-section-arrow { transform: rotate(180deg); opacity: 0.65; }
.nav-section.open > .nav-section-toggle { background: rgba(99,102,241,0.05); }

.nav-section-links {
    max-height: 0; overflow: hidden;
    transition: max-height 0.3s cubic-bezier(0.4,0,0.2,1);
}
.nav-section.open .nav-section-links { max-height: 600px; }

.nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 14px; border-radius: 11px;
    color: var(--sb-muted); text-decoration: none;
    font-size: 13px; font-weight: 500;
    transition: all 0.15s; margin-bottom: 1px;
}
.nav-item i { width: 17px; text-align: center; font-size: 13px; flex-shrink: 0; transition: color 0.15s; }
.nav-item:hover { color: var(--sb-text); background: var(--sb-surface-hover); }
.nav-item.active {
    color: #fff; font-weight: 600;
    background: var(--sb-gradient);
    box-shadow: 0 4px 16px rgba(99,102,241,0.35);
}
.nav-item.active i { color: #fff; }

/* ── Footer ─────────────────────────────────────────────── */
.sidebar-footer {
    padding: 14px 18px; border-top: 1px solid var(--sb-border);
    flex-shrink: 0; position: relative; z-index: 1;
}
.sidebar-footer-school {
    font-size: 10.5px; color: var(--sb-muted); margin-bottom: 11px;
    font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    display: flex; align-items: center; gap: 5px;
}
.sidebar-logout {
    display: flex; align-items: center; gap: 8px;
    color: var(--sb-error); text-decoration: none;
    font-size: 12.5px; font-weight: 600;
    padding: 8px 10px; border-radius: 10px;
    opacity: 0.55; transition: all 0.2s;
    margin: 0 -10px;
}
.sidebar-logout:hover { opacity: 1; background: rgba(248,113,113,0.08); }

/* ── Mobile Header ──────────────────────────────────────── */
.sb-mobile-header {
    display: none; position: fixed; top: 0; left: 0; right: 0; z-index: 60;
    background: rgba(6,6,26,0.97);
    backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
    border-bottom: 1px solid var(--sb-border);
    height: 64px; padding: 0 18px;
    align-items: center; justify-content: space-between;
}
.sb-mobile-brand { display: flex; align-items: center; gap: 12px; }
.sb-mobile-icon {
    width: 34px; height: 34px; border-radius: 10px;
    background: var(--sb-gradient);
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; color: #fff;
    box-shadow: 0 4px 14px rgba(99,102,241,0.35);
}
.sb-mobile-title { font-size: 14px; font-weight: 700; color: var(--sb-text); }
.sb-hamburger {
    background: rgba(99,102,241,0.06);
    border: 1.5px solid var(--sb-border-hover);
    border-radius: 10px; color: var(--sb-text);
    font-size: 15px; cursor: pointer;
    padding: 8px 11px; transition: all 0.2s;
    line-height: 1;
}
.sb-hamburger:hover { background: rgba(99,102,241,0.12); }

.sb-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.65);
    z-index: 49; opacity: 0; transition: opacity 0.3s;
    backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
}
.sb-overlay.open { display: block; opacity: 1; }

/* ── Responsive ─────────────────────────────────────────── */
@media (max-width: 768px) {
    .sb-mobile-header { display: flex; }
    .sidebar {
        transform: translateX(-110%);
        transition: transform 0.38s cubic-bezier(0.16,1,0.3,1);
        width: 272px;
        box-shadow: 6px 0 40px rgba(0,0,0,0.6);
        top: 64px; height: calc(100vh - 64px);
    }
    .sidebar.open { transform: translateX(0); }
    .sidebar::before { display: none; }
    .nav-section-links { max-height: none !important; overflow: visible !important; }
    .nav-section-toggle { display: none; }
}
</style>

<!-- Mobile Header -->
<div class="sb-mobile-header">
    <div class="sb-mobile-brand">
        <div class="sb-mobile-icon"><i class="fas fa-chalkboard"></i></div>
        <span class="sb-mobile-title">Öğretmen Paneli</span>
    </div>
    <button class="sb-hamburger" onclick="toggleSidebar()" aria-label="Menü"><i class="fas fa-bars"></i></button>
</div>
<div class="sb-overlay" id="sbOverlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<nav class="sidebar" id="ogretmenSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="fas fa-chalkboard"></i></div>
        <h2>Öğretmen Paneli</h2>
        <div class="sidebar-brand-name"><?= htmlspecialchars($ogretmen['name'] ?? '') ?></div>
        <?php if (!empty($ogretmen['ders'])): ?>
        <div class="sidebar-brand-ders"><i class="fas fa-book-open"></i> <?= htmlspecialchars($ogretmen['ders']) ?></div>
        <?php endif; ?>
    </div>

    <div class="sidebar-nav">

        <!-- PLANLAMA -->
        <div class="nav-section <?= (!$activeSection || $activeSection === 'planlama') ? 'open' : '' ?>">
            <button class="nav-section-toggle" onclick="toggleSection(this)">
                <span class="nav-section-label"><i class="fas fa-calendar-days"></i> Planlama</span>
                <i class="fas fa-chevron-down nav-section-arrow"></i>
            </button>
            <div class="nav-section-links">
                <a class="nav-item <?= $currentPage === 'panel.php' ? 'active' : '' ?>" href="/ogretmen/panel.php">
                    <i class="fas fa-home"></i> Ana Panel
                </a>
                <a class="nav-item <?= $currentPage === 'yillik_plan.php' ? 'active' : '' ?>" href="/ogretmen/yillik_plan.php">
                    <i class="fas fa-book-bookmark"></i> Yıllık Plan
                </a>
            </div>
        </div>

        <!-- EVRAKLAR -->
        <div class="nav-section <?= $activeSection === 'evraklar' ? 'open' : '' ?>">
            <button class="nav-section-toggle" onclick="toggleSection(this)">
                <span class="nav-section-label"><i class="fas fa-file-signature"></i> Evraklar</span>
                <i class="fas fa-chevron-down nav-section-arrow"></i>
            </button>
            <div class="nav-section-links">
                <a class="nav-item <?= $currentPage === 'zumre.php' ? 'active' : '' ?>" href="/ogretmen/zumre.php">
                    <i class="fas fa-users"></i> Zümre
                </a>
                <a class="nav-item <?= $currentPage === 'kulup.php' ? 'active' : '' ?>" href="/ogretmen/kulup.php">
                    <i class="fas fa-person-standing"></i> Kulüp
                </a>
                <a class="nav-item <?= $currentPage === 'veli_toplanti.php' ? 'active' : '' ?>" href="/ogretmen/veli_toplanti.php">
                    <i class="fas fa-phone"></i> Veli Toplantı
                </a>
                <a class="nav-item <?= $currentPage === 'maarif_rapor.php' ? 'active' : '' ?>" href="/ogretmen/maarif_rapor.php">
                    <i class="fas fa-file-lines"></i> Maarif Rapor
                </a>
            </div>
        </div>

        <!-- NOT & DEĞERLENDİRME -->
        <div class="nav-section <?= $activeSection === 'notlar' ? 'open' : '' ?>">
            <button class="nav-section-toggle" onclick="toggleSection(this)">
                <span class="nav-section-label"><i class="fas fa-chart-column"></i> Not & Değerlendirme</span>
                <i class="fas fa-chevron-down nav-section-arrow"></i>
            </button>
            <div class="nav-section-links">
                <a class="nav-item <?= $currentPage === 'not_takip.php' ? 'active' : '' ?>" href="/ogretmen/not_takip.php">
                    <i class="fas fa-clipboard-check"></i> Not Takip
                </a>
                <a class="nav-item <?= $currentPage === 'not_ortalama.php' ? 'active' : '' ?>" href="/ogretmen/not_ortalama.php">
                    <i class="fas fa-calculator"></i> Not Ortalama
                </a>
                <a class="nav-item <?= $currentPage === 'kazanim_degerlendirme.php' ? 'active' : '' ?>" href="/ogretmen/kazanim_degerlendirme.php">
                    <i class="fas fa-bullseye"></i> Kazanım
                </a>
                <a class="nav-item <?= $currentPage === 'surec_degerlendirme.php' ? 'active' : '' ?>" href="/ogretmen/surec_degerlendirme.php">
                    <i class="fas fa-diagram-3"></i> Süreç
                </a>
            </div>
        </div>

        <!-- PERFORMANS & PROJE -->
        <div class="nav-section <?= $activeSection === 'performans' ? 'open' : '' ?>">
            <button class="nav-section-toggle" onclick="toggleSection(this)">
                <span class="nav-section-label"><i class="fas fa-trophy"></i> Performans & Proje</span>
                <i class="fas fa-chevron-down nav-section-arrow"></i>
            </button>
            <div class="nav-section-links">
                <a class="nav-item <?= $currentPage === 'performans.php' ? 'active' : '' ?>" href="/ogretmen/performans.php">
                    <i class="fas fa-star"></i> Performans
                </a>
                <a class="nav-item <?= $currentPage === 'proje.php' ? 'active' : '' ?>" href="/ogretmen/proje.php">
                    <i class="fas fa-folder"></i> Proje
                </a>
                <a class="nav-item <?= $currentPage === 'urun_dosyasi.php' ? 'active' : '' ?>" href="/ogretmen/urun_dosyasi.php">
                    <i class="fas fa-file-earmark"></i> Ürün Dosyası
                </a>
                <a class="nav-item <?= $currentPage === 'ders_ici.php' ? 'active' : '' ?>" href="/ogretmen/ders_ici.php">
                    <i class="fas fa-book"></i> Ders İçi
                </a>
                <a class="nav-item <?= $currentPage === 'kriterler.php' ? 'active' : '' ?>" href="/ogretmen/kriterler.php">
                    <i class="fas fa-list-check"></i> Kriterler
                </a>
            </div>
        </div>

        <!-- ÖZEL EĞİTİM -->
        <div class="nav-section <?= $activeSection === 'ozel_egitim' ? 'open' : '' ?>">
            <button class="nav-section-toggle" onclick="toggleSection(this)">
                <span class="nav-section-label"><i class="fas fa-universal-access"></i> Özel Eğitim</span>
                <i class="fas fa-chevron-down nav-section-arrow"></i>
            </button>
            <div class="nav-section-links">
                <a class="nav-item <?= $currentPage === 'bep.php' ? 'active' : '' ?>" href="/ogretmen/bep.php">
                    <i class="fas fa-heart-pulse"></i> BEP
                </a>
                <a class="nav-item <?= $currentPage === 'bep_kaba.php' ? 'active' : '' ?>" href="/ogretmen/bep_kaba.php">
                    <i class="fas fa-file-earmark-text"></i> BEP Kaba
                </a>
                <a class="nav-item <?= $currentPage === 'iyep_turkce.php' ? 'active' : '' ?>" href="/ogretmen/iyep_turkce.php">
                    <i class="fas fa-language"></i> IYEP Türkçe
                </a>
                <a class="nav-item <?= $currentPage === 'iyep_matematik.php' ? 'active' : '' ?>" href="/ogretmen/iyep_matematik.php">
                    <i class="fas fa-square-root-variable"></i> IYEP Matematik
                </a>
            </div>
        </div>

        <!-- REHBERLİK & DİĞER -->
        <div class="nav-section <?= $activeSection === 'diger' ? 'open' : '' ?>">
            <button class="nav-section-toggle" onclick="toggleSection(this)">
                <span class="nav-section-label"><i class="fas fa-ellipsis"></i> Rehberlik & Diğer</span>
                <i class="fas fa-chevron-down nav-section-arrow"></i>
            </button>
            <div class="nav-section-links">
                <a class="nav-item <?= $currentPage === 'rehberlik.php' ? 'active' : '' ?>" href="/ogretmen/rehberlik.php">
                    <i class="fas fa-hand-holding-heart"></i> Rehberlik
                </a>
                <a class="nav-item <?= $currentPage === 'nobet.php' ? 'active' : '' ?>" href="/ogretmen/nobet.php">
                    <i class="fas fa-calendar-week"></i> Nöbet
                </a>
                <a class="nav-item <?= $currentPage === 'egzersiz.php' ? 'active' : '' ?>" href="/ogretmen/egzersiz.php">
                    <i class="fas fa-person-walking"></i> Egzersiz
                </a>
                <a class="nav-item <?= $currentPage === 'okul_temelli.php' ? 'active' : '' ?>" href="/ogretmen/okul_temelli.php">
                    <i class="fas fa-building"></i> Okul Temelli
                </a>
                <a class="nav-item <?= $currentPage === 'dosyalarim.php' ? 'active' : '' ?>" href="/ogretmen/dosyalarim.php">
                    <i class="fas fa-folder-open"></i> Dosyalarım
                </a>
                <a class="nav-item <?= $currentPage === 'aday.php' ? 'active' : '' ?>" href="/ogretmen/aday.php">
                    <i class="fas fa-id-badge"></i> Aday
                </a>
            </div>
        </div>

    </div>

    <?php if (isset($ogretmen['school'])): ?>
    <div class="sidebar-footer">
        <div class="sidebar-footer-school"><i class="fas fa-school" style="margin-right:4px;"></i> <?= htmlspecialchars($ogretmen['school']) ?></div>
        <a href="/ogretmen/logout.php" class="sidebar-logout"><i class="fas fa-right-from-bracket"></i> Çıkış Yap</a>
    </div>
    <?php endif; ?>
</nav>

<script>
function toggleSidebar() {
    document.getElementById('ogretmenSidebar').classList.toggle('open');
    document.getElementById('sbOverlay').classList.toggle('open');
}
function toggleSection(btn) {
    btn.closest('.nav-section').classList.toggle('open');
}
// Close sidebar on nav click (mobile)
document.querySelectorAll('.sidebar .nav-item').forEach(function(item) {
    item.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            document.getElementById('ogretmenSidebar').classList.remove('open');
            document.getElementById('sbOverlay').classList.remove('open');
        }
    });
});
</script>