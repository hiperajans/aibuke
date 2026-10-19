<?php
/**
 * Aibuke Öğretmen - Not Takip (Deneme/Quiz/Sözlü)
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
    $ogrenciId = (int)($_POST['ogrenci_id'] ?? 0);
    $sinavTuru = trim($_POST['sinav_turu'] ?? '');
    $sinavTarihi = trim($_POST['sinav_tarihi'] ?? '');
    $puan = $_POST['puan'] !== '' ? (float)$_POST['puan'] : null;
    $dogru = $_POST['dogru'] !== '' ? (int)$_POST['dogru'] : null;
    $yanlis = $_POST['yanlis'] !== '' ? (int)$_POST['yanlis'] : null;
    $bos = $_POST['bos'] !== '' ? (int)$_POST['bos'] : null;
    $ders = trim($_POST['ders'] ?? '');

    if ($ogrenciId && $sinavTuru && $sinavTarihi) {
        if ($id > 0) {
            $pdo->prepare("UPDATE not_takip SET sinav_turu=?, sinav_tarihi=?, puan=?, dogru=?, yanlis=?, bos=?, ders=? WHERE id=? AND school_id=?")
                ->execute([$sinavTuru, $sinavTarihi, $puan, $dogru, $yanlis, $bos, $ders, $id, $schoolId]);
            $mesaj = 'Not güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO not_takip (ogrenci_id, school_id, created_by, sinav_turu, sinav_tarihi, puan, dogru, yanlis, bos, ders) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$ogrenciId, $schoolId, $ogretmenId, $sinavTuru, $sinavTarihi, $puan, $dogru, $yanlis, $bos, $ders]);
            $mesaj = 'Not eklendi.';
        }
    } else {
        $hata = 'Öğrenci, sınav türü ve tarih zorunludur.';
    }
}

// POST: Sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pdo->prepare("DELETE FROM not_takip WHERE id=? AND school_id=?")->execute([(int)$_POST['id'], $schoolId]);
    $mesaj = 'Not silindi.';
}

// Filtreler
$filterTuru = trim($_GET['sinav_turu'] ?? '');
$filterTarih = trim($_GET['tarih'] ?? '');
$filterOgrenci = trim($_GET['ogrenci_id'] ?? '');

// Öğrenci listesi
$ogrenciler = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, s.class FROM students s WHERE s.school_id=? ORDER BY s.class, s.first_name, s.last_name LIMIT 100");
$ogrenciler->execute([$schoolId]);
$ogrenciler = $ogrenciler->fetchAll(PDO::FETCH_ASSOC);

// Notlar
$sql = "SELECT nt.*, s.first_name, s.last_name, s.class FROM not_takip nt JOIN students s ON nt.ogrenci_id = s.id WHERE nt.school_id=?";
$params = [$schoolId];
if ($filterTuru) { $sql .= " AND nt.sinav_turu=?"; $params[] = $filterTuru; }
if ($filterTarih) { $sql .= " AND nt.sinav_tarihi=?"; $params[] = $filterTarih; }
if ($filterOgrenci) { $sql .= " AND nt.ogrenci_id=?"; $params[] = (int)$filterOgrenci; }
$sql .= " ORDER BY nt.sinav_tarihi DESC, s.class, s.first_name LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$stats = $pdo->prepare("
    SELECT sinav_turu, COUNT(*) as sayi, AVG(puan) as ortalama, MAX(puan) as en_yuksek, MIN(puan) as en_dusuk
    FROM not_takip WHERE school_id=? AND puan IS NOT NULL GROUP BY sinav_turu
");
$stats->execute([$schoolId]);
$stats = $stats->fetchAll(PDO::FETCH_ASSOC);
$statsMap = [];
foreach ($stats as $s) { $statsMap[$s['sinav_turu']] = $s; }

$sinavTurleri = ['deneme','quiz','sozlu'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Not Takip — Aibüke</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0a0a1a; --surface: rgba(26,26,46,0.85); --surface-solid: #1a1a2e;
            --border: rgba(99,102,241,0.12); --border-solid: #2d2d44;
            --text: #e2e8f0; --muted: #7c8db0;
            --primary: #6366f1; --success: #22c55e; --error: #ef4444; --warning: #f59e0b;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); }

        .sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background: var(--surface-solid); border-right: 1px solid var(--border-solid); display: flex; flex-direction: column; padding: 0; backdrop-filter: blur(20px); z-index: 50; }
        .sidebar-brand { padding: 28px 24px 24px; background: linear-gradient(180deg, rgba(99,102,241,0.08) 0%, transparent 100%); border-bottom: 1px solid var(--border-solid); }
        .sidebar-brand-icon { width: 44px; height: 44px; border-radius: 14px; background: linear-gradient(135deg, #6366f1, #8b5cf6); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; margin-bottom: 14px; }
        .sidebar-brand h2 { font-size: 16px; font-weight: 700; }
        .sidebar-brand-name { font-size: 13px; color: var(--muted); margin-top: 4px; }
        .sidebar-nav { padding: 16px 12px; flex: 1; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 11px 16px; color: var(--muted); text-decoration: none; font-size: 14px; font-weight: 500; border-radius: 12px; transition: all 0.2s; margin-bottom: 4px; }
        .nav-item:hover { color: var(--text); background: rgba(99,102,241,0.08); }
        .nav-item.active { color: #fff; background: linear-gradient(135deg, #6366f1, #8b5cf6); }
        .nav-item i { width: 18px; text-align: center; }
        .sidebar-footer { padding: 16px 20px; border-top: 1px solid var(--border-solid); }
        .sidebar-footer-school { font-size: 11px; color: var(--muted); margin-bottom: 10px; }
        .sidebar-logout:hover { opacity: 1; }

        .main { margin-left: 240px; padding: 32px 36px; min-height: 100vh; }
        .page-header { margin-bottom: 28px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 24px; font-weight: 700; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--primary); }

        .toast { padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .toast-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: var(--error); }
        .toast i { font-size: 16px; }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 28px; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 16px; }
        .stat-type { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .stat-type.deneme { color: var(--primary); }
        .stat-type.quiz { color: var(--success); }
        .stat-type.sozlu { color: var(--warning); }
        .stat-row { display: flex; justify-content: space-between; align-items: center; font-size: 12px; margin-bottom: 4px; }
        .stat-row .label { color: var(--muted); }
        .stat-row .val { font-weight: 600; }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }

        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-select, .form-input { padding: 9px 14px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 10px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; }
        .form-select:focus, .form-input:focus { border-color: var(--primary); }

        .btn { padding: 10px 20px; border-radius: 12px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; font-family: inherit; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; box-shadow: 0 4px 16px rgba(99,102,241,0.25); }
        .btn-primary:hover { transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border-solid); }
        .btn-ghost:hover { color: var(--text); }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 10px; }
        .btn-edit { background: rgba(99,102,241,0.1); color: var(--primary); }
        .btn-del { background: rgba(239,68,68,0.1); color: var(--error); }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 12px; border-bottom: 1px solid var(--border-solid); }
        .data-table td { padding: 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.04); }
        .data-table tr:hover { background: rgba(99,102,241,0.04); }
        .tur-badge { display: inline-flex; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; }
        .tur-deneme { background: rgba(99,102,241,0.1); color: var(--primary); }
        .tur-quiz { background: rgba(34,197,94,0.1); color: var(--success); }
        .tur-sozlu { background: rgba(245,158,11,0.1); color: var(--warning); }
        .puan-val { font-weight: 700; font-size: 15px; }
        .puan-high { color: var(--success); }
        .puan-mid { color: var(--warning); }
        .puan-low { color: var(--error); }
        .sinif-badge { font-size: 11px; padding: 2px 8px; border-radius: 100px; background: rgba(99,102,241,0.1); color: var(--accent); font-weight: 600; }
        .date-str { color: var(--muted); font-size: 12px; }
        .date-str.success { color: var(--success); }
        .date-str.error { color: var(--error); }

        .empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
        .empty-state i { font-size: 40px; opacity: 0.2; margin-bottom: 12px; display: block; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; padding: 28px; width: 500px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }

        .form-group { margin-bottom: 16px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-select { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; }
        .form-input:focus, .form-select:focus { border-color: var(--primary); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
        .action-btns { display: flex; gap: 6px; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .modal { padding: 20px; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-chart-bar"></i> Not Takip</h1>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Yeni Not</button>
    </div>

    <?php if ($mesaj): ?><div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
    <?php if ($hata): ?><div class="toast toast-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($hata) ?></div><?php endif; ?>

    <!-- İstatistikler -->
    <div class="stats-grid">
        <?php foreach ($sinavTurleri as $t): ?>
            <?php $s = $statsMap[$t] ?? null; ?>
            <div class="stat-card">
                <div class="stat-type <?= $t ?>">
                    <?php if ($t === 'deneme'): ?><i class="fas fa-file-lines"></i> Deneme Sınavı
                    <?php elseif ($t === 'quiz'): ?><i class="fas fa-circle-question"></i> Quiz
                    <?php else: ?><i class="fas fa-comments"></i> Sözlü
                    <?php endif; ?>
                </div>
                <?php if ($s): ?>
                    <div class="stat-row"><span class="label">Sınav sayısı</span><span class="val"><?= (int)$s['sayi'] ?></span></div>
                    <div class="stat-row"><span class="label">Ortalama</span><span class="val"><?= round($s['ortalama'], 1) ?></span></div>
                    <div class="stat-row"><span class="label">En yüksek</span><span class="val" style="color:var(--success);"><?= round($s['en_yuksek'], 1) ?></span></div>
                    <div class="stat-row"><span class="label">En düşük</span><span class="val" style="color:var(--error);"><?= round($s['en_dusuk'], 1) ?></span></div>
                <?php else: ?>
                    <div style="color:var(--muted);font-size:12px;">Henüz veri yok</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtre -->
    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Sınav Türü</label>
                <select name="sinav_turu" onchange="this.form.submit()" class="form-select">
                    <option value="">Tümü</option>
                    <option value="deneme" <?= $filterTuru === 'deneme' ? 'selected' : '' ?>>Deneme</option>
                    <option value="quiz" <?= $filterTuru === 'quiz' ? 'selected' : '' ?>>Quiz</option>
                    <option value="sozlu" <?= $filterTuru === 'sozlu' ? 'selected' : '' ?>>Sözlü</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Tarih</label>
                <input type="date" name="tarih" value="<?= htmlspecialchars($filterTarih) ?>" class="form-input" onchange="this.form.submit()">
            </div>
            <div class="filter-group">
                <label class="filter-label">Öğrenci</label>
                <select name="ogrenci_id" onchange="this.form.submit()" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($ogrenciler as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $filterOgrenci == $o['id'] ? 'selected' : '' ?>><?= htmlspecialchars($o['class'] . ' - ' . $o['first_name'] . ' ' . $o['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filterTuru || $filterTarih || $filterOgrenci): ?>
                <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Notlar Tablosu -->
    <div class="card">
        <?php if (empty($notlar)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>Henüz not girilmemiş.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Öğrenci</th>
                        <th>Sınıf</th>
                        <th>Tür</th>
                        <th>Tarih</th>
                        <th>Puan</th>
                        <th>D/Y/B</th>
                        <th>Ders</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notlar as $n): ?>
                        <?php
                        $puan = $n['puan'] !== null ? round((float)$n['puan'], 1) : '-';
                        $puanClass = $n['puan'] >= 85 ? 'puan-high' : ($n['puan'] >= 50 ? 'puan-mid' : 'puan-low');
                        $turBadgeClass = 'tur-' . $n['sinav_turu'];
                        $turLabel = $n['sinav_turu'] === 'deneme' ? 'Deneme' : ($n['sinav_turu'] === 'quiz' ? 'Quiz' : 'Sözlü');
                        $dyb = ($n['dogru'] !== null ? $n['dogru'] : '-') . '/' . ($n['yanlis'] !== null ? $n['yanlis'] : '-') . '/' . ($n['bos'] !== null ? $n['bos'] : '-');
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?></strong></td>
                            <td><span class="sinif-badge"><?= htmlspecialchars($n['class']) ?></span></td>
                            <td><span class="tur-badge <?= $turBadgeClass ?>"><?= $turLabel ?></span></td>
                            <td><span class="date-str"><?= date('d.m.Y', strtotime($n['sinav_tarihi'])) ?></span></td>
                            <td><span class="puan-val <?= $puanClass ?>"><?= $puan ?></span></td>
                            <td><span class="date-str"><?= $dyb ?></span></td>
                            <td><?= htmlspecialchars($n['ders'] ?: '-') ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-edit" onclick='editRow(<?= json_encode($n) ?>)'><i class="fas fa-pen"></i></button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $n['id'] ?>">
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
            <span id="modalTitle">Yeni Not</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="f_id" value="0">

            <div class="form-group">
                <label class="form-label">Öğrenci *</label>
                <select name="ogrenci_id" id="f_ogrenci_id" class="form-select" required>
                    <?php foreach ($ogrenciler as $o): ?>
                        <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['class'] . ' - ' . $o['first_name'] . ' ' . $o['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Sınav Türü *</label>
                    <select name="sinav_turu" id="f_sinav_turu" class="form-select" required>
                        <option value="deneme">Deneme Sınavı</option>
                        <option value="quiz">Quiz</option>
                        <option value="sozlu">Sözlü</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tarih *</label>
                    <input type="date" name="sinav_tarihi" id="f_sinav_tarihi" class="form-input" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Puan (0-100)</label>
                    <input type="number" name="puan" id="f_puan" class="form-input" min="0" max="100" step="0.1" placeholder="0-100">
                </div>
                <div class="form-group">
                    <label class="form-label">Ders</label>
                    <input type="text" name="ders" id="f_ders" class="form-input" placeholder="Örn: Matematik">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Doğru</label>
                    <input type="number" name="dogru" id="f_dogru" class="form-input" min="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Yanlış</label>
                    <input type="number" name="yanlis" id="f_yanlis" class="form-input" min="0" placeholder="0">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Boş</label>
                <input type="number" name="bos" id="f_bos" class="form-input" min="0" placeholder="0">
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
    document.getElementById('modalTitle').textContent = data ? 'Not Düzenle' : 'Yeni Not';
    if (data) {
        document.getElementById('f_ogrenci_id').value = data.ogrenci_id;
        document.getElementById('f_sinav_turu').value = data.sinav_turu;
        document.getElementById('f_sinav_tarihi').value = data.sinav_tarihi;
        document.getElementById('f_puan').value = data.puan || '';
        document.getElementById('f_ders').value = data.ders || '';
        document.getElementById('f_dogru').value = data.dogru || '';
        document.getElementById('f_yanlis').value = data.yanlis || '';
        document.getElementById('f_bos').value = data.bos || '';
    } else {
        document.getElementById('f_id').value = 0;
    }
    document.getElementById('formModal').classList.add('active');
}
function closeModal() { document.getElementById('formModal').classList.remove('active'); }
function editRow(data) { openModal(data); }
document.getElementById('formModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
</script>
</body>
</html>