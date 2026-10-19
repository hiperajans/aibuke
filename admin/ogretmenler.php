<?php
/**
 * Aibuke Admin - Öğretmen Yönetimi
 */
require_once __DIR__ . '/auth.php';
session_start();
requireAdminLogin();
$admin    = getAdminInfo();
$schoolId = $admin['school_id'];

// Sidebar badge
$stmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_sentiment_alerts sa JOIN students s ON sa.student_id = s.id WHERE s.school_id = ? AND sa.is_resolved = 0");
$stmt->execute([$schoolId]);
$redFlagCount = (int) $stmt->fetchColumn();

$mesaj = '';
$hata  = '';

// ── Öğretmen ekle ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ekle') {
    $adSoyad  = trim($_POST['ad_soyad'] ?? '');
    $branchId = (int)($_POST['branch_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$adSoyad || !$branchId || !$username || strlen($password) < 6) {
        $hata = 'Tüm alanlar zorunludur. Şifre en az 6 karakter olmalıdır.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM staff WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            $hata = 'Bu kullanıcı adı zaten kullanımda.';
        } else {
            $bStmt = $pdo->prepare("SELECT branch_name FROM branches WHERE id = ? AND school_id = ?");
            $bStmt->execute([$branchId, $schoolId]);
            $branch = $bStmt->fetch();
            if (!$branch) {
                $hata = 'Geçersiz branş.';
            } else {
                $title = $branch['branch_name'] . ' Öğretmeni';
                $hash  = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO staff (school_id, full_name, title, role, branch_id, username, password) VALUES (?, ?, ?, 'teacher', ?, ?, ?)")
                    ->execute([$schoolId, $adSoyad, $title, $branchId, $username, $hash]);
                $mesaj = "\"$adSoyad\" başarıyla eklendi.";
            }
        }
    }
}

// ── Şifre güncelle ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sifre') {
    $staffId     = (int)($_POST['staff_id'] ?? 0);
    $newUsername = trim($_POST['new_username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    if (!$staffId || !$newUsername || strlen($newPassword) < 6) {
        $hata = 'Kullanıcı adı ve şifre (en az 6 karakter) zorunludur.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM staff WHERE username = ? AND id != ?");
        $chk->execute([$newUsername, $staffId]);
        if ($chk->fetch()) {
            $hata = 'Bu kullanıcı adı başka bir öğretmene ait.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE staff SET username = ?, password = ? WHERE id = ? AND school_id = ?")
                ->execute([$newUsername, $hash, $staffId, $schoolId]);
            $mesaj = 'Giriş bilgileri güncellendi.';
        }
    }
}

// ── Öğretmen sil ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sil') {
    $staffId = (int)($_POST['staff_id'] ?? 0);
    if ($staffId) {
        $pdo->prepare("DELETE FROM staff WHERE id = ? AND school_id = ? AND role = 'teacher'")
            ->execute([$staffId, $schoolId]);
        $mesaj = 'Öğretmen silindi.';
    }
}

// ── Sınıf öğretmeni ata ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sinif_ata') {
    $staffId = (int)($_POST['staff_id'] ?? 0);
    $sinif   = trim($_POST['sinif'] ?? '');
    if ($staffId) {
        $sinifVal = $sinif ?: null;
        if ($sinifVal) {
            $pdo->prepare("UPDATE staff SET sinif_ogretmeni = NULL WHERE school_id = ? AND sinif_ogretmeni = ? AND id != ?")
                ->execute([$schoolId, $sinifVal, $staffId]);
        }
        $pdo->prepare("UPDATE staff SET sinif_ogretmeni = ? WHERE id = ? AND school_id = ?")
            ->execute([$sinifVal, $staffId, $schoolId]);
        $mesaj = $sinifVal ? "Sınıf öğretmeni olarak atandı: $sinifVal" : 'Sınıf öğretmenliği kaldırıldı.';
    }
}

// ── Erişim kaldır ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'erisim_kaldir') {
    $staffId = (int)($_POST['staff_id'] ?? 0);
    if ($staffId) {
        $pdo->prepare("UPDATE staff SET username = NULL, password = NULL WHERE id = ? AND school_id = ?")
            ->execute([$staffId, $schoolId]);
        $mesaj = 'Panel erişimi kaldırıldı.';
    }
}

// ── Mevcut öğretmenleri listele ─────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT s.id, s.full_name, s.title, s.username, s.sinif_ogretmeni,
           b.branch_name, b.id as branch_id,
           (SELECT COUNT(*) FROM islenen_konular ik WHERE ik.ogretmen_id = s.id) as konu_sayisi
    FROM staff s
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE s.school_id = ? AND s.role = 'teacher'
    ORDER BY b.branch_name, s.full_name
");
$stmt->execute([$schoolId]);
$ogretmenler = $stmt->fetchAll();

// Branş listesi
$stmt = $pdo->prepare("SELECT id, branch_name FROM branches WHERE school_id = ? ORDER BY branch_name");
$stmt->execute([$schoolId]);
$branslar = $stmt->fetchAll();

// Sınıf listesi
$stmt = $pdo->prepare("SELECT DISTINCT class FROM students WHERE school_id = ? AND class IS NOT NULL ORDER BY class");
$stmt->execute([$schoolId]);
$siniflar = $stmt->fetchAll(PDO::FETCH_COLUMN);

// İstatistikler
$toplamOgretmen = count($ogretmenler);
$erisimliOgretmen = count(array_filter($ogretmenler, fn($o) => !empty($o['username'])));
$sinifAtanmis = count(array_filter($ogretmenler, fn($o) => !empty($o['sinif_ogretmeni'])));
$toplamKonu = array_sum(array_column($ogretmenler, 'konu_sayisi'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğretmenler — Aibüke Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0a0a1a; --surface: #12122a; --surface-2: #1a1a36;
            --border: rgba(99,102,241,0.1); --border-solid: #252545;
            --text: #e2e8f0; --muted: #7c8db0;
            --primary: #6366f1; --accent: #a78bfa;
            --success: #22c55e; --error: #ef4444; --warning: #f59e0b;
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa);
        }
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg); color: var(--text);
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(99,102,241,0.06) 0%, transparent 55%),
                radial-gradient(ellipse 50% 40% at 100% 100%, rgba(139,92,246,0.04) 0%, transparent 50%);
        }

        /* ── Main ──────────────────────────────────────────── */
        .main { margin-left: 260px; padding: 28px 32px; }

        /* ── Page Header ───────────────────────────────────── */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 28px;
        }
        .page-header h1 { font-size: 24px; font-weight: 700; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--primary); font-size: 20px; }
        .page-header p { color: var(--muted); font-size: 13px; margin-top: 4px; }

        /* ── Toast ─────────────────────────────────────────── */
        .toast {
            padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500;
            margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
            animation: toastIn 0.4s ease;
        }
        @keyframes toastIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .toast-success { background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.15); color: var(--success); }
        .toast-error   { background: rgba(239,68,68,0.08);  border: 1px solid rgba(239,68,68,0.15);  color: var(--error); }

        /* ── Stats ─────────────────────────────────────────── */
        .stats-row {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 28px;
        }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; padding: 18px; text-align: center;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon { font-size: 20px; margin-bottom: 8px; color: var(--muted); }
        .stat-val {
            font-size: 28px; font-weight: 800; letter-spacing: -1px;
            background: var(--gradient); -webkit-background-clip: text;
            -webkit-text-fill-color: transparent; background-clip: text;
        }
        .stat-lbl { font-size: 11px; color: var(--muted); margin-top: 3px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        /* ── Section ───────────────────────────────────────── */
        .section-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;
        }
        .section-title {
            font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px;
        }
        .section-title i { color: var(--primary); font-size: 15px; }
        .section-count {
            font-size: 12px; color: var(--muted); font-weight: 500;
            background: var(--surface); border: 1px solid var(--border);
            padding: 4px 12px; border-radius: 100px;
        }

        /* ── Teacher Card Grid ─────────────────────────────── */
        .teacher-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px; margin-bottom: 36px;
        }
        .teacher-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; padding: 20px; position: relative;
            transition: all 0.2s; overflow: hidden;
        }
        .teacher-card:hover { border-color: rgba(99,102,241,0.25); transform: translateY(-2px); }
        .teacher-card-top { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
        .teacher-avatar {
            width: 48px; height: 48px; border-radius: 14px;
            background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(167,139,250,0.1));
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: var(--accent); font-weight: 700; flex-shrink: 0;
            border: 1px solid rgba(167,139,250,0.12);
        }
        .teacher-info { flex: 1; min-width: 0; }
        .teacher-name { font-size: 15px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .teacher-branch {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 11px; font-weight: 600; color: var(--accent);
            background: rgba(167,139,250,0.08); border: 1px solid rgba(167,139,250,0.1);
            padding: 2px 10px; border-radius: 100px; margin-top: 4px;
        }
        .teacher-meta {
            display: flex; gap: 12px; flex-wrap: wrap;
            padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.04);
            margin-bottom: 14px;
        }
        .teacher-meta-item {
            display: flex; align-items: center; gap: 5px;
            font-size: 11px; color: var(--muted); font-weight: 500;
        }
        .teacher-meta-item i { font-size: 10px; }
        .teacher-meta-item.active { color: var(--success); }
        .teacher-meta-item.warn { color: var(--warning); }
        .teacher-meta-item.highlight { color: var(--primary); }
        .teacher-actions {
            display: flex; gap: 6px; flex-wrap: wrap;
        }

        /* ── Buttons ───────────────────────────────────────── */
        .btn {
            padding: 8px 14px; border-radius: 10px; border: none; cursor: pointer;
            font-size: 12px; font-weight: 600; font-family: inherit;
            transition: all 0.15s; display: inline-flex; align-items: center; gap: 6px;
            text-decoration: none; -webkit-tap-highlight-color: transparent;
        }
        .btn-primary { background: var(--gradient); color: #fff; box-shadow: 0 4px 14px rgba(99,102,241,0.2); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.3); }
        .btn-ghost { background: rgba(99,102,241,0.08); color: var(--primary); border: 1px solid rgba(99,102,241,0.1); }
        .btn-ghost:hover { background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.2); }
        .btn-danger { background: rgba(239,68,68,0.08); color: var(--error); border: 1px solid rgba(239,68,68,0.1); }
        .btn-danger:hover { background: rgba(239,68,68,0.15); }
        .btn-warn { background: rgba(245,158,11,0.08); color: var(--warning); border: 1px solid rgba(245,158,11,0.1); }
        .btn-warn:hover { background: rgba(245,158,11,0.15); }
        .btn-success { background: rgba(34,197,94,0.08); color: var(--success); border: 1px solid rgba(34,197,94,0.1); }
        .btn-success:hover { background: rgba(34,197,94,0.15); }

        /* ── Add Form ──────────────────────────────────────── */
        .add-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 20px; padding: 28px; position: relative; overflow: hidden;
            margin-bottom: 36px;
        }
        .add-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: var(--gradient);
        }
        .add-card-title {
            font-size: 16px; font-weight: 700; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .add-card-title i { color: var(--primary); }
        .add-form { display: grid; grid-template-columns: repeat(4, 1fr) auto; gap: 14px; align-items: end; }
        .form-group { display: flex; flex-direction: column; }
        .form-label {
            font-size: 11px; font-weight: 600; color: var(--muted);
            margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .form-input, .form-select {
            width: 100%; padding: 11px 14px;
            background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid);
            border-radius: 12px; color: var(--text); font-size: 14px;
            outline: none; transition: all 0.2s; font-family: inherit;
        }
        .form-input:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .form-input::placeholder { color: rgba(124,141,176,0.4); }

        /* ── Modal ─────────────────────────────────────────── */
        .modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 200;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(6px);
            align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: var(--surface-2); border: 1px solid var(--border-solid);
            border-radius: 20px; padding: 0; width: 100%; max-width: 440px;
            overflow: hidden; animation: modalIn 0.25s ease;
        }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-header {
            padding: 22px 24px 0; display: flex; align-items: center; gap: 12px;
        }
        .modal-header-icon {
            width: 40px; height: 40px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
        }
        .modal-header-icon.purple { background: rgba(99,102,241,0.12); color: var(--primary); }
        .modal-header-icon.green { background: rgba(34,197,94,0.12); color: var(--success); }
        .modal-header h3 { font-size: 16px; font-weight: 700; }
        .modal-header p { font-size: 12px; color: var(--muted); margin-top: 2px; }
        .modal-body { padding: 20px 24px; }
        .modal-footer {
            padding: 16px 24px; display: flex; gap: 10px; justify-content: flex-end;
            border-top: 1px solid rgba(255,255,255,0.04);
            background: rgba(0,0,0,0.15);
        }
        .modal-footer .btn { padding: 10px 20px; }
        .btn-cancel { background: var(--border-solid); color: var(--text); border: none; }
        .btn-cancel:hover { background: #3a3a5c; }

        /* ── Empty ─────────────────────────────────────────── */
        .empty-state {
            text-align: center; padding: 60px 20px; color: var(--muted);
        }
        .empty-state i { font-size: 40px; opacity: 0.15; display: block; margin-bottom: 12px; }
        .empty-state p { font-size: 13px; }

        /* ── Animations ────────────────────────────────────── */
        @keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .animate { animation: fadeUp 0.4s ease forwards; opacity: 0; }
        .d1 { animation-delay: 0.05s; } .d2 { animation-delay: 0.1s; }
        .d3 { animation-delay: 0.15s; } .d4 { animation-delay: 0.2s; }

        /* ── Responsive ────────────────────────────────────── */
        @media (max-width: 1100px) {
            .add-form { grid-template-columns: repeat(2, 1fr); }
            .add-form .btn-primary { grid-column: 1 / -1; }
        }
        @media (max-width: 768px) {
            .teacher-grid { grid-template-columns: 1fr; }
            .add-form { grid-template-columns: 1fr; }
        }
    </style>
    </head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="page-header animate d1">
        <div>
            <h1><i class="fas fa-user-tie"></i> Öğretmenler</h1>
            <p>Öğretmen hesaplarını yönetin, sınıf öğretmeni atayin ve giriş bilgilerini düzenleyin.</p>
        </div>
    </div>

    <?php if ($mesaj): ?>
        <div class="toast toast-success animate d1"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div>
    <?php endif; ?>
    <?php if ($hata): ?>
        <div class="toast toast-error animate d1"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($hata) ?></div>
    <?php endif; ?>

    <!-- İstatistikler -->
    <div class="stats-row animate d2">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
            <div class="stat-val"><?= $toplamOgretmen ?></div>
            <div class="stat-lbl">Toplam</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-key"></i></div>
            <div class="stat-val"><?= $erisimliOgretmen ?></div>
            <div class="stat-lbl">Panel Erişimi</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users-rectangle"></i></div>
            <div class="stat-val"><?= $sinifAtanmis ?></div>
            <div class="stat-lbl">Sınıf Öğrt.</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-book-open"></i></div>
            <div class="stat-val"><?= $toplamKonu ?></div>
            <div class="stat-lbl">İşlenen Konu</div>
        </div>
    </div>

    <!-- Yeni Öğretmen Ekle -->
    <div class="add-card animate d3">
        <div class="add-card-title"><i class="fas fa-user-plus"></i> Yeni Öğretmen Ekle</div>
        <form method="POST" class="add-form">
            <input type="hidden" name="action" value="ekle">
            <div class="form-group">
                <label class="form-label">Ad Soyad</label>
                <input type="text" name="ad_soyad" class="form-input" placeholder="Ayşe Kaya" required>
            </div>
            <div class="form-group">
                <label class="form-label">Branş</label>
                <select name="branch_id" class="form-select" required>
                    <option value="">Seçin...</option>
                    <?php foreach ($branslar as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Kullanıcı Adı</label>
                <input type="text" name="username" class="form-input" placeholder="kullanici_adi" required autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label">Şifre <span style="opacity:0.5">(min 6)</span></label>
                <input type="password" name="password" class="form-input" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary" style="height:44px;white-space:nowrap;">
                <i class="fas fa-plus"></i> Ekle
            </button>
        </form>
    </div>

    <!-- Öğretmen Listesi -->
    <div class="section-header animate d4">
        <div class="section-title"><i class="fas fa-list"></i> Öğretmenler</div>
        <span class="section-count"><?= $toplamOgretmen ?> kayıt</span>
    </div>

    <?php if (empty($ogretmenler)): ?>
        <div class="empty-state animate d4">
            <i class="fas fa-user-tie"></i>
            <p>Henüz öğretmen eklenmemiş.</p>
        </div>
    <?php else: ?>
    <div class="teacher-grid animate d4">
        <?php foreach ($ogretmenler as $o):
            $initials = '';
            $parts = explode(' ', $o['full_name']);
            foreach ($parts as $p) { $initials .= mb_substr(trim($p), 0, 1, 'UTF-8'); }
            $initials = mb_strtoupper(mb_substr($initials, 0, 2, 'UTF-8'), 'UTF-8');
        ?>
        <div class="teacher-card">
            <div class="teacher-card-top">
                <div class="teacher-avatar"><?= $initials ?></div>
                <div class="teacher-info">
                    <div class="teacher-name"><?= htmlspecialchars($o['full_name']) ?></div>
                    <div class="teacher-branch"><i class="fas fa-book-open"></i> <?= htmlspecialchars($o['branch_name'] ?? 'Belirtilmemiş') ?></div>
                </div>
            </div>
            <div class="teacher-meta">
                <?php if ($o['sinif_ogretmeni']): ?>
                    <span class="teacher-meta-item active"><i class="fas fa-users-rectangle"></i> <?= htmlspecialchars($o['sinif_ogretmeni']) ?> Sınıf Öğrt.</span>
                <?php endif; ?>
                <?php if ($o['username']): ?>
                    <span class="teacher-meta-item active"><i class="fas fa-circle-check"></i> Panel erişimi var</span>
                <?php else: ?>
                    <span class="teacher-meta-item"><i class="fas fa-circle-xmark"></i> Panel erişimi yok</span>
                <?php endif; ?>
                <?php if ($o['konu_sayisi'] > 0): ?>
                    <span class="teacher-meta-item highlight"><i class="fas fa-book-open"></i> <?= $o['konu_sayisi'] ?> konu</span>
                <?php endif; ?>
            </div>
            <div class="teacher-actions">
                <button class="btn btn-success" onclick="openSinifModal(<?= $o['id'] ?>, <?= htmlspecialchars(json_encode($o['full_name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['sinif_ogretmeni'] ?? '', JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                    <i class="fas fa-users-rectangle"></i> Sınıf
                </button>
                <button class="btn btn-ghost" onclick="openSifreModal(<?= $o['id'] ?>, <?= htmlspecialchars(json_encode($o['full_name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['username'] ?? '', JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                    <i class="fas fa-key"></i> Giriş
                </button>
                <?php if ($o['username']): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Panel erişimini kaldırmak istediğinizden emin misiniz?')">
                    <input type="hidden" name="action" value="erisim_kaldir">
                    <input type="hidden" name="staff_id" value="<?= $o['id'] ?>">
                    <button type="submit" class="btn btn-warn"><i class="fas fa-ban"></i></button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Bu öğretmeni ve girdiği tüm konuları silmek istediğinizden emin misiniz?')">
                    <input type="hidden" name="action" value="sil">
                    <input type="hidden" name="staff_id" value="<?= $o['id'] ?>">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-can"></i></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<!-- ═══════════════════ Giriş Bilgileri Modal ═══════════════════ -->
<div class="modal-overlay" id="sifreModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon purple"><i class="fas fa-key"></i></div>
            <div>
                <h3>Giriş Bilgileri</h3>
                <p id="modalOgretmenAd"></p>
            </div>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="sifre">
                <input type="hidden" name="staff_id" id="modalStaffId">
                <div id="modalMevcutInfo" style="font-size:12px;color:var(--muted);margin-bottom:14px;padding:10px 14px;background:rgba(99,102,241,0.06);border-radius:10px;display:none;">
                    <i class="fas fa-info-circle" style="margin-right:4px;color:var(--primary);"></i>
                    Mevcut: <strong id="modalMevcutUsername" style="color:var(--primary);"></strong>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" name="new_username" id="modalUsername" class="form-input" required autocomplete="off" placeholder="Kullanıcı adı girin">
                </div>
                <div class="form-group">
                    <label class="form-label">Yeni Şifre <span style="opacity:0.5">(min 6 karakter)</span></label>
                    <input type="password" name="new_password" class="form-input" required autocomplete="new-password" placeholder="Yeni şifre girin">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeModal('sifreModal')">İptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════ Sınıf Öğretmeni Modal ═══════════════════ -->
<div class="modal-overlay" id="sinifModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon green"><i class="fas fa-users-rectangle"></i></div>
            <div>
                <h3>Sınıf Öğretmeni Ata</h3>
                <p id="sinifModalAd"></p>
            </div>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="sinif_ata">
                <input type="hidden" name="staff_id" id="sinifModalStaffId">
                <div class="form-group">
                    <label class="form-label">Sınıf Seçin</label>
                    <select name="sinif" id="sinifModalSelect" class="form-select">
                        <option value="">— Sınıf öğretmenliği yok —</option>
                        <?php foreach ($siniflar as $sinif): ?>
                            <option value="<?= htmlspecialchars($sinif) ?>"><?= htmlspecialchars($sinif) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeModal('sinifModal')">İptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSifreModal(staffId, adSoyad, username) {
    document.getElementById('modalStaffId').value = staffId;
    document.getElementById('modalOgretmenAd').textContent = adSoyad;
    var inp = document.getElementById('modalUsername');
    inp.value = username || '';
    var info = document.getElementById('modalMevcutInfo');
    var span = document.getElementById('modalMevcutUsername');
    if (username) { span.textContent = username; info.style.display = 'block'; }
    else { info.style.display = 'none'; }
    document.getElementById('sifreModal').classList.add('open');
    setTimeout(function() { inp.focus(); }, 150);
}

function openSinifModal(staffId, adSoyad, mevcutSinif) {
    document.getElementById('sinifModalStaffId').value = staffId;
    document.getElementById('sinifModalAd').textContent = adSoyad;
    document.getElementById('sinifModalSelect').value = mevcutSinif || '';
    document.getElementById('sinifModal').classList.add('open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
    });
});
</script>
<script src="/admin/assets/mobile.js?v=<?= filemtime(__DIR__.'/assets/mobile.js') ?>"></script>
</body>
</html>
