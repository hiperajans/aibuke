<?php
/**
 * Aibuke Öğretmen - Maarif Aylık Raporları
 */
require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();
$ogretmen = getOgretmenInfo();
$schoolId = (int)$ogretmen['school_id'];
$ogretmenId = $ogretmen['id'];

$mesaj = '';
$hata = '';

$aylar = ['Eylül','Ekim','Kasım','Aralık','Ocak','Şubat','Mart','Nisan','Mayıs','Haziran'];
$yillar = [(int)date('Y'), (int)date('Y')+1];

// POST: Ekle / Güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $raporAyi = trim($_POST['rapor_ayi'] ?? '');
    $raporYili = (int)($_POST['rapor_yili'] ?? date('Y'));
    $sinif = trim($_POST['sinif'] ?? '');
    $genelDegerlendirme = trim($_POST['genel_degerlendirme'] ?? '');

    // JSON icerik alanları
    $icerik = [
        'ogretmen_sayisi' => (int)($_POST['ogretmen_sayisi'] ?? 0),
        'ogrenci_sayisi' => (int)($_POST['ogrenci_sayisi'] ?? 0),
        'devamsizlik' => (int)($_POST['devamsizlik'] ?? 0),
        'dsk_toplam' => (int)($_POST['dsk_toplam'] ?? 0),
        'etkinlik_sayisi' => (int)($_POST['etkinlik_sayisi'] ?? 0),
        'veli_toplanti_sayisi' => (int)($_POST['veli_toplanti_sayisi'] ?? 0),
        'saglik_taramasi' => (int)($_POST['saglik_taramasi'] ?? 0),
        'notlar' => trim($_POST['rapor_notlar'] ?? ''),
    ];

    if ($raporAyi && $raporYili) {
        if ($id > 0) {
            $pdo->prepare("UPDATE maarif_rapor SET rapor_ayi=?, rapor_yili=?, sinif=?, icerik=?, genel_degerlendirme=? WHERE id=? AND school_id=?")
                ->execute([$raporAyi, $raporYili, $sinif, json_encode($icerik), $genelDegerlendirme, $id, $schoolId]);
            $mesaj = 'Rapor güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO maarif_rapor (school_id, created_by, rapor_ayi, rapor_yili, sinif, icerik, genel_degerlendirme) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$schoolId, $ogretmenId, $raporAyi, $raporYili, $sinif, json_encode($icerik), $genelDegerlendirme]);
            $mesaj = 'Rapor eklendi.';
        }
    } else {
        $hata = 'Ay ve yıl zorunludur.';
    }
}

// POST: Sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pdo->prepare("DELETE FROM maarif_rapor WHERE id=? AND school_id=?")->execute([(int)$_POST['id'], $schoolId]);
    $mesaj = 'Rapor silindi.';
}

// Filtreler
$filterAy = trim($_GET['rapor_ayi'] ?? '');
$filterYil = trim($_GET['rapor_yili'] ?? date('Y'));
$filterSinif = trim($_GET['sinif'] ?? '');

$sql = "SELECT * FROM maarif_rapor WHERE school_id=?";
$params = [$schoolId];
if ($filterAy) { $sql .= " AND rapor_ayi=?"; $params[] = $filterAy; }
if ($filterYil) { $sql .= " AND rapor_yili=?"; $params[] = (int)$filterYil; }
if ($filterSinif) { $sql .= " AND sinif=?"; $params[] = $filterSinif; }
$sql .= " ORDER BY rapor_yili DESC, FIELD(rapor_ayi,'Eylül','Ekim','Kasım','Aralık','Ocak','Şubat','Mart','Nisan','Mayıs','Haziran') LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$raporlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mevcut siniflar
$siniflar = $pdo->query("SELECT DISTINCT class FROM students WHERE school_id=$schoolId ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maarif Raporları — Aibüke</title>
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

        .main { margin-left: 240px; padding: 32px 36px; min-height: 100vh; }
        .page-header { margin-bottom: 28px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 24px; font-weight: 700; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--primary); }

        .toast { padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .toast-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: var(--error); }
        .toast i { font-size: 16px; }

        .card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 20px; }
        .card-title { font-size: 15px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
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

        .rapor-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .rapor-card { background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 14px; padding: 18px; }
        .rapor-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; }
        .rapor-ay { font-size: 15px; font-weight: 700; color: var(--primary); }
        .rapor-yil { font-size: 12px; color: var(--muted); margin-top: 2px; }
        .rapor-sinif { font-size: 11px; padding: 3px 10px; border-radius: 100px; background: rgba(99,102,241,0.1); color: var(--accent); font-weight: 600; }
        .rapor-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
        .rapor-stat { display: flex; flex-direction: column; gap: 2px; }
        .rapor-stat-val { font-size: 16px; font-weight: 700; color: var(--text); }
        .rapor-stat-lbl { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; }
        .rapor-degerlendirme { font-size: 12px; color: var(--muted); line-height: 1.5; margin-bottom: 12px; }
        .rapor-actions { display: flex; gap: 6px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 12px; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state i { font-size: 48px; opacity: 0.2; margin-bottom: 16px; display: block; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; padding: 28px; width: 580px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }

        .form-group { margin-bottom: 14px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; }
        .form-textarea { resize: vertical; min-height: 70px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
        .action-btns { display: flex; gap: 6px; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 20px; }
            .form-row, .form-row-3 { grid-template-columns: 1fr; }
            .rapor-grid { grid-template-columns: 1fr; }
            .modal { padding: 20px; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-file-lines"></i> Maarif Aylık Raporları</h1>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Yeni Rapor</button>
    </div>

    <?php if ($mesaj): ?><div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
    <?php if ($hata): ?><div class="toast toast-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($hata) ?></div><?php endif; ?>

    <?php $aiType = 'maarif_rapor'; include __DIR__ . '/includes/ai_evrak_widget.php'; ?>

    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Ay</label>
                <select name="rapor_ayi" onchange="this.form.submit()" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($aylar as $a): ?>
                        <option value="<?= $a ?>" <?= $filterAy === $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Yıl</label>
                <select name="rapor_yili" onchange="this.form.submit()" class="form-select">
                    <?php foreach ($yillar as $y): ?>
                        <option value="<?= $y ?>" <?= $filterYil == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filterAy || $filterYil != date('Y')): ?>
                <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($raporlar)): ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-file-lines"></i>
                <p>Henüz rapor eklenmemiş.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="rapor-grid">
            <?php foreach ($raporlar as $r):
                $icerik = json_decode($r['icerik'] ?? '{}', true);
            ?>
                <div class="rapor-card">
                    <div class="rapor-header">
                        <div>
                            <div class="rapor-ay"><?= htmlspecialchars($r['rapor_ayi']) ?></div>
                            <div class="rapor-yil"><?= (int)$r['rapor_yili'] ?></div>
                        </div>
                        <?php if ($r['sinif']): ?>
                            <span class="rapor-sinif"><?= htmlspecialchars($r['sinif']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="rapor-stats">
                        <div class="rapor-stat">
                            <span class="rapor-stat-val"><?= (int)($icerik['ogrenci_sayisi'] ?? 0) ?></span>
                            <span class="rapor-stat-lbl">Öğrenci</span>
                        </div>
                        <div class="rapor-stat">
                            <span class="rapor-stat-val"><?= (int)($icerik['ogretmen_sayisi'] ?? 0) ?></span>
                            <span class="rapor-stat-lbl">Öğretmen</span>
                        </div>
                        <div class="rapor-stat">
                            <span class="rapor-stat-val"><?= (int)($icerik['etkinlik_sayisi'] ?? 0) ?></span>
                            <span class="rapor-stat-lbl">Etkinlik</span>
                        </div>
                        <div class="rapor-stat">
                            <span class="rapor-stat-val"><?= (int)($icerik['devamsizlik'] ?? 0) ?></span>
                            <span class="rapor-stat-lbl">Devamsız</span>
                        </div>
                    </div>
                    <?php if ($r['genel_degerlendirme']): ?>
                        <div class="rapor-degerlendirme"><?= htmlspecialchars(mb_substr($r['genel_degerlendirme'], 0, 120)) ?><?= mb_strlen($r['genel_degerlendirme']) > 120 ? '…' : '' ?></div>
                    <?php endif; ?>
                    <div class="rapor-actions">
                        <button class="btn btn-sm btn-edit" onclick='editRow(<?= htmlspecialchars(json_encode($r)) ?>)'><i class="fas fa-pen"></i></button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-del"><i class="fas fa-trash-can"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Modal -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <span id="modalTitle">Yeni Rapor</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="f_id" value="0">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Ay *</label>
                    <select name="rapor_ayi" id="f_rapor_ayi" class="form-select" required>
                        <?php foreach ($aylar as $a): ?>
                            <option value="<?= $a ?>"><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Yıl *</label>
                    <select name="rapor_yili" id="f_rapor_yili" class="form-select" required>
                        <?php foreach ($yillar as $y): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sınıf</label>
                    <select name="sinif" id="f_sinif" class="form-select">
                        <option value="">Genel (Tüm Okul)</option>
                        <?php foreach ($siniflar as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">Öğrenci</label>
                    <input type="number" name="ogrenci_sayisi" id="f_ogrenci_sayisi" class="form-input" min="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Öğretmen</label>
                    <input type="number" name="ogretmen_sayisi" id="f_ogretmen_sayisi" class="form-input" min="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Devamsız</label>
                    <input type="number" name="devamsizlik" id="f_devamsizlik" class="form-input" min="0" placeholder="0">
                </div>
            </div>

            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">DSK Toplam</label>
                    <input type="number" name="dsk_toplam" id="f_dsk_toplam" class="form-input" min="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Etkinlik</label>
                    <input type="number" name="etkinlik_sayisi" id="f_etkinlik_sayisi" class="form-input" min="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Veli Top.</label>
                    <input type="number" name="veli_toplanti_sayisi" id="f_veli_toplanti_sayisi" class="form-input" min="0" placeholder="0">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Genel Değerlendirme</label>
                <textarea name="genel_degerlendirme" id="f_genel_degerlendirme" class="form-textarea" placeholder="Aylık genel değerlendirme..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Notlar</label>
                <textarea name="rapor_notlar" id="f_rapor_notlar" class="form-textarea" placeholder="Ek notlar..."></textarea>
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
    document.getElementById('modalTitle').textContent = data ? 'Rapor Düzenle' : 'Yeni Rapor';
    if (data) {
        document.getElementById('f_rapor_ayi').value = data.rapor_ayi;
        document.getElementById('f_rapor_yili').value = data.rapor_yili;
        document.getElementById('f_sinif').value = data.sinif || '';
        document.getElementById('f_genel_degerlendirme').value = data.genel_degerlendirme || '';
        var icerik = JSON.parse(data.icerik || '{}');
        document.getElementById('f_ogrenci_sayisi').value = icerik.ogrenci_sayisi || 0;
        document.getElementById('f_ogretmen_sayisi').value = icerik.ogretmen_sayisi || 0;
        document.getElementById('f_devamsizlik').value = icerik.devamsizlik || 0;
        document.getElementById('f_dsk_toplam').value = icerik.dsk_toplam || 0;
        document.getElementById('f_etkinlik_sayisi').value = icerik.etkinlik_sayisi || 0;
        document.getElementById('f_veli_toplanti_sayisi').value = icerik.veli_toplanti_sayisi || 0;
        document.getElementById('f_rapor_notlar').value = icerik.notlar || '';
    } else {
        document.getElementById('f_id').value = 0;
        document.getElementById('f_rapor_yili').value = new Date().getFullYear();
    }
    document.getElementById('formModal').classList.add('active');
}
function closeModal() { document.getElementById('formModal').classList.remove('active'); }
function editRow(data) { openModal(data); }
document.getElementById('formModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
</script>
</body>
</html>