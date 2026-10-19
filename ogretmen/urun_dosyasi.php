<?php
/**
 * Aibuke - Ürün Dosyası
 * Öğrenci ürün dosyası takibi.
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
        $ogrenciId = isset($_POST['ogrenci_id']) ? (int)$_POST['ogrenci_id'] : 0;
        $ders = isset($_POST['ders']) && !empty(trim($_POST['ders'])) ? trim($_POST['ders']) : '';
        $dosyaAdi = trim($_POST['dosya_adi']);
        $kategori = trim($_POST['kategori']);
        $tarih = trim($_POST['tarih']);
        $puan = $_POST['puan'] !== '' ? (int)$_POST['puan'] : null;
        $aciklama = trim($_POST['aciklama']);

        if ($id > 0) {
            $pdo->prepare("UPDATE urun_dosyalari SET ders=?, dosya_adi=?, kategori=?, tarih=?, puan=?, aciklama=? WHERE id=? AND school_id=?")
                ->execute([$ders, $dosyaAdi, $kategori, $tarih, $puan, $aciklama, $id, $schoolId]);
            $mesaj = 'Ürün dosyası güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO urun_dosyalari (ogrenci_id, school_id, created_by, ders, dosya_adi, kategori, tarih, puan, aciklama) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$ogrenciId, $schoolId, $userId, $ders, $dosyaAdi, $kategori, $tarih, $puan, $aciklama]);
            $mesaj = 'Ürün dosyası eklendi.';
        }
    } elseif ($_POST['action'] === 'sil') {
        $pdo->prepare("DELETE FROM urun_dosyalari WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Ürün dosyası silindi.';
    }
}

$filterDers = isset($_GET['ders']) ? trim($_GET['ders']) : '';

$ogrenciler = $pdo->prepare("SELECT id, first_name AS ad, last_name AS soyad, class AS sinif FROM students WHERE school_id=? ORDER BY class, first_name, last_name");
$ogrenciler->execute([$schoolId]);
$ogrenciler = $ogrenciler->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT s.first_name AS ad, s.last_name AS soyad, s.class AS sinif, ud.* FROM urun_dosyalari ud JOIN students s ON ud.ogrenci_id=s.id WHERE ud.school_id=?";
$params = [$schoolId];

if ($filterDers) { $sql .= " AND ud.ders LIKE ?"; $params[] = "%$filterDers%"; }
$sql .= " ORDER BY ud.tarih DESC";

$list = $pdo->prepare($sql);
$list->execute($params);
$list = $list->fetchAll(PDO::FETCH_ASSOC);

$stats = $pdo->prepare("SELECT COUNT(*) as toplam, AVG(puan) as ortalama FROM urun_dosyalari WHERE school_id=?");
$stats->execute([$schoolId]);
$stats = $stats->fetch(PDO::FETCH_ASSOC);

$kategoriler = ['proje_raporu' => 'Proje Raporu', 'arastirma' => 'Araştırma', 'sunum' => 'Sunum', 'afis' => 'Afiş', 'teknoloji' => 'Teknoloji Ürünü', 'sanat' => 'Sanat Eseri', 'spor' => 'Spor Ürünü', 'diger' => 'Diğer'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Dosyası - Aibuke</title>
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
        .page-header h1 { font-size: 24px; font-weight: 700; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--primary); font-size: 20px; }
        .page-header p { color: var(--muted); font-size: 13px; margin-top: 6px; }
        .toast { padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; animation: toastIn 0.4s ease; }
        @keyframes toastIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 20px; padding: 20px; position: relative; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 28px; }
        .stat-card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 16px; padding: 18px; text-align: center; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-val { font-size: 28px; font-weight: 800; letter-spacing: -1px; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .stat-lbl { font-size: 11px; color: var(--muted); margin-top: 3px; font-weight: 600; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 18px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-label i { font-size: 11px; color: rgba(99,102,241,0.6); }
        .form-label .req { color: var(--error); font-size: 14px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; transition: all 0.2s; font-family: inherit; }
        .form-textarea { resize: vertical; min-height: 90px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
        .form-input::placeholder, .form-textarea::placeholder { color: rgba(124,141,176,0.5); }
        .btn { padding: 11px 24px; border-radius: 12px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; font-family: inherit; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: var(--gradient); color: #fff; box-shadow: 0 4px 16px rgba(99,102,241,0.25); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(99,102,241,0.35); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border-solid); }
        .btn-ghost:hover { color: var(--text); border-color: var(--text); }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 10px; }
        .btn-edit { background: rgba(99,102,241,0.1); color: var(--primary); }
        .btn-edit:hover { background: rgba(99,102,241,0.2); }
        .btn-del { background: rgba(239,68,68,0.1); color: var(--error); }
        .btn-del:hover { background: rgba(239,68,68,0.2); }
        .btn-circle { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; padding: 0; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-circle:hover { transform: scale(1.08); }
        table { width: 100%; border-collapse: collapse; }
        thead th { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-solid); }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover { background: rgba(99,102,241,0.04); }
        tbody td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .puan-badge { padding: 4px 10px; border-radius: 20px; font-weight: 600; }
        .puan-high { background: #d4edda; color: #155724; }
        .puan-mid { background: #fff3cd; color: #856404; }
        .puan-low { background: #f8d7da; color: #721c24; }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 200; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.25s; }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal-box { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; width: 90%; max-width: 560px; max-height: 90vh; overflow-y: auto; transform: translateY(20px); transition: transform 0.25s; }
        .modal-overlay.open .modal-box { transform: translateY(0); }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border-solid); display: flex; align-items: center; justify-content: space-between; }
        .modal-header h5 { font-size: 16px; font-weight: 700; }
        .modal-header h5 i { color: var(--primary); margin-right: 8px; }
        .modal-close { width: 32px; height: 32px; border-radius: 10px; background: rgba(255,255,255,0.05); border: none; color: var(--muted); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .modal-close:hover { background: rgba(255,255,255,0.1); color: var(--text); }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border-solid); display: flex; gap: 10px; justify-content: flex-end; }
        .filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 22px; flex-wrap: wrap; }
        .filter-input { padding: 9px 14px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 10px; color: var(--text); font-size: 13px; font-family: inherit; outline: none; min-width: 160px; }
        .filter-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        @media (max-width: 768px) { .main { margin-left: 0; padding: 72px 16px 84px; } .stats-grid { grid-template-columns: repeat(2, 1fr); } .form-row { grid-template-columns: 1fr; gap: 0; } .page-header h1 { font-size: 20px; } }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-file-earmark"></i> Ürün Dosyası</h1>
        <p>Öğrenci ürün dosyası takibi.</p>
    </div>

    <?php if ($mesaj): ?>
        <div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div>
    <?php endif; ?>

    <div class="filter-bar">
        <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <input type="text" name="ders" class="filter-input" placeholder="Ders adı..." value="<?= htmlspecialchars($filterDers) ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtrele</button>
            <?php if ($filterDers): ?>
                <a href="urun_dosyasi.php" class="btn btn-ghost btn-sm">Temizle</a>
            <?php endif; ?>
        </form>
        <button class="btn btn-primary" onclick="acFormModal()"><i class="fas fa-plus"></i> Yeni Ürün</button>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-val"><?= $stats['toplam'] ?? 0 ?></div>
            <div class="stat-lbl">Toplam Ürün</div>
        </div>
        <div class="stat-card">
            <div class="stat-val" style="-webkit-text-fill-color:transparent;background:linear-gradient(135deg,#11998e,#38ef7d);-webkit-background-clip:text;"><?= $stats['ortalama'] ? number_format($stats['ortalama'], 1) : '-' ?></div>
            <div class="stat-lbl">Ortalama Puan</div>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>Öğrenci</th>
                    <th>Sınıf</th>
                    <th>Ürün Adı</th>
                    <th>Kategori</th>
                    <th>Ders</th>
                    <th>Puan</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list)): ?>
                    <tr><td colspan="8" class="text-center py-5" style="color:var(--muted);">Kayıt bulunamadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($list as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['tarih']) ?></td>
                            <td><?= htmlspecialchars($row['ad'] . ' ' . $row['soyad']) ?></td>
                            <td><?= htmlspecialchars($row['sinif']) ?>. Sınıf</td>
                            <td><?= htmlspecialchars(mb_substr($row['dosya_adi'], 0, 40)) ?><?= mb_strlen($row['dosya_adi']) > 40 ? '...' : '' ?></td>
                            <td><?= $kategoriler[$row['kategori']] ?? ($row['kategori'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($row['ders']) ?></td>
                            <td>
                                <?php if ($row['puan'] !== null): ?>
                                    <span class="puan-badge <?= $row['puan'] >= 70 ? 'puan-high' : ($row['puan'] >= 50 ? 'puan-mid' : 'puan-low') ?>"><?= $row['puan'] ?></span>
                                <?php else: ?><span style="color:var(--muted);">-</span><?php endif; ?>
                            </td>
                            <td>
                                <button class="btn-circle btn-edit" onclick="duzenle(<?= $row['id'] ?>)" title="Düzenle"><i class="fas fa-pen"></i></button>
                                <button class="btn-circle btn-del" onclick="sil(<?= $row['id'] ?>)" title="Sil"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Form Modal -->
<div class="modal-overlay" id="formModal">
    <div class="modal-box">
        <div class="modal-header">
            <h5><i class="fas fa-file-earmark"></i> Ürün Dosyası</h5>
            <button class="modal-close" onclick="kapatFormModal()"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="kaydet">
                <input type="hidden" name="id" id="formId" value="0">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-user"></i> Öğrenci <span class="req">*</span></label>
                    <select name="ogrenci_id" id="formOgrId" class="form-select" required onchange="ogrenciSinifGoster(this)">
                        <option value="">Seçiniz</option>
                        <?php foreach ($ogrenciler as $o): ?>
                            <option value="<?= $o['id'] ?>" data-sinif="<?= $o['sinif'] ?>"><?= htmlspecialchars($o['ad'] . ' ' . $o['soyad']) ?> – <?= $o['sinif'] ?>. Sınıf</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="sinifGrup" style="display:none">
                    <label class="form-label"><i class="fas fa-school"></i> Sınıf</label>
                    <input type="text" id="formSinif" class="form-input" readonly style="opacity:0.7;cursor:default;">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-file-signature"></i> Ürün Adı</label>
                        <input type="text" name="dosya_adi" id="formDosyaAdi" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-book"></i> Ders</label>
                        <input type="text" name="ders" id="formDers" class="form-input" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-tags"></i> Kategori</label>
                    <select name="kategori" id="formKategori" class="form-select">
                        <?php foreach ($kategoriler as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Tarih</label>
                        <input type="date" name="tarih" id="formTarih" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-chart-line"></i> Puan</label>
                        <input type="number" name="puan" id="formPuan" class="form-input" min="0" max="100">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-align-left"></i> Açıklama</label>
                    <textarea name="aciklama" id="formAciklama" class="form-textarea" rows="3" placeholder="Açıklama..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="kapatFormModal()">İptal</button>
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
                <p style="color:var(--muted);font-size:14px;">Bu kaydı silmek istediğinizden emin misiniz?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="kapatSilModal()">İptal</button>
                <button type="submit" class="btn btn-del"><i class="fas fa-trash"></i> Sil</button>
            </div>
        </form>
    </div>
</div>

<script>
function ogrenciSinifGoster(sel){var opt=sel.options[sel.selectedIndex],grp=document.getElementById("sinifGrup"),inp=document.getElementById("formSinif");if(opt && opt.dataset.sinif){inp.value=opt.dataset.sinif+". Sınıf";grp.style.display=""}else{inp.value="";grp.style.display="none"}}
const veriler = <?= json_encode($list) ?>;
function acFormModal() { document.getElementById('formModal').classList.add('open'); }
function kapatFormModal() { document.getElementById('formModal').classList.remove('open'); }
function kapatSilModal() { document.getElementById('silModal').classList.remove('open'); }
function duzenle(id) {
    const r = veriler.find(x => x.id == id);
    if (!r) return;
    document.getElementById('formId').value = r.id;
    document.getElementById('formOgrId').value = r.ogrenci_id;
    document.getElementById('formDosyaAdi').value = r.dosya_adi;
    document.getElementById('formDers').value = r.ders;
    document.getElementById('formKategori').value = r.kategori || 'diger';
    document.getElementById('formTarih').value = r.tarih;
    document.getElementById('formPuan').value = r.puan ?? '';
    document.getElementById('formAciklama').value = r.aciklama || '';
    acFormModal();
}
function sil(id) { document.getElementById('silId').value = id; document.getElementById('silModal').classList.add('open'); }
</script>
</body>
</html>
