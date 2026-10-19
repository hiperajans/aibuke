<?php
/**
 * Aibuke - Değerlendirme Kriterleri
 * Performans, proje, ürün dosyası ve ders içi için kriter tanımları.
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
        $tur = $_POST['tur'];
        $kriterAdi = trim($_POST['kriter_adi']);
        $agirlik = (int)($_POST['puan_agirlik'] ?? 1);

        if ($id > 0) {
            $pdo->prepare("UPDATE degerlendirme_kriterler SET tur=?, kriter_adi=?, puan_agirlik=? WHERE id=? AND school_id=?")
                ->execute([$tur, $kriterAdi, $agirlik, $id, $schoolId]);
            $mesaj = 'Kriter güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO degerlendirme_kriterler (school_id, created_by, tur, kriter_adi, puan_agirlik) VALUES (?, ?, ?, ?, ?)")
                ->execute([$schoolId, $userId, $tur, $kriterAdi, $agirlik]);
            $mesaj = 'Kriter eklendi.';
        }
    } elseif ($_POST['action'] === 'sil') {
        $pdo->prepare("DELETE FROM degerlendirme_kriterler WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Kriter silindi.';
    } elseif ($_POST['action'] === 'toggle') {
        $pdo->prepare("UPDATE degerlendirme_kriterler SET aktif=NOT aktif WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Kriter durumu değiştirildi.';
    }
}

$filterTur = isset($_GET['tur']) ? trim($_GET['tur']) : '';

$turler = ['performans' => 'Performans', 'proje' => 'Proje', 'urun_dosyasi' => 'Ürün Dosyası', 'ders_ici' => 'Ders İçi'];
$turRenkleri = ['performans' => 'var(--primary)', 'proje' => '#f093fb', 'urun_dosyasi' => '#4facfe', 'ders_ici' => '#38ef7d'];

$sql = "SELECT * FROM degerlendirme_kriterler WHERE school_id=?";
$params = [$schoolId];
if ($filterTur) { $sql .= " AND tur=?"; $params[] = $filterTur; }
$sql .= " ORDER BY tur, kriter_adi";

$list = $pdo->prepare($sql);
$list->execute($params);
$list = $list->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Değerlendirme Kriterleri - Aibuke</title>
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

        .toast { padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .toast i { font-size: 16px; }

        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-select { padding: 9px 14px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 10px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; cursor: pointer; }
        .form-select:focus { border-color: var(--primary); }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 12px; border-bottom: 1px solid var(--border-solid); }
        .data-table td { padding: 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.04); }
        .data-table tr:hover { background: rgba(99,102,241,0.04); }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
        .empty-state i { font-size: 40px; opacity: 0.2; margin-bottom: 12px; display: block; }

        .tur-badge { display: inline-flex; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; }
        .action-btns { display: flex; gap: 6px; }

        .btn { padding: 10px 20px; border-radius: 12px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; font-family: inherit; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; box-shadow: 0 4px 16px rgba(99,102,241,0.25); }
        .btn-primary:hover { transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border-solid); }
        .btn-ghost:hover { color: var(--text); }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 10px; }
        .btn-edit { background: rgba(99,102,241,0.1); color: var(--primary); }
        .btn-del { background: rgba(239,68,68,0.1); color: var(--error); }
        .btn-toggle { background: rgba(99,102,241,0.06); color: var(--muted); border: 1px solid var(--border-solid); }
        .btn-toggle:hover { color: var(--primary); border-color: var(--primary); }

        .status-badge { display: inline-flex; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; }
        .status-aktif { background: rgba(34,197,94,0.1); color: var(--success); }
        .status-pasif { background: rgba(100,116,139,0.1); color: var(--muted); }
        .agirlik-val { font-weight: 700; color: var(--accent); }

        .modal-overlay { opacity: 0; pointer-events: none; transition: opacity 0.25s; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; display: flex; align-items: center; justify-content: center; }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; padding: 28px; width: 480px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }

        .form-group { margin-bottom: 16px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-select { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; transition: border-color 0.2s; }
        .form-input:focus, .form-select:focus { border-color: var(--primary); }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
        .form-hint { font-size: 11px; color: var(--muted); margin-top: 6px; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 20px; }
            .modal { padding: 20px; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-list-check"></i> Değerlendirme Kriterleri</h1>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Yeni Kriter</button>
    </div>

    <?php if ($mesaj): ?><div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div><?php endif; ?>

    <!-- Filtre -->
    <div class="filter-bar">
        <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div class="filter-group">
                <label class="filter-label">Tür</label>
                <select name="tur" onchange="this.form.submit()" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($turler as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $filterTur == $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filterTur): ?>
                <a href="kriterler.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tablo -->
    <div class="card">
        <?php if (empty($list)): ?>
            <div class="empty-state"><i class="fas fa-list-check"></i><p>Kayıt bulunamadı.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tür</th>
                        <th>Kriter Adı</th>
                        <th>Ağırlık</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $row): ?>
                        <tr>
                            <td>
                                <span class="tur-badge" style="background: <?= $turRenkleri[$row['tur']] ?>22; color: <?= $turRenkleri[$row['tur']] ?>">
                                    <?= $turler[$row['tur']] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['kriter_adi']) ?></td>
                            <td><span class="agirlik-val">x<?= $row['puan_agirlik'] ?></span></td>
                            <td>
                                <?php if ($row['aktif']): ?>
                                    <span class="status-badge status-aktif">Aktif</span>
                                <?php else: ?>
                                    <span class="status-badge status-pasif">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-edit" onclick='duzenle(<?= json_encode($row) ?>)'><i class="fas fa-pen"></i></button>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-toggle" title="<?= $row['aktif'] ? 'Pasif yap' : 'Aktif yap' ?>">
                                            <i class="fas fa-toggle-<?= $row['aktif'] ? 'on' : 'off' ?>"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-del" onclick='sil(<?= $row['id'] ?>)'><i class="fas fa-trash-can"></i></button>
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
            <span id="modalTitle">Yeni Kriter</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="kaydet">
            <input type="hidden" name="id" id="formId" value="0">
            <div class="form-group">
                <label class="form-label">Tür</label>
                <select name="tur" id="formTur" class="form-select" required>
                    <?php foreach ($turler as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Kriter Adı</label>
                <input type="text" name="kriter_adi" id="formKriterAdi" class="form-input" placeholder="örn: Araştırma becerisi" required>
            </div>
            <div class="form-group">
                <label class="form-label">Puan Ağırlığı (çarpan)</label>
                <input type="number" name="puan_agirlik" id="formAgirlik" class="form-input" value="1" min="1" max="10">
                <div class="form-hint">Örn: 2 = puan x2 çarpılır</div>
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
    <div class="modal" style="width:400px;">
        <div class="modal-header">Kriteri Sil<span class="modal-close" onclick="closeSil()">&times;</span></div>
        <form method="post" style="margin-top:0;">
            <input type="hidden" name="action" value="sil">
            <input type="hidden" name="id" id="silId" value="0">
            <p style="color:var(--muted);margin-bottom:20px;">Bu kriteri silmek istediğinizden emin misiniz?</p>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeSil()">İptal</button>
                <button type="submit" class="btn btn-del"><i class="fas fa-trash-can"></i> Sil</button>
            </div>
        </form>
    </div>
</div>

<script>
const veriler = <?= json_encode($list) ?>;
function openModal(data) {
    document.getElementById('formId').value = data ? data.id : 0;
    document.getElementById('modalTitle').textContent = data ? 'Kriter Düzenle' : 'Yeni Kriter';
    if (data) {
        document.getElementById('formTur').value = data.tur;
        document.getElementById('formKriterAdi').value = data.kriter_adi;
        document.getElementById('formAgirlik').value = data.puan_agirlik;
    } else {
        document.getElementById('formId').value = 0;
    }
    document.getElementById('formModal').classList.add('open');
}
function closeModal() { document.getElementById('formModal').classList.remove('open'); }
function duzenle(id) {
    const r = veriler.find(x => x.id == id);
    if (r) openModal(r);
}
function sil(id) { document.getElementById('silId').value = id; document.getElementById('silModal').classList.add('open'); }
function closeSil() { document.getElementById('silModal').classList.remove('open'); }
document.getElementById('formModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
document.getElementById('silModal').addEventListener('click', function(e) { if (e.target === this) closeSil(); });
</script>
</body>
</html>
