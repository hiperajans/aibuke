<?php
/**
 * Aibuke - Aday Öğretmen Dosyası
 * Aday öğretmen değerlendirme dosyası (sınav, performans, gözlem).
 */

require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();
$ogretmen = getOgretmenInfo();

$userId = $ogretmen["id"];
$schoolId = $ogretmen["school_id"];

$mesaj = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);

    if ($_POST['action'] === 'kaydet') {
        $adayAdi = trim($_POST['aday_adi']);
        $tarih = trim($_POST['tarih']) ?: null;
        $tur = $_POST['dosya_turu'];
        if (!isset($turler[$tur])) { echo json_encode(['error' => 'Geçersiz dosya türü']); exit; }
        $puan = $_POST['puan'] !== '' ? (int)$_POST['puan'] : null;
        $aciklama = trim($_POST['aciklama']);
        $notlar = trim($_POST['notlar']);

        if ($id > 0) {
            $pdo->prepare("UPDATE aday_ogretmen_dosyasi SET aday_adi=?, tarih=?, dosya_turu=?, puan=?, aciklama=?, notlar=? WHERE id=? AND school_id=?")
                ->execute([$adayAdi, $tarih, $tur, $puan, $aciklama, $notlar, $id, $schoolId]);
            $mesaj = 'Aday kaydı güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO aday_ogretmen_dosyasi (school_id, created_by, aday_adi, tarih, dosya_turu, puan, aciklama, notlar) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$schoolId, $userId, $adayAdi, $tarih, $tur, $puan, $aciklama, $notlar]);
            $mesaj = 'Aday kaydı eklendi.';
        }
    } elseif ($_POST['action'] === 'sil') {
        $pdo->prepare("DELETE FROM aday_ogretmen_dosyasi WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Aday kaydı silindi.';
    }
}

$filterTur = isset($_GET['tur']) ? trim($_GET['tur']) : '';

$turler = [
    'sinav' => 'Sınav',
    'performans' => 'Performans Değerlendirme',
    'gozlem' => 'Gözlem',
    'sunum' => 'Sunum',
    'staj' => 'Staj Değerlendirme',
    'sikayet' => 'Şikayet',
    'tesekkur' => 'Teşekkür',
    'diger' => 'Diğer',
];

$sql = "SELECT * FROM aday_ogretmen_dosyasi WHERE school_id=?";
$params = [$schoolId];
if ($filterTur) { $sql .= " AND dosya_turu=?"; $params[] = $filterTur; }
$sql .= " ORDER BY tarih DESC, aday_adi";

$list = $pdo->prepare($sql);
$list->execute($params);
$list = $list->fetchAll(PDO::FETCH_ASSOC);

$turRenkleri = ['sinav' => '#ef4444', 'performans' => '#6366f1', 'gozlem' => '#f59e0b', 'sunum' => '#22c55e', 'staj' => '#8b5cf6', 'sikayet' => '#dc2626', 'tesekkur' => '#10b981', 'diger' => '#6b7280'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aday Öğretmen Dosyası - Aibuke</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #0a0a1a; --surface: rgba(26,26,46,0.85); --surface-solid: #1a1a2e;
            --border: rgba(99,102,241,0.12); --border-solid: #2d2d44;
            --text: #e2e8f0; --muted: #7c8db0;
            --primary: #6366f1; --accent: #a78bfa; --success: #22c55e; --error: #ef4444; --warning: #f59e0b;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); }

        .main { margin-left: 240px; padding: 32px 36px; min-height: 100vh; }
        .page-header { margin-bottom: 28px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 24px; font-weight: 700; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--primary); }

        .toast { padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; animation: toastIn 0.4s ease; }
        @keyframes toastIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .toast i { font-size: 16px; }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 28px; }
        .stat-card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 16px; padding: 18px; text-align: center; }
        .stat-val { font-size: 28px; font-weight: 800; letter-spacing: -1px; background: linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-lbl { font-size: 11px; color: var(--muted); margin-top: 3px; font-weight: 600; }

        .card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }

        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 12px; border-bottom: 1px solid var(--border-solid); }
        .data-table td { padding: 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.04); }
        .data-table tr:hover { background: rgba(99,102,241,0.04); }

        .tur-badge { display: inline-flex; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; }
        .puan-badge { display: inline-flex; padding: 3px 10px; border-radius: 100px; font-size: 12px; font-weight: 700; }
        .puan-high { background: rgba(34,197,94,0.1); color: var(--success); }
        .puan-mid { background: rgba(245,158,11,0.1); color: var(--warning); }
        .puan-low { background: rgba(239,68,68,0.1); color: var(--error); }
        .action-btns { display: flex; gap: 6px; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state i { font-size: 48px; opacity: 0.2; margin-bottom: 16px; display: block; }

        .btn { padding: 10px 20px; border-radius: 12px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; font-family: inherit; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; box-shadow: 0 4px 16px rgba(99,102,241,0.25); }
        .btn-primary:hover { transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border-solid); }
        .btn-ghost:hover { color: var(--text); border-color: var(--text); }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 10px; }
        .btn-edit { background: rgba(99,102,241,0.1); color: var(--primary); }
        .btn-del { background: rgba(239,68,68,0.1); color: var(--error); }

        .form-select, .form-input { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; transition: border-color 0.2s; }
        .form-select:focus, .form-input:focus { border-color: var(--primary); }
        .form-textarea { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; resize: vertical; min-height: 80px; transition: border-color 0.2s; }
        .form-textarea:focus { border-color: var(--primary); }
        .form-group { margin-bottom: 16px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }

        .modal-overlay { opacity: 0; pointer-events: none; transition: opacity 0.25s; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; display: flex; align-items: center; justify-content: center; }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; padding: 28px; width: 560px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .modal { padding: 20px; }
            .data-table { font-size: 12px; }
            .data-table th, .data-table td { padding: 8px 6px; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-user-graduate"></i> Aday Öğretmen Dosyası</h1>
        <button class="btn btn-primary" onclick="openFormModal()"><i class="fas fa-plus"></i> Yeni Kayıt</button>
    </div>

    <?php if ($mesaj): ?>
        <div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div>
    <?php endif; ?>

    <?php $aiType = 'aday_ogretmen'; $aiExtraLabel = 'Aday Öğretmen Adı Soyadı'; include __DIR__ . '/includes/ai_evrak_widget.php'; ?>

    <!-- İstatistikler -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-val"><?= count(array_unique(array_column($list, 'aday_adi'))) ?></div>
            <div class="stat-lbl">Toplam Aday</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= count($list) ?></div>
            <div class="stat-lbl">Toplam Kayıt</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?php
                $puanlar = array_filter(array_column($list, 'puan'));
                $ort = !empty($puanlar) ? array_sum($puanlar) / count($puanlar) : null;
                echo $ort !== null ? number_format($ort, 1) : '-';
            ?></div>
            <div class="stat-lbl">Ortalama Puan</div>
        </div>
    </div>

    <!-- Filtre -->
    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Dosya Türü</label>
                <select name="tur" class="form-select" onchange="this.form.submit()">
                    <option value="">Tümü</option>
                    <?php foreach ($turler as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $filterTur == $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filterTur): ?>
                <a href="aday.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tablo -->
    <div class="card">
        <?php if (empty($list)): ?>
            <div class="empty-state">
                <i class="fas fa-user-graduate"></i>
                <p>Kayıt bulunamadı.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Aday Adı</th>
                        <th>Tür</th>
                        <th>Puan</th>
                        <th>Açıklama</th>
                        <th>Notlar</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $row): ?>
                        <tr>
                            <td><?= $row['tarih'] ? htmlspecialchars($row['tarih']) : '—' ?></td>
                            <td><strong><?= htmlspecialchars($row['aday_adi']) ?></strong></td>
                            <td>
                                <span class="tur-badge" style="background: <?= $turRenkleri[$row['dosya_turu']] ?>18; color: <?= $turRenkleri[$row['dosya_turu']] ?>">
                                    <?= htmlspecialchars($turler[$row['dosya_turu']] ?? $row['dosya_turu']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['puan'] !== null): ?>
                                    <span class="puan-badge <?= $row['puan'] >= 70 ? 'puan-high' : ($row['puan'] >= 50 ? 'puan-mid' : 'puan-low') ?>">
                                        <?= $row['puan'] ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:var(--muted)">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(mb_substr($row['aciklama'], 0, 40)) ?><?= mb_strlen($row['aciklama']) > 40 ? '…' : '' ?></td>
                            <td><?= htmlspecialchars(mb_substr($row['notlar'], 0, 40)) ?><?= mb_strlen($row['notlar']) > 40 ? '…' : '' ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-edit" onclick="duzenle(<?= $row['id'] ?>)"><i class="fas fa-pen"></i></button>
                                    <button class="btn btn-sm btn-del" onclick="sil(<?= $row['id'] ?>)"><i class="fas fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<!-- Form Modal -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <span id="formModalTitle">Yeni Kayıt</span>
            <button class="modal-close" onclick="closeFormModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="kaydet">
            <input type="hidden" name="id" id="formId" value="0">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Aday Adı</label>
                    <input type="text" name="aday_adi" id="formAdi" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tarih</label>
                    <input type="date" name="tarih" id="formTarih" class="form-input" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tür</label>
                    <select name="dosya_turu" id="formTur" class="form-select">
                        <?php foreach ($turler as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Puan (0-100)</label>
                    <input type="number" name="puan" id="formPuan" class="form-input" min="0" max="100" placeholder="0-100">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Açıklama</label>
                <textarea name="aciklama" id="formAciklama" class="form-textarea" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Notlar</label>
                <textarea name="notlar" id="formNotlar" class="form-textarea" rows="3"></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeFormModal()">İptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Sil Modal -->
<div class="modal-overlay" id="silModal">
    <div class="modal" style="width:420px;">
        <div class="modal-header">Kaydı Sil <span class="modal-close" onclick="closeSilModal()">&times;</span></div>
        <form method="post">
            <input type="hidden" name="action" value="sil">
            <input type="hidden" name="id" id="silId" value="0">
            <p style="color:var(--muted);margin-bottom:20px;">Bu kaydı silmek istediğinizden emin misiniz?</p>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeSilModal()">İptal</button>
                <button type="submit" class="btn btn-del"><i class="fas fa-trash-can"></i> Sil</button>
            </div>
        </form>
    </div>
</div>

<script>
const veriler = <?= json_encode($list) ?>;

function openFormModal(data) {
    document.getElementById('formId').value = data ? data.id : 0;
    document.getElementById('formModalTitle').textContent = data ? 'Kayıt Düzenle' : 'Yeni Kayıt';
    if (data) {
        document.getElementById('formAdi').value = data.aday_adi;
        document.getElementById('formTarih').value = data.tarih || '';
        document.getElementById('formTur').value = data.dosya_turu;
        document.getElementById('formPuan').value = data.puan ?? '';
        document.getElementById('formAciklama').value = data.aciklama || '';
        document.getElementById('formNotlar').value = data.notlar || '';
    } else {
        document.getElementById('formAdi').value = '';
        document.getElementById('formTarih').value = '<?= date('Y-m-d') ?>';
        document.getElementById('formTur').value = 'sinav';
        document.getElementById('formPuan').value = '';
        document.getElementById('formAciklama').value = '';
        document.getElementById('formNotlar').value = '';
    }
    document.getElementById('formModal').classList.add('open');
}
function closeFormModal() { document.getElementById('formModal').classList.remove('open'); }

function duzenle(id) {
    const r = veriler.find(x => x.id == id);
    if (r) openFormModal(r);
}

function sil(id) {
    document.getElementById('silId').value = id;
    document.getElementById('silModal').classList.add('open');
}
function closeSilModal() { document.getElementById('silModal').classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
});
</script>
</body>
</html>
