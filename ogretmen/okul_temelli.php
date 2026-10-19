<?php
/**
 * Aibuke - Okul Temelli Planlama
 * 1-2-5-6-9-10 sınıflar için özel okul temelli planlama.
 */

require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();
$ogretmen = getOgretmenInfo();

$userId = $ogretmen["id"];
$schoolId = $ogretmen["school_id"];

$mesaj = '';

$planTurleri = [
    'ilkokul_1_2' => 'İlkokul 1-2. Sınıf',
    'ilkokul_5' => 'İlkokul 5. Sınıf',
    'ortaokul_9' => 'Ortaokul 9. Sınıf',
    'lise_10' => 'Lise 10. Sınıf',
    'ozel' => 'Özel Eğitim',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);

    if ($_POST['action'] === 'kaydet') {
        $sinif = trim($_POST['sinif']);
        $donem = (int)($_POST['donem']);
        $tur = $_POST['plan_turu'];
        $konu = trim($_POST['konu']);
        $detay = trim($_POST['konu_detay']);
        $kazanimlar = trim($_POST['kazanimlar']);
        $etkinlikler = trim($_POST['etkinlikler']);
        $tarih = trim($_POST['tarih']) ?: null;
        $sure = (int)($_POST['sure_dk'] ?? 40);
        $degerlendirme = trim($_POST['degerlendirme']);

        if ($id > 0) {
            $pdo->prepare("UPDATE okul_temelli_planlama SET sinif=?, donem=?, plan_turu=?, konu=?, konu_detay=?, kazanimlar=?, etkinlikler=?, tarih=?, sure_dk=?, degerlendirme=? WHERE id=? AND school_id=?")
                ->execute([$sinif, $donem, $tur, $konu, $detay, $kazanimlar, $etkinlikler, $tarih, $sure, $degerlendirme, $id, $schoolId]);
            $mesaj = 'Plan güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO okul_temelli_planlama (school_id, created_by, sinif, donem, plan_turu, konu, konu_detay, kazanimlar, etkinlikler, tarih, sure_dk, degerlendirme) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$schoolId, $userId, $sinif, $donem, $tur, $konu, $detay, $kazanimlar, $etkinlikler, $tarih, $sure, $degerlendirme]);
            $mesaj = 'Plan eklendi.';
        }
    } elseif ($_POST['action'] === 'sil') {
        $pdo->prepare("DELETE FROM okul_temelli_planlama WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Plan silindi.';
    }
}

$filterTur = isset($_GET['tur']) ? trim($_GET['tur']) : '';
$filterSinif = isset($_GET['sinif']) ? trim($_GET['sinif']) : '';

$sql = "SELECT * FROM okul_temelli_planlama WHERE school_id=?";
$params = [$schoolId];
if ($filterTur) { $sql .= " AND plan_turu=?"; $params[] = $filterTur; }
if ($filterSinif) { $sql .= " AND sinif=?"; $params[] = $filterSinif; }
$sql .= " ORDER BY donem, sinif, tarih DESC";

$list = $pdo->prepare($sql);
$list->execute($params);
$list = $list->fetchAll(PDO::FETCH_ASSOC);

$turRenkleri = ['ilkokul_1_2' => '#22c55e', 'ilkokul_5' => '#3b82f6', 'ortaokul_9' => '#f59e0b', 'lise_10' => '#8b5cf6', 'ozel' => '#ef4444'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Okul Temelli Planlama - Aibuke</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0a0a1a; --surface: rgba(26,26,46,0.85); --surface-solid: #1a1a2e;
            --border: rgba(99,102,241,0.12); --border-solid: #2d2d44;
            --text: #e2e8f0; --muted: #7c8db0;
            --primary: #6366f1; --accent: #a78bfa; --success: #22c55e; --error: #ef4444; --warning: #f59e0b;
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa);
        }
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg); color: var(--text);
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(99,102,241,0.08) 0%, transparent 55%),
                radial-gradient(ellipse 50% 40% at 100% 100%, rgba(139,92,246,0.05) 0%, transparent 50%);
        }
        .main { margin-left: 240px; padding: 32px 36px; min-height: 100vh; }
        .page-header { margin-bottom: 28px; }
        .page-header h1 {
            font-size: 24px; font-weight: 700; letter-spacing: -0.5px;
            display: flex; align-items: center; gap: 10px;
        }
        .page-header h1 i { color: var(--primary); font-size: 20px; }
        .page-header p { color: var(--muted); font-size: 13px; margin-top: 6px; }

        /* Toast */
        .toast {
            padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500;
            margin-bottom: 24px; display: flex; align-items: center; gap: 10px;
            animation: toastIn 0.4s ease;
        }
        @keyframes toastIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .toast i { font-size: 16px; }

        /* Stats Grid */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px; margin-bottom: 28px;
        }
        .stat-card {
            background: var(--surface); backdrop-filter: blur(12px);
            border: 1px solid var(--border); border-radius: 16px;
            padding: 18px; text-align: center; transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-val {
            font-size: 28px; font-weight: 800; letter-spacing: -1px;
            background: var(--gradient); -webkit-background-clip: text;
            -webkit-text-fill-color: transparent; background-clip: text;
        }
        .stat-lbl { font-size: 11px; color: var(--muted); margin-top: 3px; font-weight: 600; }

        /* Card */
        .card {
            background: var(--surface); backdrop-filter: blur(12px);
            border: 1px solid var(--border); border-radius: 20px;
            padding: 20px; position: relative;
        }

        /* Filter Bar */
        .filter-bar {
            display: flex; align-items: center; gap: 12px; margin-bottom: 22px; flex-wrap: wrap;
        }
        .filter-input {
            padding: 9px 14px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid);
            border-radius: 10px; color: var(--text); font-size: 13px; font-family: inherit; outline: none;
            min-width: 160px;
        }
        .filter-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }

        /* Form */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 18px; }
        .form-label {
            font-size: 12px; font-weight: 600; color: var(--muted);
            margin-bottom: 7px; display: flex; align-items: center; gap: 6px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .form-label i { font-size: 11px; color: rgba(99,102,241,0.6); }
        .form-label .req { color: var(--error); font-size: 14px; }
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%; padding: 12px 15px;
            background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid);
            border-radius: 12px; color: var(--text); font-size: 14px;
            outline: none; transition: all 0.2s; font-family: inherit;
        }
        .form-textarea { resize: vertical; min-height: 80px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }
        .form-input::placeholder, .form-textarea::placeholder { color: rgba(124,141,176,0.5); }

        /* Buttons */
        .btn {
            padding: 11px 24px; border-radius: 12px; border: none; cursor: pointer;
            font-size: 14px; font-weight: 600; font-family: inherit;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none;
        }
        .btn-primary { background: var(--gradient); color: #fff; box-shadow: 0 4px 16px rgba(99,102,241,0.25); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(99,102,241,0.35); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border-solid); }
        .btn-ghost:hover { color: var(--text); border-color: var(--text); }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 10px; }
        .btn-edit { background: rgba(99,102,241,0.1); color: var(--primary); }
        .btn-edit:hover { background: rgba(99,102,241,0.2); }
        .btn-del { background: rgba(239,68,68,0.1); color: var(--error); }
        .btn-del:hover { background: rgba(239,68,68,0.2); }
        .btn-circle {
            width: 36px; height: 36px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0; border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-circle:hover { transform: scale(1.08); }

        /* Table */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            font-size: 11px; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.5px;
            padding: 12px 16px; text-align: left;
            border-bottom: 1px solid var(--border-solid);
        }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover { background: rgba(99,102,241,0.04); }
        tbody td {
            padding: 14px 16px; font-size: 13px;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .tur-badge {
            padding: 4px 10px; border-radius: 6px; font-size: 0.85em; font-weight: 600;
        }

        /* Modal */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px); z-index: 200;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.25s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal-box {
            background: var(--surface-solid); border: 1px solid var(--border-solid);
            border-radius: 20px; width: 90%; max-width: 720px; max-height: 90vh;
            overflow-y: auto; transform: translateY(20px); transition: transform 0.25s;
        }
        .modal-overlay.open .modal-box { transform: translateY(0); }
        .modal-header {
            padding: 20px 24px; border-bottom: 1px solid var(--border-solid);
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-header h5 { font-size: 16px; font-weight: 700; }
        .modal-header h5 i { color: var(--primary); margin-right: 8px; }
        .modal-close {
            width: 32px; height: 32px; border-radius: 10px;
            background: rgba(255,255,255,0.05); border: none; color: var(--muted);
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .modal-close:hover { background: rgba(255,255,255,0.1); color: var(--text); }
        .modal-body { padding: 24px; }
        .modal-footer {
            padding: 16px 24px; border-top: 1px solid var(--border-solid);
            display: flex; gap: 10px; justify-content: flex-end;
        }

        /* Empty State */
        .empty-state {
            text-align: center; padding: 48px 20px; color: var(--muted);
        }
        .empty-state i { font-size: 40px; margin-bottom: 14px; opacity: 0.4; display: block; }
        .empty-state p { font-size: 14px; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .form-row, .form-row-3 { grid-template-columns: 1fr; gap: 0; }
            .page-header h1 { font-size: 20px; }
            .filter-bar { flex-direction: column; align-items: stretch; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-building"></i> Okul Temelli Planlama</h1>
        <p>1-2-5-6-9-10 sınıfları için okul temelli planlama kayıtları.</p>
    </div>

    <?php if ($mesaj): ?>
        <div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <?php foreach ($planTurleri as $k => $v): ?>
            <?php
            $s = $pdo->prepare("SELECT COUNT(*) FROM okul_temelli_planlama WHERE school_id=? AND plan_turu=?");
            $s->execute([$schoolId, $k]);
            $sayi = $s->fetchColumn();
            ?>
            <div class="stat-card">
                <div class="stat-val" style="-webkit-text-fill-color:transparent;background:<?= $turRenkleri[$k] ?>;-webkit-background-clip:text;"><?= $sayi ?></div>
                <div class="stat-lbl"><?= $v ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <select name="tur" class="filter-input">
                <option value="">Tüm Türler</option>
                <?php foreach ($planTurleri as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filterTur == $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="sinif" class="filter-input" placeholder="Sinif..." value="<?= htmlspecialchars($filterSinif) ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtrele</button>
            <?php if ($filterTur || $filterSinif): ?>
                <a href="okul_temelli.php" class="btn btn-ghost btn-sm">Temizle</a>
            <?php endif; ?>
        </form>
        <button class="btn btn-primary" onclick="acFormModal()"><i class="fas fa-plus"></i> Yeni Plan</button>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Donem</th>
                        <th>Sinif</th>
                        <th>Tur</th>
                        <th>Konu</th>
                        <th>Tarih</th>
                        <th>Sure</th>
                        <th>Islem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <p>Henuz plan kaydi bulunmuyor.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($list as $row): ?>
                            <tr>
                                <td><?= $row['donem'] ?>. Donem</td>
                                <td><?= htmlspecialchars($row['sinif']) ?></td>
                                <td>
                                    <span class="tur-badge" style="background: <?= $turRenkleri[$row['plan_turu']] ?>22; color: <?= $turRenkleri[$row['plan_turu']] ?>">
                                        <?= $planTurleri[$row['plan_turu']] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(mb_substr($row['konu'], 0, 45)) ?><?= mb_strlen($row['konu']) > 45 ? '...' : '' ?></td>
                                <td><?= $row['tarih'] ? htmlspecialchars($row['tarih']) : '-' ?></td>
                                <td><?= $row['sure_dk'] ?> dk</td>
                                <td>
                                    <button class="btn-circle btn-edit" onclick="duzenle(<?= $row['id'] ?>)" title="Duzenle"><i class="fas fa-pen"></i></button>
                                    <button class="btn-circle btn-del" onclick="sil(<?= $row['id'] ?>)" title="Sil"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Form Modal -->
<div class="modal-overlay" id="formModal">
    <div class="modal-box">
        <div class="modal-header">
            <h5><i class="fas fa-building"></i> Okul Temelli Plan</h5>
            <button class="modal-close" onclick="kapatFormModal()"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="kaydet">
                <input type="hidden" name="id" id="formId" value="0">

                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-users"></i> Sinif <span class="req">*</span></label>
                        <input type="text" name="sinif" id="formSinif" class="form-input" placeholder="orn: 5/A" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar-half-year"></i> Donem</label>
                        <select name="donem" id="formDonem" class="form-select">
                            <option value="1">1. Donem</option>
                            <option value="2">2. Donem</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-list"></i> Plan Turu <span class="req">*</span></label>
                        <select name="plan_turu" id="formTur" class="form-select" required>
                            <?php foreach ($planTurleri as $k => $v): ?>
                                <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-bullseye"></i> Konu <span class="req">*</span></label>
                        <input type="text" name="konu" id="formKonu" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Tarih</label>
                        <input type="date" name="tarih" id="formTarih" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-clock"></i> Sure (dk)</label>
                        <input type="number" name="sure_dk" id="formSure" class="form-input" value="40" min="10" max="180">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-align-left"></i> Konu Detay</label>
                    <textarea name="konu_detay" id="formDetay" class="form-textarea" rows="3" placeholder="Konu ile ilgili detaylar..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-bullseye"></i> Kazanimlar</label>
                    <textarea name="kazanimlar" id="formKazanimlar" class="form-textarea" rows="2" placeholder="Kazanimlar..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-play"></i> Etkinlikler</label>
                    <textarea name="etkinlikler" id="formEtkinlikler" class="form-textarea" rows="3" placeholder="Uygulanan etkinlikler..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-chart-line"></i> Degerlendirme</label>
                    <textarea name="degerlendirme" id="formDeger" class="form-textarea" rows="2" placeholder="Degerlendirme sonuclari..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="kapatFormModal()">Iptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Sil Modal -->
<div class="modal-overlay" id="silModal">
    <div class="modal-box" style="max-width:400px;">
        <div class="modal-header">
            <h5><i class="fas fa-triangle-exclamation" style="color:var(--error);"></i> Sil</h5>
            <button class="modal-close" onclick="kapatSilModal()"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="sil">
                <input type="hidden" name="id" id="silId" value="0">
                <p style="color:var(--muted);font-size:14px;">Bu plani silmek istediginizden emin misiniz?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="kapatSilModal()">Iptal</button>
                <button type="submit" class="btn btn-del"><i class="fas fa-trash"></i> Sil</button>
            </div>
        </form>
    </div>
</div>

<script>
const veriler = <?= json_encode($list) ?>;

function acFormModal() {
    document.getElementById('formId').value = 0;
    document.getElementById('formSinif').value = '';
    document.getElementById('formDonem').value = '1';
    document.getElementById('formTur').value = 'ilkokul_1_2';
    document.getElementById('formKonu').value = '';
    document.getElementById('formTarih').value = '';
    document.getElementById('formSure').value = 40;
    document.getElementById('formDetay').value = '';
    document.getElementById('formKazanimlar').value = '';
    document.getElementById('formEtkinlikler').value = '';
    document.getElementById('formDeger').value = '';
    document.getElementById('formModal').classList.add('open');
}
function kapatFormModal() { document.getElementById('formModal').classList.remove('open'); }
function kapatSilModal() { document.getElementById('silModal').classList.remove('open'); }

function duzenle(id) {
    const r = veriler.find(x => x.id == id);
    if (!r) return;
    document.getElementById('formId').value = r.id;
    document.getElementById('formSinif').value = r.sinif;
    document.getElementById('formDonem').value = r.donem;
    document.getElementById('formTur').value = r.plan_turu;
    document.getElementById('formKonu').value = r.konu;
    document.getElementById('formTarih').value = r.tarih || '';
    document.getElementById('formSure').value = r.sure_dk;
    document.getElementById('formDetay').value = r.konu_detay || '';
    document.getElementById('formKazanimlar').value = r.kazanimlar || '';
    document.getElementById('formEtkinlikler').value = r.etkinlikler || '';
    document.getElementById('formDeger').value = r.degerlendirme || '';
    document.getElementById('formModal').classList.add('open');
}
function sil(id) {
    document.getElementById('silId').value = id;
    document.getElementById('silModal').classList.add('open');
}
</script>
</body>
</html>
