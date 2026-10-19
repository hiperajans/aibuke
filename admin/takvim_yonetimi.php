<?php
/**
 * Aibuke Admin - Takvim Yönetimi
 * Okul takvimi CRUD + hafta-tarih eşlemesi
 */
require_once __DIR__ . '/auth.php';
session_start();
requireAdminLogin();
$admin = getAdminInfo();
$schoolId = (int)$admin['school_id'];
$isSuperAdmin = ($admin['role'] === 'super_admin');

$mesaj = '';
$hata = '';

// Okul seçimi — $activeSchool ataması sorgulardan ÖNCE yapılmalı
$activeSchool = $schoolId;
if ($isSuperAdmin && isset($_GET['school_id'])) {
    $activeSchool = (int)$_GET['school_id'];
}

// Red flag count for sidebar
$stmtRF = $pdo->prepare("SELECT COUNT(*) FROM aibuke_sentiment_alerts sa JOIN students s ON sa.student_id = s.id WHERE s.school_id = ? AND sa.is_resolved = 0");
$stmtRF->execute([$activeSchool]);
$redFlagCount = (int) $stmtRF->fetchColumn();

// Tüm okullar (super admin için)
$okullar = [];
if ($isSuperAdmin) {
    $stmt = $pdo->query("SELECT id, name FROM schools ORDER BY name");
    $okullar = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── POST: Takvim Event Ekle ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_event') {
    $eventType = trim($_POST['event_type'] ?? '');
    $eventName = trim($_POST['event_name'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate   = trim($_POST['end_date'] ?? '');
    $isTeaching = isset($_POST['is_teaching_day']) ? 1 : 0;
    $notes     = trim($_POST['notes'] ?? '');

    if ($eventType && $eventName && $startDate && $endDate) {
        $pdo->prepare("INSERT INTO okul_takvim (school_id, event_type, event_name, start_date, end_date, is_teaching_day, notes) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$activeSchool, $eventType, $eventName, $startDate, $endDate, $isTeaching, $notes]);
        $mesaj = 'Etkinlik eklendi.';
    } else {
        $hata = 'Tüm alanları doldurun.';
    }
}

// ── POST: Takvim Event Düzenle ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_event') {
    $id = (int)($_POST['id'] ?? 0);
    $eventType = trim($_POST['event_type'] ?? '');
    $eventName = trim($_POST['event_name'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate   = trim($_POST['end_date'] ?? '');
    $isTeaching = isset($_POST['is_teaching_day']) ? 1 : 0;
    $notes     = trim($_POST['notes'] ?? '');

    if ($id && $eventType && $eventName && $startDate && $endDate) {
        $pdo->prepare("UPDATE okul_takvim SET event_type=?, event_name=?, start_date=?, end_date=?, is_teaching_day=?, notes=? WHERE id=? AND school_id=?")
            ->execute([$eventType, $eventName, $startDate, $endDate, $isTeaching, $notes, $id, $activeSchool]);
        $mesaj = 'Etkinlik güncellendi.';
    }
}

// ── POST: Takvim Event Sil ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM okul_takvim WHERE id=? AND school_id=?")->execute([$id, $activeSchool]);
    $mesaj = 'Etkinlik silindi.';
}

// ── POST: Haftaları Otomatik Oluştur ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_weeks') {
    $donem = (int)($_POST['donem'] ?? 1);
    $baslangic = trim($_POST['baslangic_tarihi'] ?? '');
    $haftaSayisi = (int)($_POST['hafta_sayisi'] ?? 15);

    if ($baslangic) {
        $start = new DateTime($baslangic);
        $pdo->prepare("DELETE FROM yillik_plan_haftalar WHERE school_id=? AND donem=?")->execute([$activeSchool, $donem]);
        $stmt = $pdo->prepare("INSERT INTO yillik_plan_haftalar (school_id, donem, hafta_no, baslangic_tarihi, bitis_tarihi) VALUES (?, ?, ?, ?, ?)");
        for ($i = 1; $i <= $haftaSayisi; $i++) {
            $bas = clone $start;
            $bit = clone $start;
            $bit->add(new DateInterval('P6D'));
            $stmt->execute([$activeSchool, $donem, $i, $bas->format('Y-m-d'), $bit->format('Y-m-d')]);
            $start->add(new DateInterval('P7D'));
        }
        $mesaj = "$donem. dönem için $haftaSayisi hafta oluşturuldu.";
    }
}

// ── POST: Merkezi Takvimi Kopyala ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'copy_central') {
    // Okulun mevcut takvimini sil ve merkezden kopyala
    $pdo->prepare("DELETE FROM okul_takvim WHERE school_id=?")->execute([$activeSchool]);
    $pdo->prepare("DELETE FROM yillik_plan_haftalar WHERE school_id=?")->execute([$activeSchool]);

    // Merkezi takvim eventlerini kopyala
    $pdo->exec("INSERT INTO okul_takvim (school_id, event_type, event_name, start_date, end_date, is_teaching_day, notes)
                SELECT $activeSchool, event_type, event_name, start_date, end_date, is_teaching_day, notes
                FROM okul_takvim WHERE school_id = 0");

    // Merkezi haftaları kopyala
    $pdo->exec("INSERT INTO yillik_plan_haftalar (school_id, donem, hafta_no, baslangic_tarihi, bitis_tarihi)
                SELECT $activeSchool, donem, hafta_no, baslangic_tarihi, bitis_tarihi
                FROM yillik_plan_haftalar WHERE school_id = 0");

    $mesaj = 'Merkezi takvim kopyalandı.';
}

// ── Verileri Çek ─────────────────────────────────────────────────
$events = $pdo->prepare("SELECT * FROM okul_takvim WHERE school_id=? ORDER BY start_date");
$events->execute([$activeSchool]);
$events = $events->fetchAll(PDO::FETCH_ASSOC);

$haftalar1 = $pdo->prepare("SELECT * FROM yillik_plan_haftalar WHERE school_id=? AND donem=1 ORDER BY hafta_no");
$haftalar1->execute([$activeSchool]);
$haftalar1 = $haftalar1->fetchAll(PDO::FETCH_ASSOC);

$haftalar2 = $pdo->prepare("SELECT * FROM yillik_plan_haftalar WHERE school_id=? AND donem=2 ORDER BY hafta_no");
$haftalar2->execute([$activeSchool]);
$haftalar2 = $haftalar2->fetchAll(PDO::FETCH_ASSOC);

$branches = $pdo->query("SELECT id, branch_name FROM branches ORDER BY branch_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Takvim Yönetimi — Aibüke Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0f0f23; --surface: #1a1a2e; --border: #2d2d44;
            --text: #e2e8f0; --muted: #94a3b8;
            --primary: #6366f1; --accent: #ec4899;
            --success: #22c55e; --error: #ef4444; --warning: #f59e0b;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }


        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-header h1 { font-size: 22px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--primary); }
        .page-header .meta { color: var(--muted); font-size: 13px; }

        /* Toast */
        .toast { padding: 12px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .toast-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: var(--error); }
        .toast i { font-size: 15px; }

        /* Cards */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .card-title { font-size: 15px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 12px; border-bottom: 1px solid var(--border); }
        .data-table td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.04); }
        .data-table tr:hover { background: rgba(99,102,241,0.04); }
        .data-table .type-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; }
        .type-tatil { background: rgba(239,68,68,0.1); color: var(--error); }
        .type-sinav { background: rgba(245,158,11,0.1); color: var(--warning); }
        .type-donem { background: rgba(99,102,241,0.1); color: var(--primary); }
        .type-ozel { background: rgba(236,72,153,0.1); color: var(--accent); }
        .type-ders_gunu { background: rgba(34,197,94,0.1); color: var(--success); }
        .date-range { font-size: 12px; color: var(--muted); }
        .teaching-badge { font-size: 11px; padding: 2px 8px; border-radius: 100px; }
        .teaching-yes { background: rgba(34,197,94,0.1); color: var(--success); }
        .teaching-no { background: rgba(239,68,68,0.1); color: var(--error); }

        /* Grid */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }

        /* Forms */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .form-group { margin-bottom: 12px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-select, .form-textarea {
            width: 100%; padding: 10px 14px;
            background: rgba(0,0,0,0.3); border: 1px solid var(--border);
            border-radius: 8px; color: var(--text); font-size: 14px;
            outline: none; font-family: inherit;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-textarea { resize: vertical; min-height: 60px; }
        .form-checkbox { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
        .form-checkbox input { width: 16px; height: 16px; accent-color: var(--primary); }

        .btn { padding: 9px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; font-family: inherit; transition: all 0.15s; display: inline-flex; align-items: center; gap: 7px; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: #5558e3; }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
        .btn-ghost:hover { color: var(--text); border-color: var(--text); }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 6px; }
        .btn-edit { background: rgba(99,102,241,0.1); color: var(--primary); }
        .btn-del { background: rgba(239,68,68,0.1); color: var(--error); }
        .btn-success { background: rgba(34,197,94,0.15); color: var(--success); }

        /* Action buttons */
        .action-btns { display: flex; gap: 6px; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 24px; width: 480px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 16px; font-weight: 600; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 20px; cursor: pointer; }
        .modal-close:hover { color: var(--text); }

        /* Weeks table */
        .weeks-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
        .week-chip { background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.15); border-radius: 8px; padding: 8px 12px; font-size: 12px; }
        .week-chip strong { display: block; color: var(--primary); margin-bottom: 2px; }
        .week-chip span { color: var(--muted); font-size: 11px; }
        .week-chip.tatil { background: rgba(239,68,68,0.06); border-color: rgba(239,68,68,0.12); }
        .week-chip.tatil strong { color: var(--error); }

        /* School selector */
        .school-selector { display: flex; align-items: center; gap: 10px; }
        .school-selector select { padding: 8px 14px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 13px; }

        /* Empty state */
        .empty { text-align: center; padding: 30px; color: var(--muted); font-size: 13px; }
        .empty i { font-size: 32px; opacity: 0.2; display: block; margin-bottom: 10px; }

        /* Section header */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .section-header h3 { font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .section-header h3 i { color: var(--primary); }

        /* Flex row */
        .flex-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<?php if ($isSuperAdmin && count($okullar) > 0): ?>
<!-- School Selector for Super Admin -->
<div style="background:var(--surface);border-bottom:1px solid var(--border);padding:12px 30px;">
    <div class="school-selector">
        <i class="fas fa-school" style="color:var(--muted);"></i>
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <select name="school_id" class="form-select" onchange="this.form.submit()" style="width:auto;">
                <?php foreach ($okullar as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $o['id'] == $activeSchool ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-calendar"></i> Takvim Yönetimi</h1>
        <div class="flex-row">
            <button class="btn btn-ghost" onclick="openModal('addEventModal')"><i class="fas fa-plus"></i> Etkinlik Ekle</button>
            <button class="btn btn-primary" onclick="openModal('generateWeeksModal')"><i class="fas fa-calendar-week"></i> Haftaları Oluştur</button>
            <button class="btn btn-success" onclick="openModal('copyCentralModal')"><i class="fas fa-copy"></i> Merkezi Takvimi Kopyala</button>
        </div>
    </div>

    <?php if ($mesaj): ?><div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
    <?php if ($hata): ?><div class="toast toast-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($hata) ?></div><?php endif; ?>

    <!-- Takvim Events -->
    <div class="card">
        <div class="card-title"><i class="fas fa-calendar-days"></i> Okul Takvimi — <?= $activeSchool === 0 ? 'Merkezi (Tüm Okullar)' : "Okul #$activeSchool" ?></div>
        <?php if (empty($events)): ?>
            <div class="empty"><i class="fas fa-calendar-xmark"></i> Henüz etkinlik eklenmemiş</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tür</th>
                        <th>Etkinlik Adı</th>
                        <th>Tarih Aralığı</th>
                        <th>Ders Günü?</th>
                        <th>Notlar</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $e): ?>
                        <?php $typeClass = 'type-' . $e['event_type']; ?>
                        <tr>
                            <td><span class="type-badge <?= $typeClass ?>"><?= htmlspecialchars($e['event_type']) ?></span></td>
                            <td><strong><?= htmlspecialchars($e['event_name']) ?></strong></td>
                            <td><span class="date-range"><?= date('d.m.Y', strtotime($e['start_date'])) ?> — <?= date('d.m.Y', strtotime($e['end_date'])) ?></span></td>
                            <td><span class="teaching-badge <?= $e['is_teaching_day'] ? 'teaching-yes' : 'teaching-no' ?>"><?= $e['is_teaching_day'] ? 'Evet' : 'Hayır' ?></span></td>
                            <td><?= htmlspecialchars(mb_substr($e['notes'] ?? '', 0, 40)) ?><?= mb_strlen($e['notes'] ?? '') > 40 ? '…' : '' ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-edit" onclick='editEvent(<?= htmlspecialchars(json_encode($e)) ?>)'><i class="fas fa-pen"></i></button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?')">
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-del"><i class="fas fa-trash-can"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Haftalar -->
    <div class="grid-2">
        <div class="card">
            <div class="section-header">
                <h3><i class="fas fa-calendar-week"></i> 1. Dönem Haftaları</h3>
                <span style="font-size:12px;color:var(--muted);"><?= count($haftalar1) ?> hafta</span>
            </div>
            <?php if (empty($haftalar1)): ?>
                <div class="empty"><i class="fas fa-calendar-xmark"></i> Hafta verisi yok — "Haftaları Oluştur" butonunu kullanın</div>
            <?php else: ?>
                <div class="weeks-grid">
                    <?php foreach ($haftalar1 as $h): ?>
                        <div class="week-chip">
                            <strong>Hafta <?= $h['hafta_no'] ?></strong>
                            <span><?= date('d.m', strtotime($h['baslangic_tarihi'])) ?> — <?= date('d.m', strtotime($h['bitis_tarihi'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="section-header">
                <h3><i class="fas fa-calendar-week"></i> 2. Dönem Haftaları</h3>
                <span style="font-size:12px;color:var(--muted);"><?= count($haftalar2) ?> hafta</span>
            </div>
            <?php if (empty($haftalar2)): ?>
                <div class="empty"><i class="fas fa-calendar-xmark"></i> Hafta verisi yok — "Haftaları Oluştur" butonunu kullanın</div>
            <?php else: ?>
                <div class="weeks-grid">
                    <?php foreach ($haftalar2 as $h): ?>
                        <div class="week-chip">
                            <strong>Hafta <?= $h['hafta_no'] ?></strong>
                            <span><?= date('d.m', strtotime($h['baslangic_tarihi'])) ?> — <?= date('d.m', strtotime($h['bitis_tarihi'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Add Event Modal -->
<div class="modal-overlay" id="addEventModal">
    <div class="modal">
        <div class="modal-header">
            Etkinlik Ekle
            <button class="modal-close" onclick="closeModal('addEventModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_event">
            <div class="form-group">
                <label class="form-label">Etkinlik Türü</label>
                <select name="event_type" class="form-select" required>
                    <option value="tatil">Tatil</option>
                    <option value="sinav">Sınav</option>
                    <option value="donem_basi">Dönem Başı</option>
                    <option value="donem_sonu">Dönem Sonu</option>
                    <option value="ders_gunu">Ders Günü</option>
                    <option value="ozel">Özel Gün</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Etkinlik Adı</label>
                <input type="text" name="event_name" class="form-input" placeholder="Örn: Yarıyıl Tatili" required maxlength="255">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Başlangıç</label>
                    <input type="date" name="start_date" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Bitiş</label>
                    <input type="date" name="end_date" class="form-input" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="is_teaching_day" value="1" checked>
                    Ders işlenen gün (öğretmen plan önerisinde göster)
                </label>
            </div>
            <div class="form-group">
                <label class="form-label">Notlar</label>
                <textarea name="notes" class="form-textarea" placeholder="Ek notlar..."></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn btn-ghost" onclick="closeModal('addEventModal')">İptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ekle</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal-overlay" id="editEventModal">
    <div class="modal">
        <div class="modal-header">
            Etkinlik Düzenle
            <button class="modal-close" onclick="closeModal('editEventModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_event">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label class="form-label">Etkinlik Türü</label>
                <select name="event_type" id="edit_event_type" class="form-select" required>
                    <option value="tatil">Tatil</option>
                    <option value="sinav">Sınav</option>
                    <option value="donem_basi">Dönem Başı</option>
                    <option value="donem_sonu">Dönem Sonu</option>
                    <option value="ders_gunu">Ders Günü</option>
                    <option value="ozel">Özel Gün</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Etkinlik Adı</label>
                <input type="text" name="event_name" id="edit_event_name" class="form-input" required maxlength="255">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Başlangıç</label>
                    <input type="date" name="start_date" id="edit_start_date" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Bitiş</label>
                    <input type="date" name="end_date" id="edit_end_date" class="form-input" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="is_teaching_day" id="edit_is_teaching_day" value="1">
                    Ders işlenen gün
                </label>
            </div>
            <div class="form-group">
                <label class="form-label">Notlar</label>
                <textarea name="notes" id="edit_notes" class="form-textarea"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn btn-ghost" onclick="closeModal('editEventModal')">İptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Generate Weeks Modal -->
<div class="modal-overlay" id="generateWeeksModal">
    <div class="modal">
        <div class="modal-header">
            Haftaları Otomatik Oluştur
            <button class="modal-close" onclick="closeModal('generateWeeksModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="generate_weeks">
            <div class="form-group">
                <label class="form-label">Dönem</label>
                <select name="donem" class="form-select" required>
                    <option value="1">1. Dönem</option>
                    <option value="2">2. Dönem</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">İlk Hafta Başlangıç Tarihi</label>
                <input type="date" name="baslangic_tarihi" class="form-input" value="2025-09-08" required>
            </div>
            <div class="form-group">
                <label class="form-label">Hafta Sayısı</label>
                <input type="number" name="hafta_sayisi" class="form-input" value="15" min="1" max="20" required>
            </div>
            <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">
                <i class="fas fa-info-circle"></i> Mevcut haftalar silinip yeniden oluşturulur.
            </p>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-ghost" onclick="closeModal('generateWeeksModal')">İptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-week"></i> Oluştur</button>
            </div>
        </form>
    </div>
</div>

<!-- Copy Central Calendar Modal -->
<div class="modal-overlay" id="copyCentralModal">
    <div class="modal">
        <div class="modal-header">
            Merkezi Takvimi Kopyala
            <button class="modal-close" onclick="closeModal('copyCentralModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="copy_central">
            <p style="font-size:13px;color:var(--muted);margin-bottom:16px;line-height:1.6;">
                <i class="fas fa-info-circle"></i>
                Bu işlem mevcut takvimi ve haftaları siler, MEB'in resmi tatil günlerini içeren merkezi takvimi kopyalar.
                2025-2026 eğitim-öğretim yılı baz alınmıştır.
            </p>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-ghost" onclick="closeModal('copyCentralModal')">İptal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-copy"></i> Kopyala</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function editEvent(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_event_type').value = data.event_type;
    document.getElementById('edit_event_name').value = data.event_name;
    document.getElementById('edit_start_date').value = data.start_date;
    document.getElementById('edit_end_date').value = data.end_date;
    document.getElementById('edit_notes').value = data.notes || '';
    document.getElementById('edit_is_teaching_day').checked = data.is_teaching_day == 1;
    openModal('editEventModal');
}

document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
});
</script>
</body>
</html>