<?php
/**
 * Aibuke - Nöbetçi Listesi
 * Haftalık nöbetçi öğretmen listesi yönetimi.
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
        $haftaBas = trim($_POST['hafta_baslangic']);
        $haftaBit = trim($_POST['hafta_bitis']);
        $liste = trim($_POST['nobetci_liste']);
        $not = trim($_POST['genel_not']);

        if ($id > 0) {
            $pdo->prepare("UPDATE nobet_listesi SET hafta_baslangic=?, hafta_bitis=?, nobetci_liste=?, genel_not=? WHERE id=? AND school_id=?")
                ->execute([$haftaBas, $haftaBit, $liste, $not, $id, $schoolId]);
            $mesaj = 'Nöbet listesi güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO nobet_listesi (school_id, created_by, hafta_baslangic, hafta_bitis, nobetci_liste, genel_not) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$schoolId, $userId, $haftaBas, $haftaBit, $liste, $not]);
            $mesaj = 'Nöbet listesi eklendi.';
        }
    } elseif ($_POST['action'] === 'sil') {
        $pdo->prepare("DELETE FROM nobet_listesi WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Nöbet listesi silindi.';
    }
}

$list = $pdo->prepare("SELECT * FROM nobet_listesi WHERE school_id=? ORDER BY hafta_baslangic DESC, hafta_bitis DESC");
$list->execute([$schoolId]);
$list = $list->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nöbetçi Listesi — Aibüke</title>
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

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 28px; }
        .stat-card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 16px; padding: 18px; text-align: center; }
        .stat-val { font-size: 28px; font-weight: 800; letter-spacing: -1px; background: linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-lbl { font-size: 11px; color: var(--muted); margin-top: 3px; font-weight: 600; }

        .card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 20px; overflow-x: auto; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 14px; border-bottom: 1px solid var(--border-solid); white-space: nowrap; }
        .data-table td { padding: 14px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: top; }
        .data-table tr:hover { background: rgba(99,102,241,0.04); }
        .data-table .liste-text { color: var(--muted); font-size: 12px; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .data-table .not-text { color: var(--muted); font-size: 12px; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .date-cell { display: flex; align-items: center; gap: 6px; font-size: 13px; white-space: nowrap; }
        .date-cell i { color: var(--primary); font-size: 11px; }

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

        .modal-overlay { opacity: 0; pointer-events: none; transition: opacity 0.25s; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; display: flex; align-items: center; justify-content: center; }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; padding: 28px; width: 600px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }

        .form-group { margin-bottom: 16px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; transition: border-color 0.2s; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
        .form-hint { font-size: 11px; color: var(--muted); margin-top: 6px; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 20px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
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
        <h1><i class="fas fa-calendar-week"></i> Nöbetçi Listesi</h1>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Yeni Liste</button>
    </div>

    <?php if ($mesaj): ?><div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div><?php endif; ?>

    <!-- İstatistikler -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-val"><?= count($list) ?></div>
            <div class="stat-lbl">Toplam Hafta</div>
        </div>
        <div class="stat-card">
            <div class="stat-val" style="background:linear-gradient(135deg,#22c55e,#10b981);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
                <i class="fas fa-info-circle" style="font-size:20px;-webkit-text-fill-color:var(--success);"></i>
            </div>
            <div class="stat-lbl">Hafta başlangıç ve bitiş tarihlerini girin, ardından nöbetçi listesini ekleyin.</div>
        </div>
        <div class="stat-card">
            <div class="stat-val" style="font-size:16px;background:linear-gradient(135deg,#f59e0b,#f97316);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
                JSON veya düz metin
            </div>
            <div class="stat-lbl">Liste formatı</div>
        </div>
    </div>

    <!-- Tablo -->
    <div class="card">
        <?php if (empty($list)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-week"></i>
                <p>Henüz nöbetçi listesi eklenmemiş.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Hafta Başı</th>
                        <th>Hafta Sonu</th>
                        <th>Liste</th>
                        <th>Not</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $row): ?>
                        <tr>
                            <td><span class="date-cell"><i class="fas fa-calendar"></i> <?= htmlspecialchars($row['hafta_baslangic']) ?></span></td>
                            <td><span class="date-cell"><i class="fas fa-calendar-check"></i> <?= htmlspecialchars($row['hafta_bitis']) ?></span></td>
                            <td><span class="liste-text" title="<?= htmlspecialchars($row['nobetci_liste']) ?>"><?= htmlspecialchars(mb_substr($row['nobetci_liste'], 0, 60)) ?><?= mb_strlen($row['nobetci_liste']) > 60 ? '...' : '' ?></span></td>
                            <td><span class="not-text"><?= htmlspecialchars(mb_substr($row['genel_not'], 0, 40)) ?><?= mb_strlen($row['genel_not']) > 40 ? '...' : '' ?></span></td>
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
            <span id="modalTitle">Yeni Nöbetçi Listesi</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="kaydet">
            <input type="hidden" name="id" id="formId" value="0">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Hafta Başlangıç</label>
                    <input type="date" name="hafta_baslangic" id="formBas" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Hafta Bitiş</label>
                    <input type="date" name="hafta_bitis" id="formBit" class="form-input" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Nöbetçi Liste (JSON veya düz metin)</label>
                <textarea name="nobetci_liste" id="formListe" class="form-textarea" rows="8" placeholder='[{"tarih": "2026-01-13", "sinif": "5/A", "ogretmen": "Ayşe Yılmaz"}, ...]' required></textarea>
                <div class="form-hint">JSON formatı önerilir: tarih, sinif, ogretmen alanları ile</div>
            </div>

            <div class="form-group">
                <label class="form-label">Genel Not</label>
                <textarea name="genel_not" id="formNot" class="form-textarea" rows="2" placeholder="Nöbet hakkında genel notlar..."></textarea>
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
        <div class="modal-header">Nöbet Listesini Sil <span class="modal-close" onclick="closeSil()">&times;</span></div>
        <form method="post">
            <input type="hidden" name="action" value="sil">
            <input type="hidden" name="id" id="silId" value="0">
            <p style="color:var(--muted);margin-bottom:20px;">Bu nöbetçi listesini silmek istediğinizden emin misiniz?</p>
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
    document.getElementById('modalTitle').textContent = data ? 'Nöbetçi Listesi Düzenle' : 'Yeni Nöbetçi Listesi';
    if (data) {
        document.getElementById('formBas').value = data.hafta_baslangic;
        document.getElementById('formBit').value = data.hafta_bitis;
        document.getElementById('formListe').value = data.nobetci_liste || '';
        document.getElementById('formNot').value = data.genel_not || '';
    } else {
        document.getElementById('formBas').value = '';
        document.getElementById('formBit').value = '';
        document.getElementById('formListe').value = '';
        document.getElementById('formNot').value = '';
    }
    document.getElementById('formModal').classList.add('open');
}
function closeModal() { document.getElementById('formModal').classList.remove('open'); }

function duzenle(id) {
    const r = veriler.find(x => x.id == id);
    if (r) openModal(r);
}

function sil(id) {
    document.getElementById('silId').value = id;
    document.getElementById('silModal').classList.add('open');
}
function closeSil() { document.getElementById('silModal').classList.remove('open'); }

document.getElementById('formModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
document.getElementById('silModal').addEventListener('click', function(e) { if (e.target === this) closeSil(); });
</script>
</body>
</html>
