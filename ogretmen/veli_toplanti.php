<?php
/**
 * Aibuke Öğretmen - Veli Toplantı Tutanakları
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
    $sinif = trim($_POST['sinif'] ?? '');
    $toplantiTarihi = trim($_POST['toplanti_tarihi'] ?? '');
    $toplantiSaati = trim($_POST['toplanti_saati'] ?? '');
    $gundem = trim($_POST['gundem'] ?? '');
    $kararlar = trim($_POST['kararlar'] ?? '');
    $katilimciSayisi = (int)($_POST['katilimci_sayisi'] ?? 0);
    $notlar = trim($_POST['notlar'] ?? '');
    $veliGorusleri = trim($_POST['veli_gorusleri'] ?? '');

    if ($sinif && $toplantiTarihi) {
        if ($id > 0) {
            $pdo->prepare("UPDATE veli_toplanti SET sinif=?, toplanti_tarihi=?, toplanti_saati=?, gundem=?, kararlar=?, katilimci_sayisi=?, notlar=?, veli_gorusleri=? WHERE id=? AND school_id=?")
                ->execute([$sinif, $toplantiTarihi, $toplantiSaati, $gundem, $kararlar, $katilimciSayisi, $notlar, $veliGorusleri, $id, $schoolId]);
            $mesaj = 'Toplantı tutanağı güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO veli_toplanti (school_id, created_by, sinif, toplanti_tarihi, toplanti_saati, gundem, kararlar, katilimci_sayisi, notlar, veli_gorusleri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$schoolId, $ogretmenId, $sinif, $toplantiTarihi, $toplantiSaati, $gundem, $kararlar, $katilimciSayisi, $notlar, $veliGorusleri]);
            $mesaj = 'Toplantı tutanağı eklendi.';
        }
    } else {
        $hata = 'Sınıf ve tarih zorunludur.';
    }
}

// POST: Sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pdo->prepare("DELETE FROM veli_toplanti WHERE id=? AND school_id=?")->execute([(int)$_POST['id'], $schoolId]);
    $mesaj = 'Tutanak silindi.';
}

// Filtre
$filterSinif = trim($_GET['sinif'] ?? '');
$filterYil = trim($_GET['yil'] ?? date('Y'));

$sql = "SELECT * FROM veli_toplanti WHERE school_id=?";
$params = [$schoolId];
if ($filterSinif) { $sql .= " AND sinif=?"; $params[] = $filterSinif; }
$sql .= " ORDER BY toplanti_tarihi DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tutanaklar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sınıf listesi (ogretmenin sinifi veya mevcut tutanaklardan)
$siniflar = $pdo->prepare("SELECT DISTINCT sinif FROM veli_toplanti WHERE school_id=? ORDER BY sinif");
$siniflar->execute([$schoolId]);
$siniflar = $siniflar->fetchAll(PDO::FETCH_COLUMN);
if ($ogretmen['sinif_ogretmeni'] && !in_array($ogretmen['sinif_ogretmeni'], $siniflar)) {
    array_unshift($siniflar, $ogretmen['sinif_ogretmeni']);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veli Toplantı Tutanakları — Aibüke</title>
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

        .toast { padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; animation: toastIn 0.4s ease; }
        @keyframes toastIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
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
        .btn-ghost:hover { color: var(--text); border-color: var(--text); }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 10px; }
        .btn-edit { background: rgba(99,102,241,0.1); color: var(--primary); }
        .btn-del { background: rgba(239,68,68,0.1); color: var(--error); }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 14px; border-bottom: 1px solid var(--border-solid); }
        .data-table td { padding: 14px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: top; }
        .data-table tr:hover { background: rgba(99,102,241,0.04); }
        .sinif-badge { display: inline-flex; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 700; background: rgba(99,102,241,0.1); color: var(--primary); }
        .date-str { color: var(--muted); font-size: 12px; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state i { font-size: 48px; opacity: 0.2; margin-bottom: 16px; display: block; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; padding: 28px; width: 560px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }

        .form-group { margin-bottom: 16px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
        .action-btns { display: flex; gap: 6px; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 20px; }
            .form-row { grid-template-columns: 1fr 1fr; }
            .modal { padding: 20px; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Veli Toplantı Tutanakları</h1>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Yeni Tutanak</button>
    </div>

    <?php if ($mesaj): ?><div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
    <?php if ($hata): ?><div class="toast toast-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($hata) ?></div><?php endif; ?>

    <?php $aiType = 'veli_toplanti'; include __DIR__ . '/includes/ai_evrak_widget.php'; ?>

    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Sınıf</label>
                <select name="sinif" onchange="this.form.submit()" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($siniflar as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= $filterSinif === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filterSinif): ?>
                <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($tutanaklar)): ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>Henüz tutanak eklenmemiş.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sınıf</th>
                        <th>Tarih / Saat</th>
                        <th>Katılımcı</th>
                        <th>Gündem</th>
                        <th>Kararlar</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tutanaklar as $t): ?>
                        <tr>
                            <td><span class="sinif-badge"><?= htmlspecialchars($t['sinif']) ?></span></td>
                            <td><span class="date-str"><?= date('d.m.Y', strtotime($t['toplanti_tarihi'])) ?></span><?= $t['toplanti_saati'] ? ' <span class="date-str">'.htmlspecialchars($t['toplanti_saati']).'</span>' : '' ?></td>
                            <td><?= $t['katilimci_sayisi'] ? '<span class="date-str">'.(int)$t['katilimci_sayisi'].' veli</span>' : '-' ?></td>
                            <td style="max-width:150px;"><?= htmlspecialchars(mb_substr($t['gundem'], 0, 60)) ?><?= mb_strlen($t['gundem']) > 60 ? '…' : '' ?></td>
                            <td style="max-width:150px;"><?= htmlspecialchars(mb_substr($t['kararlar'], 0, 60)) ?><?= mb_strlen($t['kararlar']) > 60 ? '…' : '' ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-edit" onclick='editRow(<?= json_encode($t) ?>)'><i class="fas fa-pen"></i></button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-del"><i class="fas fa-trash-can"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<!-- Modal -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <span id="modalTitle">Yeni Tutanak</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="f_id" value="0">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Sınıf *</label>
                    <input type="text" name="sinif" id="f_sinif" class="form-input" placeholder="Örn: 5-A" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tarih *</label>
                    <input type="date" name="toplanti_tarihi" id="f_toplanti_tarihi" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Saat</label>
                    <input type="time" name="toplanti_saati" id="f_toplanti_saati" class="form-input">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Katılımcı Sayısı</label>
                <input type="number" name="katilimci_sayisi" id="f_katilimci_sayisi" class="form-input" min="0" placeholder="0">
            </div>

            <div class="form-group">
                <label class="form-label">Gündem</label>
                <textarea name="gundem" id="f_gundem" class="form-textarea" placeholder="Toplantı gündemi..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Alınan Kararlar</label>
                <textarea name="kararlar" id="f_kararlar" class="form-textarea" placeholder="Toplantıda alınan kararlar..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Veli Görüşleri</label>
                <textarea name="veli_gorusleri" id="f_veli_gorusleri" class="form-textarea" placeholder="Velilerin görüşleri..."></textarea>
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
    document.getElementById('modalTitle').textContent = data ? 'Tutanak Düzenle' : 'Yeni Tutanak';
    if (data) {
        document.getElementById('f_sinif').value = data.sinif;
        document.getElementById('f_toplanti_tarihi').value = data.toplanti_tarihi;
        document.getElementById('f_toplanti_saati').value = data.toplanti_saati || '';
        document.getElementById('f_katilimci_sayisi').value = data.katilimci_sayisi || 0;
        document.getElementById('f_gundem').value = data.gundem || '';
        document.getElementById('f_kararlar').value = data.kararlar || '';
        document.getElementById('f_veli_gorusleri').value = data.veli_gorusleri || '';
        document.getElementById('f_notlar').value = data.notlar || '';
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