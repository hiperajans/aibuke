<?php
/**
 * Aibuke - Kazanım Değerlendirme
 * MEB kazanım kodlarına göre öğrenci değerlendirmesi.
 */

require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();
$ogretmen = getOgretmenInfo();

$userId = $ogretmen["id"];
$schoolId = $ogretmen["school_id"];

$mesaj = '';

// Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'kaydet') {
        $id = (int)$_POST['id'];
        $ogrenciId = (int)$_POST['ogrenci_id'];
        $ders = trim($_POST['ders']);
        $kazanimKodu = trim($_POST['kazanim_kodu']);
        $kazanimAciklama = trim($_POST['kazanim_aciklama']);
        $tarih = trim($_POST['degerlendirme_tarihi']);
        $puan = (int)$_POST['puan'];
        $gozlem = trim($_POST['gozlem']);
        $notlar = trim($_POST['notlar']);

        if ($id > 0) {
            $pdo->prepare("UPDATE kazanim_degerlendirme SET ogrenci_id=?, ders=?, kazanim_kodu=?, kazanim_aciklama=?, degerlendirme_tarihi=?, puan=?, gozlem=?, notlar=? WHERE id=? AND school_id=?")
                ->execute([$ogrenciId, $ders, $kazanimKodu, $kazanimAciklama, $tarih, $puan, $gozlem, $notlar, $id, $schoolId]);
            $mesaj = 'Kazanım değerlendirmesi güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO kazanim_degerlendirme (ogrenci_id, school_id, created_by, ders, kazanim_kodu, kazanim_aciklama, degerlendirme_tarihi, puan, gozlem, notlar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$ogrenciId, $schoolId, $userId, $ders, $kazanimKodu, $kazanimAciklama, $tarih, $puan, $gozlem, $notlar]);
            $mesaj = 'Kazanım değerlendirmesi eklendi.';
        }
    } elseif ($_POST['action'] === 'sil') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM kazanim_degerlendirme WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Kazanım değerlendirmesi silindi.';
    }
}

// Filtreler
$filterTarih = isset($_GET['tarih']) ? trim($_GET['tarih']) : date('Y-m-d');
$filterOgr = isset($_GET['ogrenci']) ? (int)$_GET['ogrenci'] : 0;

// Öğrenciler
$ogrenciler = $pdo->prepare("SELECT id, first_name AS ad, last_name AS soyad, class AS sinif FROM students WHERE school_id=? ORDER BY class, first_name, last_name");
$ogrenciler->execute([$schoolId]);
$ogrenciler = $ogrenciler->fetchAll(PDO::FETCH_ASSOC);

// Liste
$sql = "SELECT s.first_name AS ad, s.last_name AS soyad, s.class AS sinif, kd.* FROM kazanim_degerlendirme kd JOIN students s ON kd.ogrenci_id=s.id WHERE kd.school_id=?";
$params = [$schoolId];

if ($filterOgr) {
    $sql .= " AND kd.ogrenci_id=?";
    $params[] = $filterOgr;
}
if ($filterTarih) {
    $sql .= " AND kd.degerlendirme_tarihi=?";
    $params[] = $filterTarih;
}
$sql .= " ORDER BY kd.degerlendirme_tarihi DESC, s.first_name";

$list = $pdo->prepare($sql);
$list->execute($params);
$list = $list->fetchAll(PDO::FETCH_ASSOC);

// Ders listesi
$dersler = $pdo->query("SELECT DISTINCT ders FROM kazanim_degerlendirme WHERE school_id=$schoolId ORDER BY ders")->fetchAll(PDO::FETCH_COLUMN);

// İstatistikler
$stats = $pdo->prepare("SELECT COUNT(*) as toplam, AVG(puan) as ortalama, MAX(puan) as maxPuan, MIN(puan) as minPuan FROM kazanim_degerlendirme WHERE school_id=? AND degerlendirme_tarihi=?");
$stats->execute([$schoolId, $filterTarih]);
$stats = $stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kazanım Değerlendirme - Aibuke</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #0a0a1a;
            --surface: #101025;
            --surface-solid: #1a1a2e;
            --surface-hover: #22223a;
            --primary: #6366f1;
            --primary-dim: rgba(99, 102, 241, 0.15);
            --accent: #a78bfa;
            --accent-dim: rgba(167, 139, 250, 0.12);
            --text: #e2e8f0;
            --text-muted: #94a3b8;
            --border: rgba(255,255,255,0.06);
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa);
            --shadow: 0 8px 32px rgba(0,0,0,0.4);
            --radius: 14px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        .sidebar {
            position: fixed;
            left: 0; top: 0; bottom: 0;
            width: 240px;
            background: var(--surface-solid);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-logo { padding: 28px 24px 20px; border-bottom: 1px solid var(--border); }
        .sidebar-logo h1 { font-size: 1.1rem; font-weight: 700; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .sidebar-logo span { font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 2px; }
        .sidebar-nav { flex: 1; padding: 16px 0; }
        .nav-section { margin-bottom: 8px; }
        .nav-section-label { font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); padding: 8px 24px 4px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 24px; color: var(--text-muted); text-decoration: none; font-size: 0.875rem; font-weight: 500; transition: all 0.15s ease; }
        .nav-item i { width: 18px; text-align: center; font-size: 0.85rem; }
        .nav-item:hover { color: var(--text); background: var(--surface-hover); }
        .nav-item.active { color: #fff; background: var(--primary-dim); border-right: 2px solid var(--primary); }
        .nav-item.active i { color: var(--accent); }
        .sidebar-footer { padding: 16px 24px; border-top: 1px solid var(--border); }
        .sidebar-footer .logout { display: flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 0.85rem; font-weight: 500; padding: 8px 0; transition: color 0.15s; }
        .sidebar-footer .logout:hover { color: #f87171; }

        .main { margin-left: 240px; padding: 36px 40px; max-width: 1400px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .page-title { font-size: 1.5rem; font-weight: 700; color: #fff; }
        .page-title i { color: var(--accent); margin-right: 10px; }

        .toast { background: var(--surface-solid); border: 1px solid var(--border); border-left: 3px solid #22c55e; padding: 14px 20px; border-radius: var(--radius); margin-bottom: 24px; font-size: 0.9rem; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 28px; padding: 20px 24px; background: var(--surface-solid); border: 1px solid var(--border); border-radius: var(--radius); }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .filter-input { background: var(--surface); border: 1px solid var(--border); color: var(--text); padding: 9px 14px; border-radius: 8px; font-size: 0.875rem; font-family: inherit; outline: none; transition: border-color 0.15s; min-width: 140px; }
        .filter-input:focus { border-color: var(--primary); }
        .filter-input::placeholder { color: var(--text-muted); }
        select.filter-input { cursor: pointer; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; font-family: inherit; cursor: pointer; border: none; transition: all 0.15s ease; }
        .btn-primary { background: var(--gradient); color: #fff; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5); filter: brightness(1.1); }
        .btn-ghost { background: transparent; color: var(--text-muted); border: 1px solid var(--border); }
        .btn-ghost:hover { background: var(--surface-hover); color: var(--text); }
        .btn-sm { padding: 6px 10px; font-size: 0.8rem; }

        .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: var(--surface-solid); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px 24px; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--gradient); }
        .stat-card.toplam::before { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-card.ortalama::before { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .stat-card.max::before { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .stat-card.min::before { background: linear-gradient(135deg, #11998e, #38ef7d); }
        .stat-card h5 { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; }
        .stat-card h3 { font-size: 1.8rem; font-weight: 700; color: #fff; }

        .table-container { background: var(--surface-solid); border-radius: var(--radius); padding: 24px; border: 1px solid var(--border); }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); padding: 12px 16px; border-bottom: 1px solid var(--border); }
        .table td { padding: 14px 16px; font-size: 0.875rem; color: var(--text); border-bottom: 1px solid rgba(255,255,255,0.03); }
        .table tr:last-child td { border-bottom: none; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }

        .puan-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 0.8rem; }
        .puan-high { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .puan-mid { background: rgba(234, 179, 8, 0.15); color: #fbbf24; }
        .puan-low { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 200; opacity: 0; pointer-events: none; transition: opacity 0.25s ease; }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal-box { background: var(--surface-solid); border: 1px solid var(--border); border-radius: var(--radius); width: 90%; max-width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow); transform: translateY(20px); transition: transform 0.25s ease; }
        .modal-overlay.open .modal-box { transform: translateY(0); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--border); }
        .modal-header h3 { font-size: 1rem; font-weight: 700; color: #fff; }
        .modal-header h3 i { color: var(--accent); margin-right: 8px; }
        .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer; padding: 4px; line-height: 1; }
        .modal-close:hover { color: var(--text); }
        .modal-body { padding: 24px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .form-input { background: var(--surface); border: 1px solid var(--border); color: var(--text); padding: 10px 14px; border-radius: 8px; font-size: 0.875rem; font-family: inherit; outline: none; transition: border-color 0.15s; }
        .form-input:focus { border-color: var(--primary); }
        .form-input::placeholder { color: var(--text-muted); }
        select.form-input { cursor: pointer; }
        textarea.form-input { resize: vertical; min-height: 80px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 12px; padding: 16px 24px; border-top: 1px solid var(--border); }
        .btn-danger { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .btn-danger:hover { background: rgba(239,68,68,0.25); }

        .empty-state { text-align: center; padding: 48px 24px; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.4; }
        .empty-state p { font-size: 0.9rem; }

        .kazanim-badge { background: var(--primary-dim); color: var(--accent); padding: 3px 8px; border-radius: 6px; font-size: 0.8rem; font-family: monospace; font-weight: 600; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; padding: 20px; }
            .filter-bar { flex-direction: column; }
            .form-grid { grid-template-columns: 1fr; }
            .stat-row { grid-template-columns: 1fr 1fr; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="main">
        <div class="page-header">
            <h2 class="page-title"><i class="fas fa-bullseye"></i> Kazanım Değerlendirme</h2>
            <button class="btn btn-primary" onclick="yeni()">
                <i class="fas fa-plus"></i> Yeni Değerlendirme
            </button>
        </div>

        <?php if ($mesaj): ?>
            <div class="toast"><?= htmlspecialchars($mesaj) ?></div>
        <?php endif; ?>

        <div class="stat-row">
            <div class="stat-card toplam">
                <h5><i class="fas fa-list-check"></i> Toplam</h5>
                <h3><?= $stats['toplam'] ?? 0 ?></h3>
            </div>
            <div class="stat-card ortalama">
                <h5><i class="fas fa-chart-line"></i> Ortalama</h5>
                <h3><?= $stats['ortalama'] ? number_format($stats['ortalama'], 1) : '-' ?></h3>
            </div>
            <div class="stat-card max">
                <h5><i class="fas fa-arrow-up"></i> En Yüksek</h5>
                <h3><?= $stats['maxPuan'] ?? '-' ?></h3>
            </div>
            <div class="stat-card min">
                <h5><i class="fas fa-arrow-down"></i> En Düşük</h5>
                <h3><?= $stats['minPuan'] ?? '-' ?></h3>
            </div>
        </div>

        <div class="filter-bar">
            <form method="get" style="display: contents;">
                <div class="filter-group">
                    <label class="filter-label">Tarih</label>
                    <input type="date" name="tarih" class="filter-input" value="<?= htmlspecialchars($filterTarih) ?>">
                </div>
                <div class="filter-group" style="flex: 1;">
                    <label class="filter-label">Öğrenci</label>
                    <select name="ogrenci" class="filter-input" style="width: 100%;">
                        <option value="">Tümü</option>
                        <?php foreach ($ogrenciler as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= $filterOgr == $o['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($o['ad'] . ' ' . $o['soyad']) ?> (<?= htmlspecialchars($o['sinif']) ?>. Sınıf)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group" style="justify-content: flex-end;">
                    <button type="submit" class="btn btn-ghost"><i class="fas fa-filter"></i> Filtrele</button>
                    <a href="kazanim_degerlendirme.php" class="btn btn-ghost" style="padding: 9px 12px;"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Öğrenci</th>
                        <th>Sınıf</th>
                        <th>Ders</th>
                        <th>Kazanım Kodu</th>
                        <th>Kazanım Açıklama</th>
                        <th>Puan</th>
                        <th>Gözlem</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list)): ?>
                        <tr><td colspan="9"><div class="empty-state"><i class="fas fa-inbox"></i><p>Kayıt bulunamadı.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($list as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['degerlendirme_tarihi']) ?></td>
                                <td><?= htmlspecialchars($row['ad'] . ' ' . $row['soyad']) ?></td>
                                <td><?= htmlspecialchars($row['sinif']) ?>. Sınıf</td>
                                <td><?= htmlspecialchars($row['ders']) ?></td>
                                <td><span class="kazanim-badge"><?= htmlspecialchars($row['kazanim_kodu']) ?></span></td>
                                <td><?= htmlspecialchars(mb_substr($row['kazanim_aciklama'], 0, 60)) ?><?= mb_strlen($row['kazanim_aciklama']) > 60 ? '...' : '' ?></td>
                                <td>
                                    <span class="puan-badge <?= $row['puan'] >= 70 ? 'puan-high' : ($row['puan'] >= 50 ? 'puan-mid' : 'puan-low') ?>">
                                        <?= $row['puan'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(mb_substr($row['gozlem'], 0, 40)) ?><?= mb_strlen($row['gozlem']) > 40 ? '...' : '' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-ghost" onclick="duzenle(<?= $row['id'] ?>)" title="Düzenle">
                                        <i class="fas fa-pen" style="color: var(--primary);"></i>
                                    </button>
                                    <button class="btn btn-sm btn-ghost" onclick="sil(<?= $row['id'] ?>)" title="Sil">
                                        <i class="fas fa-trash" style="color: #f87171;"></i>
                                    </button>
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
                <h3><i class="fas fa-bullseye"></i> Kazanım Değerlendirme</h3>
                <button class="modal-close" onclick="closeModal('formModal')">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="kaydet">
                    <input type="hidden" name="id" id="formId" value="0">

                    <div class="form-grid">
                        <div class="form-group full">
                            <label class="form-label">Öğrenci</label>
                            <select name="ogrenci_id" id="formOgrId" class="form-input" required onchange="ogrenciSinifGoster(this)">
                                <option value="">Seçiniz</option>
                                <?php foreach ($ogrenciler as $o): ?>
                                    <option value="<?= $o['id'] ?>" data-sinif="<?= $o['sinif'] ?>"><?= htmlspecialchars($o['ad'] . ' ' . $o['soyad']) ?> – <?= $o['sinif'] ?>. Sınıf</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="sinifGrup" style="display:none">
                            <label class="form-label">Sınıf</label>
                            <input type="text" id="formSinif" class="form-input" readonly style="opacity:0.7;cursor:default;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Ders</label>
                            <input type="text" name="ders" id="formDers" class="form-input" required list="dersListesi">
                            <datalist id="dersListesi">
                                <?php foreach ($dersler as $d): ?>
                                    <option value="<?= htmlspecialchars($d) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tarih</label>
                            <input type="date" name="degerlendirme_tarihi" id="formTarih" class="form-input" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group full">
                            <label class="form-label">Kazanım Kodu</label>
                            <input type="text" name="kazanim_kodu" id="formKod" class="form-input" placeholder="örn: T.5.1.1" required>
                        </div>
                        <div class="form-group full">
                            <label class="form-label">Kazanım Açıklama</label>
                            <textarea name="kazanim_aciklama" id="formAciklama" class="form-input" rows="2" placeholder="Kazanım açıklaması..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Puan (0-100)</label>
                            <input type="number" name="puan" id="formPuan" class="form-input" min="0" max="100" value="0" required>
                        </div>
                        <div class="form-group full">
                            <label class="form-label">Gözlem</label>
                            <textarea name="gozlem" id="formGozlem" class="form-input" rows="2" placeholder="Gözlem notları..."></textarea>
                        </div>
                        <div class="form-group full">
                            <label class="form-label">Notlar</label>
                            <textarea name="notlar" id="formNotlar" class="form-input" rows="2" placeholder="Ek notlar..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('formModal')">İptal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sil Onay -->
    <div class="modal-overlay" id="silModal">
        <div class="modal-box" style="max-width: 400px;">
            <div class="modal-header">
                <h3><i class="fas fa-trash" style="color: #f87171;"></i> Sil</h3>
                <button class="modal-close" onclick="closeModal('silModal')">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="sil">
                    <input type="hidden" name="id" id="silId" value="0">
                    <p style="color: var(--text); font-size: 0.9rem;">Bu değerlendirmeyi silmek istediğinizden emin misiniz?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('silModal')">İptal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Sil</button>
                </div>
            </form>
        </div>
    </div>

    <script>
function ogrenciSinifGoster(sel){var opt=sel.options[sel.selectedIndex],grp=document.getElementById("sinifGrup"),inp=document.getElementById("formSinif");if(opt<script><script>opt.dataset.sinif){inp.value=opt.dataset.sinif+". Sınıf";grp.style.display=""}else{inp.value="";grp.style.display="none"}}
        const veriler = <?= json_encode($list) ?>;

        function yeni() {
            document.getElementById('formId').value = '0';
            document.getElementById('formOgrId').value = '';
            document.getElementById('formDers').value = '';
            document.getElementById('formTarih').value = '<?= date('Y-m-d') ?>';
            document.getElementById('formKod').value = '';
            document.getElementById('formAciklama').value = '';
            document.getElementById('formPuan').value = '0';
            document.getElementById('formGozlem').value = '';
            document.getElementById('formNotlar').value = '';
            openModal('formModal');
        }

        function duzenle(id) {
            const r = veriler.find(x => x.id == id);
            if (!r) return;
            document.getElementById('formId').value = r.id;
            document.getElementById('formOgrId').value = r.ogrenci_id;
            document.getElementById('formDers').value = r.ders;
            document.getElementById('formTarih').value = r.degerlendirme_tarihi;
            document.getElementById('formKod').value = r.kazanim_kodu;
            document.getElementById('formAciklama').value = r.kazanim_aciklama || '';
            document.getElementById('formPuan').value = r.puan;
            document.getElementById('formGozlem').value = r.gozlem || '';
            document.getElementById('formNotlar').value = r.notlar || '';
            openModal('formModal');
        }

        function sil(id) {
            document.getElementById('silId').value = id;
            openModal('silModal');
        }

        function openModal(id) { document.getElementById(id).classList.add('open'); }
        function closeModal(id) { document.getElementById(id).classList.remove('open'); }
        document.querySelectorAll('.modal-overlay').forEach(el => {
            el.addEventListener('click', e => { if (e.target === el) closeModal(el.id); });
        });
    </script>
</body>
</html>
