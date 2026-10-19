<?php
/**
 * Aibuke Öğretmen - Zümre Evrakları
 * Okul türüne göre zümre toplantı tutanakları
 */
require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();
$ogretmen = getOgretmenInfo();
$schoolId = (int)$ogretmen['school_id'];
$ogretmenId = $ogretmen['id'];

$mesaj = '';
$hata = '';

// POST: Ekle / Güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $okulTuru = trim($_POST['okul_turu'] ?? '');
    $ders = trim($_POST['ders'] ?? '');
    $donem = trim($_POST['donem'] ?? '');
    $toplantiTarihi = trim($_POST['toplanti_tarihi'] ?? '');
    $katilimcilar = trim($_POST['katilimcilar'] ?? '');
    $gundem = trim($_POST['gundem'] ?? '');
    $kararlar = trim($_POST['kararlar'] ?? '');
    $notlar = trim($_POST['notlar'] ?? '');

    if ($okulTuru && $ders && $donem && $toplantiTarihi) {
        if ($id > 0) {
            $pdo->prepare("UPDATE zumre_evraklari SET okul_turu=?, ders=?, donem=?, toplanti_tarihi=?, katilimcilar=?, gundem=?, kararlar=?, notlar=? WHERE id=? AND school_id=?")
                ->execute([$okulTuru, $ders, $donem, $toplantiTarihi, $katilimcilar, $gundem, $kararlar, $notlar, $id, $schoolId]);
            $mesaj = 'Zümre tutanağı güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO zumre_evraklari (school_id, created_by, okul_turu, ders, donem, toplanti_tarihi, katilimcilar, gundem, kararlar, notlar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$schoolId, $ogretmenId, $okulTuru, $ders, $donem, $toplantiTarihi, $katilimcilar, $gundem, $kararlar, $notlar]);
            $mesaj = 'Zümre tutanağı eklendi.';
        }
    } else {
        $hata = 'Okul türü, ders, dönem ve tarih zorunludur.';
    }
}

// POST: Sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM zumre_evraklari WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
    $mesaj = 'Zümre tutanağı silindi.';
}

// Filtreler
$filterOkulTuru = trim($_GET['okul_turu'] ?? '');
$filterDonem = trim($_GET['donem'] ?? '');
$filterDers = trim($_GET['ders'] ?? '');

// Liste
$sql = "SELECT * FROM zumre_evraklari WHERE school_id=?";
$params = [$schoolId];
if ($filterOkulTuru) { $sql .= " AND okul_turu=?"; $params[] = $filterOkulTuru; }
if ($filterDonem) { $sql .= " AND donem=?"; $params[] = $filterDonem; }
if ($filterDers) {
    $escapedDers = str_replace(['_', '%'], ['\_', '\%'], $filterDers);
    $sql .= " AND ders LIKE ?";
    $params[] = "%{$escapedDers}%";
}
$sql .= " ORDER BY toplanti_tarihi DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evraklar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ders listesi (staff.branch_id'den ogretmenin dersi + diger dersler)
$dersler = $pdo->query("SELECT DISTINCT branch_name FROM branches ORDER BY branch_name")->fetchAll(PDO::FETCH_COLUMN);

$okulTurleri = ['ilkokul','ortaokul','lise','meslek','ozelegitim'];
$donemler = ['1. Donem','2. Donem','Ara Dönem'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zümre Evrakları — Aibüke</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0a0a1a; --surface: rgba(26,26,46,0.85); --surface-solid: #1a1a2e;
            --border: rgba(99,102,241,0.12); --border-solid: #2d2d44;
            --text: #e2e8f0; --muted: #7c8db0;
            --primary: #6366f1; --primary-soft: rgba(99,102,241,0.1);
            --accent: #a78bfa; --success: #22c55e; --error: #ef4444; --warning: #f59e0b;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); }

        .main { margin-left: 240px; padding: 32px 36px; min-height: 100vh; }

        .page-header { margin-bottom: 28px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 24px; font-weight: 700; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--primary); }

        .toast { padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; animation: toastIn 0.4s ease; }
        @keyframes toastIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .toast-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: var(--error); }
        .toast i { font-size: 16px; }

        .card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }

        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-select, .form-input { padding: 9px 14px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 10px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; }
        .form-select:focus, .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }

        .btn { padding: 10px 20px; border-radius: 12px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; font-family: inherit; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; box-shadow: 0 4px 16px rgba(99,102,241,0.25); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(99,102,241,0.35); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border-solid); }
        .btn-ghost:hover { color: var(--text); border-color: var(--text); }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 10px; }
        .btn-edit { background: rgba(99,102,241,0.1); color: var(--primary); }
        .btn-del { background: rgba(239,68,68,0.1); color: var(--error); }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 14px; border-bottom: 1px solid var(--border-solid); }
        .data-table td { padding: 14px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: top; }
        .data-table tr:hover { background: rgba(99,102,241,0.04); }
        .data-table .type-badge { display: inline-flex; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; }
        .badge-ilkokul { background: rgba(34,197,94,0.1); color: var(--success); }
        .badge-ortaokul { background: rgba(99,102,241,0.1); color: var(--primary); }
        .badge-lise { background: rgba(236,72,153,0.1); color: var(--accent); }
        .badge-meslek { background: rgba(245,158,11,0.1); color: var(--warning); }
        .badge-ozelegitim { background: rgba(167,139,250,0.1); color: var(--accent); }
        .date-str { color: var(--muted); font-size: 12px; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state i { font-size: 48px; opacity: 0.2; margin-bottom: 16px; display: block; }
        .empty-state p { font-size: 14px; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; padding: 28px; width: 560px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }

        .form-group { margin-bottom: 16px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }

        .action-btns { display: flex; gap: 6px; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 20px; }
            .form-row { grid-template-columns: 1fr; }
            .modal { padding: 20px; width: 95vw; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-users-rectangle"></i> Zümre Evrakları</h1>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Yeni Zümre Tutanağı</button>
    </div>

    <?php if ($mesaj): ?><div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
    <?php if ($hata): ?><div class="toast toast-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($hata) ?></div><?php endif; ?>

    <?php $aiType = 'zumre'; include __DIR__ . '/includes/ai_evrak_widget.php'; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-filter"></i> Filtreler</div>
        </div>
        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Okul Türü</label>
                <select name="okul_turu" onchange="this.form.submit()" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($okulTurleri as $t): ?>
                        <option value="<?= $t ?>" <?= $filterOkulTuru === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Dönem</label>
                <select name="donem" onchange="this.form.submit()" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($donemler as $d): ?>
                        <option value="<?= $d ?>" <?= $filterDonem === $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Ders</label>
                <input type="text" name="ders" value="<?= htmlspecialchars($filterDers) ?>" placeholder="Ders ara..." class="form-input">
            </div>
            <button type="submit" class="btn btn-ghost btn-sm"><i class="fas fa-filter"></i> Filtrele</button>
            <?php if ($filterOkulTuru || $filterDonem || $filterDers): ?>
                <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <?php if (empty($evraklar)): ?>
            <div class="empty-state">
                <i class="fas fa-users-rectangle"></i>
                <p>Henüz zümre tutanağı eklenmemiş.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Okul Türü</th>
                        <th>Ders</th>
                        <th>Dönem</th>
                        <th>Toplantı Tarihi</th>
                        <th>Gündem</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evraklar as $e): ?>
                        <?php $badgeClass = 'badge-' . $e['okul_turu']; ?>
                        <tr>
                            <td><span class="type-badge <?= $badgeClass ?>"><?= htmlspecialchars($e['okul_turu']) ?></span></td>
                            <td><strong><?= htmlspecialchars($e['ders']) ?></strong></td>
                            <td><?= htmlspecialchars($e['donem']) ?></td>
                            <td><span class="date-str"><?= $e['toplanti_tarihi'] ? date('d.m.Y', strtotime($e['toplanti_tarihi'])) : '-' ?></span></td>
                            <td style="max-width:200px;"><?= htmlspecialchars(mb_substr($e['gundem'], 0, 80)) ?><?= mb_strlen($e['gundem']) > 80 ? '…' : '' ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-edit" onclick='editRow(<?= json_encode($e) ?>)'><i class="fas fa-pen"></i></button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?')">
                                        <input type="hidden" name="action" value="delete">
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
</main>

<!-- Modal -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <span id="modalTitle">Yeni Zümre Tutanağı</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="evrakForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="f_id" value="0">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Okul Türü *</label>
                    <select name="okul_turu" id="f_okul_turu" class="form-select" required>
                        <?php foreach ($okulTurleri as $t): ?>
                            <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Dönem *</label>
                    <select name="donem" id="f_donem" class="form-select" required>
                        <?php foreach ($donemler as $d): ?>
                            <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Ders *</label>
                    <select name="ders" id="f_ders" class="form-select" required>
                        <?php foreach ($dersler as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Toplantı Tarihi *</label>
                    <input type="date" name="toplanti_tarihi" id="f_toplanti_tarihi" class="form-input" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Katılımcılar</label>
                <input type="text" name="katilimcilar" id="f_katilimcilar" class="form-input" placeholder="Örn: Ahmet Yılmaz, Ayşe Demir, Mehmet Kaya">
            </div>

            <div class="form-group">
                <label class="form-label">Gündem</label>
                <textarea name="gundem" id="f_gundem" class="form-textarea" placeholder="Toplantıda ele alınan konular..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Alınan Kararlar</label>
                <textarea name="kararlar" id="f_kararlar" class="form-textarea" placeholder="Toplantıda alınan kararlar..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Notlar</label>
                <textarea name="notlar" id="f_notlar" class="form-textarea" placeholder="Ek notlar..."></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">İptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(data = null) {
    document.getElementById('f_id').value = data ? data.id : 0;
    document.getElementById('modalTitle').textContent = data ? 'Zümre Tutanağı Düzenle' : 'Yeni Zümre Tutanağı';
    if (data) {
        document.getElementById('f_okul_turu').value = data.okul_turu;
        document.getElementById('f_donem').value = data.donem;
        document.getElementById('f_ders').value = data.ders;
        document.getElementById('f_toplanti_tarihi').value = data.toplanti_tarihi;
        document.getElementById('f_katilimcilar').value = data.katilimcilar || '';
        document.getElementById('f_gundem').value = data.gundem || '';
        document.getElementById('f_kararlar').value = data.kararlar || '';
        document.getElementById('f_notlar').value = data.notlar || '';
    } else {
        document.getElementById('evrakForm').reset();
    }
    document.getElementById('formModal').classList.add('active');
}

function closeModal() {
    document.getElementById('formModal').classList.remove('active');
}

function editRow(data) { openModal(data); }

document.getElementById('formModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>