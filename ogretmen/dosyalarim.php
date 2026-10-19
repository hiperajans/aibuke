<?php
/**
 * Aibuke - Dosyalarım
 * Kişisel dosya yönetimi (belge, resim, video).
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
        $adi = trim($_POST['dosya_adi']);
        $kategori = trim($_POST['kategori']) ?: 'genel';
        $tur = trim($_POST['dosya_turu']);
        $yol = trim($_POST['dosya_yolu']);
        $boyut = (int)($_POST['dosya_boyut'] ?? 0);
        $aciklama = trim($_POST['aciklama']);

        if ($id > 0) {
            $pdo->prepare("UPDATE dosyalarim SET dosya_adi=?, kategori=?, dosya_turu=?, dosya_yolu=?, dosya_boyut=?, aciklama=? WHERE id=? AND school_id=?")
                ->execute([$adi, $kategori, $tur, $yol, $boyut, $aciklama, $id, $schoolId]);
            $mesaj = 'Dosya güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO dosyalarim (school_id, created_by, dosya_adi, kategori, dosya_turu, dosya_yolu, dosya_boyut, aciklama) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$schoolId, $userId, $adi, $kategori, $tur, $yol, $boyut, $aciklama]);
            $mesaj = 'Dosya kaydedildi.';
        }
    } elseif ($_POST['action'] === 'sil') {
        $pdo->prepare("DELETE FROM dosyalarim WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Dosya silindi.';
    }
}

$filterKat = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';

$kategoriler = ['genel' => 'Genel', 'yillik_plan' => 'Yıllık Plan', 'performans' => 'Performans', 'proje' => 'Proje', 'egzersiz' => 'Egzersiz', 'belge' => 'Belge', 'sinav' => 'Sınav', 'rapor' => 'Rapor', 'diger' => 'Diğer'];

$sql = "SELECT * FROM dosyalarim WHERE school_id=?";
$params = [$schoolId];
if ($filterKat) { $sql .= " AND kategori=?"; $params[] = $filterKat; }
$sql .= " ORDER BY created_at DESC";

$list = $pdo->prepare($sql);
$list->execute($params);
$list = $list->fetchAll(PDO::FETCH_ASSOC);

function formatBytes($bytes) {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
function fileIconClass($tur) {
    if ($tur === 'pdf') return 'fa-file-pdf';
    if ($tur === 'doc' || $tur === 'docx') return 'fa-file-word';
    if ($tur === 'xls' || $tur === 'xlsx') return 'fa-file-excel';
    if (in_array($tur, ['img','jpg','jpeg','png','gif'])) return 'fa-file-image';
    if ($tur === 'video') return 'fa-file-video';
    if ($tur === 'zip') return 'fa-file-zipper';
    return 'fa-file';
}
function fileIconColor($tur) {
    if ($tur === 'pdf') return 'var(--error)';
    if ($tur === 'doc' || $tur === 'docx') return 'var(--primary)';
    if ($tur === 'xls' || $tur === 'xlsx') return 'var(--success)';
    if (in_array($tur, ['img','jpg','jpeg','png','gif'])) return 'var(--success)';
    if ($tur === 'video') return 'var(--accent)';
    if ($tur === 'zip') return 'var(--warning)';
    return 'var(--muted)';
}

$toplamBoyut = array_sum(array_column($list, 'dosya_boyut'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dosyalarım — Aibüke</title>
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
        .toast i { font-size: 16px; }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 28px; }
        .stat-card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 16px; padding: 18px; text-align: center; }
        .stat-val { font-size: 28px; font-weight: 800; letter-spacing: -1px; background: linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-val.green { background: linear-gradient(135deg, #22c55e, #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-val.amber { background: linear-gradient(135deg, #f59e0b, #d97706); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-lbl { font-size: 11px; color: var(--muted); margin-top: 3px; font-weight: 600; }

        .card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }

        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

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
        .data-table td { padding: 14px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
        .data-table tr:hover { background: rgba(99,102,241,0.04); }
        .data-table .file-name { display: flex; align-items: center; gap: 10px; font-weight: 600; }
        .data-table .file-icon { font-size: 20px; width: 28px; text-align: center; }
        .data-table .kat-badge { display: inline-flex; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; background: var(--primary-soft); color: var(--primary); }
        .data-table .type-label { color: var(--muted); font-size: 12px; }
        .data-table .size-label { color: var(--muted); font-size: 12px; }
        .data-table .date-str { color: var(--muted); font-size: 12px; }
        .action-btns { display: flex; gap: 6px; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state i { font-size: 48px; opacity: 0.2; margin-bottom: 16px; display: block; }
        .empty-state p { font-size: 14px; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; padding: 28px; width: 560px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }
        .modal-close:hover { color: var(--text); }

        .form-group { margin-bottom: 16px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }

        .confirm-text { font-size: 14px; color: var(--text); line-height: 1.6; padding: 8px 0; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 20px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
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
        <h1><i class="fas fa-folder-open"></i> Dosyalarım</h1>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Yeni Dosya</button>
    </div>

    <?php if ($mesaj): ?>
        <div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div>
    <?php endif; ?>

    <!-- Istatistikler -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-val"><?= count($list) ?></div>
            <div class="stat-lbl">Toplam Dosya</div>
        </div>
        <div class="stat-card">
            <div class="stat-val green"><?= formatBytes($toplamBoyut) ?></div>
            <div class="stat-lbl">Toplam Boyut</div>
        </div>
        <div class="stat-card">
            <div class="stat-val amber"><?= count(array_unique(array_column($list, 'kategori'))) ?></div>
            <div class="stat-lbl">Kategori</div>
        </div>
    </div>

    <!-- Filtre -->
    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Kategori</label>
                <select name="kategori" onchange="this.form.submit()" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($kategoriler as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $filterKat == $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filterKat): ?>
                <a href="dosyalarim.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tablo -->
    <div class="card">
        <?php if (empty($list)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>Henüz dosya eklenmemiş.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Dosya Adı</th>
                        <th>Kategori</th>
                        <th>Tür</th>
                        <th>Boyut</th>
                        <th>Tarih</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $row): ?>
                        <tr>
                            <td>
                                <div class="file-name">
                                    <i class="fas <?= fileIconClass($row['dosya_turu']) ?> file-icon" style="color:<?= fileIconColor($row['dosya_turu']) ?>"></i>
                                    <?= htmlspecialchars($row['dosya_adi']) ?>
                                </div>
                            </td>
                            <td><span class="kat-badge"><?= $kategoriler[$row['kategori']] ?? $row['kategori'] ?></span></td>
                            <td><span class="type-label"><?= strtoupper($row['dosya_turu']) ?: '-' ?></span></td>
                            <td><span class="size-label"><?= formatBytes($row['dosya_boyut']) ?></span></td>
                            <td><span class="date-str"><?= date('d.m.Y', strtotime($row['created_at'])) ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-edit" onclick="editRow(<?= $row['id'] ?>)"><i class="fas fa-pen"></i></button>
                                    <button class="btn btn-sm btn-del" onclick="confirmDelete(<?= $row['id'] ?>)"><i class="fas fa-trash-can"></i></button>
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
            <span id="modalTitle">Yeni Dosya</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="dosyaForm">
            <input type="hidden" name="action" value="kaydet">
            <input type="hidden" name="id" id="f_id" value="0">

            <div class="form-group">
                <label class="form-label">Dosya Adı</label>
                <input type="text" name="dosya_adi" id="f_dosya_adi" class="form-input" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="kategori" id="f_kategori" class="form-select">
                        <?php foreach ($kategoriler as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Dosya Türü</label>
                    <select name="dosya_turu" id="f_dosya_turu" class="form-select">
                        <option value="pdf">PDF</option>
                        <option value="doc">Word</option>
                        <option value="xls">Excel</option>
                        <option value="img">Resim</option>
                        <option value="video">Video</option>
                        <option value="zip">ZIP</option>
                        <option value="diger">Diğer</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Dosya Yolu / URL</label>
                <input type="text" name="dosya_yolu" id="f_dosya_yolu" class="form-input" placeholder="/uploads/dosya.pdf veya https://..." required>
            </div>

            <div class="form-group">
                <label class="form-label">Boyut (byte)</label>
                <input type="number" name="dosya_boyut" id="f_dosya_boyut" class="form-input" value="0" min="0">
            </div>

            <div class="form-group">
                <label class="form-label">Açıklama</label>
                <textarea name="aciklama" id="f_aciklama" class="form-textarea" rows="2"></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">İptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Sil Modal -->
<div class="modal-overlay" id="silModal">
    <div class="modal">
        <div class="modal-header">
            <span>Sil</span>
            <button class="modal-close" onclick="closeSilModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="sil">
            <input type="hidden" name="id" id="silId" value="0">
            <div class="confirm-text">Bu dosyayı silmek istediğinizden emin misiniz?</div>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeSilModal()">İptal</button>
                <button type="submit" class="btn btn-del"><i class="fas fa-trash-can"></i> Sil</button>
            </div>
        </form>
    </div>
</div>

<script>
const veriler = <?= json_encode($list) ?>;

function openModal(data = null) {
    document.getElementById('f_id').value = data ? data.id : 0;
    document.getElementById('modalTitle').textContent = data ? 'Dosya Düzenle' : 'Yeni Dosya';
    if (data) {
        document.getElementById('f_dosya_adi').value = data.dosya_adi;
        document.getElementById('f_kategori').value = data.kategori;
        document.getElementById('f_dosya_turu').value = data.dosya_turu || 'diger';
        document.getElementById('f_dosya_yolu').value = data.dosya_yolu;
        document.getElementById('f_dosya_boyut').value = data.dosya_boyut;
        document.getElementById('f_aciklama').value = data.aciklama || '';
    } else {
        document.getElementById('dosyaForm').reset();
        document.getElementById('f_id').value = 0;
    }
    document.getElementById('formModal').classList.add('active');
}

function closeModal() {
    document.getElementById('formModal').classList.remove('active');
}

function editRow(id) {
    const r = veriler.find(x => x.id == id);
    if (!r) return;
    openModal(r);
}

function confirmDelete(id) {
    document.getElementById('silId').value = id;
    document.getElementById('silModal').classList.add('active');
}

function closeSilModal() {
    document.getElementById('silModal').classList.remove('active');
}

document.getElementById('formModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.getElementById('silModal').addEventListener('click', function(e) {
    if (e.target === this) closeSilModal();
});
</script>
</body>
</html>
