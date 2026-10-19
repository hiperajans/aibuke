<?php
/**
 * Aibuke - Yazılı/Konuşma/Dinleme Ortalama
 * Yazılı, sözlü ve dinleme notu ortalamalarını hesaplar ve yönetir.
 */

require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();
$ogretmen = getOgretmenInfo();

$userId = $ogretmen["id"];
$schoolId = $ogretmen["school_id"];

$mesaj = '';
$hata = '';

// Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'kaydet') {
        $ogrenciId = (int)$_POST['ogrenci_id'];
        $sinif = trim($_POST['sinif']);
        $ders = trim($_POST['ders']);
        $donem = (int)$_POST['donem'];
        $yil = (int)$_POST['yil'];
        $yazili1 = $_POST['yazili1'] !== '' ? (float)$_POST['yazili1'] : null;
        $yazili2 = $_POST['yazili2'] !== '' ? (float)$_POST['yazili2'] : null;
        $yazili3 = $_POST['yazili3'] !== '' ? (float)$_POST['yazili3'] : null;
        $sozlu1 = $_POST['sozlu1'] !== '' ? (float)$_POST['sozlu1'] : null;
        $sozlu2 = $_POST['sozlu2'] !== '' ? (float)$_POST['sozlu2'] : null;
        $dinleme1 = $_POST['dinleme1'] !== '' ? (float)$_POST['dinleme1'] : null;
        $dinleme2 = $_POST['dinleme2'] !== '' ? (float)$_POST['dinleme2'] : null;

        // Ortalama hesapla
        $notlar = array_filter([$yazili1, $yazili2, $yazili3, $sozlu1, $sozlu2, $dinleme1, $dinleme2]);
        $ortalama = !empty($notlar) ? array_sum($notlar) / count($notlar) : null;

        $id = (int)$_POST['id'];

        if ($id > 0) {
            $pdo->prepare("UPDATE yazili_ortalama SET sinif=?, ders=?, donem=?, yil=?, yazili1=?, yazili2=?, yazili3=?, sozlu1=?, sozlu2=?, dinleme1=?, dinleme2=?, ortalama=?, updated_at=NOW() WHERE id=? AND school_id=?")
                ->execute([$sinif, $ders, $donem, $yil, $yazili1, $yazili2, $yazili3, $sozlu1, $sozlu2, $dinleme1, $dinleme2, $ortalama, $id, $schoolId]);
            $mesaj = 'Not ortalama güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO yazili_ortalama (ogrenci_id, school_id, created_by, sinif, ders, donem, yil, yazili1, yazili2, yazili3, sozlu1, sozlu2, dinleme1, dinleme2, ortalama) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$ogrenciId, $schoolId, $userId, $sinif, $ders, $donem, $yil, $yazili1, $yazili2, $yazili3, $sozlu1, $sozlu2, $dinleme1, $dinleme2, $ortalama]);
            $mesaj = 'Not ortalama eklendi.';
        }
    } elseif ($_POST['action'] === 'sil') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM yazili_ortalama WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Not silindi.';
    }
}

// Filtreler
$filterDonem = isset($_GET['donem']) ? (int)$_GET['donem'] : (date('n') <= 6 ? 2 : 1);
$filterYil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');
$filterSinif = isset($_GET['sinif']) ? trim($_GET['sinif']) : '';
$filterDers = isset($_GET['ders']) ? trim($_GET['ders']) : '';

// Sınıflar
$siniflar = $pdo->query("SELECT DISTINCT class FROM students WHERE school_id=$schoolId AND class IS NOT NULL ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
if (empty($siniflar)) $siniflar = ['9','10','11','12'];

// Öğrenciler (dropdown için)
$ogrenciler = $pdo->prepare("SELECT id, first_name AS ad, last_name AS soyad FROM students WHERE school_id=? ORDER BY class, first_name, last_name");
$ogrenciler->execute([$schoolId]);
$ogrenciler = $ogrenciler->fetchAll(PDO::FETCH_ASSOC);

// Liste
$sql = "SELECT s.first_name AS ad, s.last_name AS soyad, yo.* FROM yazili_ortalama yo JOIN students s ON yo.ogrenci_id=s.id WHERE yo.school_id=? AND yo.donem=? AND yo.yil=?";
$params = [$schoolId, $filterDonem, $filterYil];

if ($filterSinif) {
    $sql .= " AND yo.sinif=?";
    $params[] = $filterSinif;
}
if ($filterDers) {
    $sql .= " AND yo.ders LIKE ?";
    $params[] = "%$filterDers%";
}
$sql .= " ORDER BY yo.sinif, s.first_name, yo.ders";

$list = $pdo->prepare($sql);
$list->execute($params);
$list = $list->fetchAll(PDO::FETCH_ASSOC);

// Ders listesi (filter için)
$dersler = $pdo->prepare("SELECT DISTINCT ders FROM yazili_ortalama WHERE school_id=? ORDER BY ders");
$dersler->execute([$schoolId]);
$dersler = $dersler->fetchAll(PDO::FETCH_COLUMN);

// İstatistikler
$stats = $pdo->prepare("SELECT COUNT(*) as toplam, AVG(ortalama) as ortalama FROM yazili_ortalama WHERE school_id=? AND donem=? AND yil=?");
$stats->execute([$schoolId, $filterDonem, $filterYil]);
$stats = $stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Not Ortalama - Aibuke</title>
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
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        body { background: var(--bg); }

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
        .sidebar-logo {
            padding: 28px 24px 20px;
            border-bottom: 1px solid var(--border);
        }
        .sidebar-logo h1 {
            font-size: 1.1rem;
            font-weight: 700;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .sidebar-logo span {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: block;
            margin-top: 2px;
        }
        .sidebar-nav { flex: 1; padding: 16px 0; }
        .nav-section { margin-bottom: 8px; }
        .nav-section-label {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            padding: 8px 24px 4px;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 24px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0;
            transition: all 0.15s ease;
        }
        .nav-item i { width: 18px; text-align: center; font-size: 0.85rem; }
        .nav-item:hover { color: var(--text); background: var(--surface-hover); }
        .nav-item.active { color: #fff; background: var(--primary-dim); border-right: 2px solid var(--primary); }
        .nav-item.active i { color: var(--accent); }
        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
        }
        .sidebar-footer .logout {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 8px 0;
            transition: color 0.15s;
        }
        .sidebar-footer .logout:hover { color: #f87171; }

        .main {
            margin-left: 240px;
            padding: 36px 40px;
            max-width: 1400px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
        }
        .page-title i { color: var(--accent); margin-right: 10px; }

        .toast {
            background: var(--surface-solid);
            border: 1px solid var(--border);
            border-left: 3px solid #22c55e;
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            font-size: 0.9rem;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .filter-bar {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 28px;
            padding: 20px 24px;
            background: var(--surface-solid);
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .filter-input {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 9px 14px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.15s;
            min-width: 140px;
        }
        .filter-input:focus { border-color: var(--primary); }
        .filter-input::placeholder { color: var(--text-muted); }
        select.filter-input { cursor: pointer; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
        }
        .btn-primary {
            background: var(--gradient);
            color: #fff;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5); filter: brightness(1.1); }
        .btn-ghost {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { background: var(--surface-hover); color: var(--text); }
        .btn-sm { padding: 6px 10px; font-size: 0.8rem; }

        .stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: var(--surface-solid);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 24px;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--gradient);
        }
        .stat-card.yazili::before { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-card.sozlu::before { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .stat-card.dinleme::before { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .stat-card.ortalama::before { background: linear-gradient(135deg, #11998e, #38ef7d); }
        .stat-card h5 { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; }
        .stat-card h3 { font-size: 1.8rem; font-weight: 700; color: #fff; }

        .card {
            background: var(--surface-solid);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .table-container { background: var(--surface-solid); border-radius: var(--radius); padding: 24px; border: 1px solid var(--border); }
        .table { width: 100%; border-collapse: collapse; }
        .table th {
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }
        .table td {
            padding: 14px 16px;
            font-size: 0.875rem;
            color: var(--text);
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .table tr:last-child td { border-bottom: none; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }

        .puan-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .puan-high { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .puan-mid { background: rgba(234, 179, 8, 0.15); color: #fbbf24; }
        .puan-low { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 200;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal-box {
            background: var(--surface-solid);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow);
            transform: translateY(20px);
            transition: transform 0.25s ease;
        }
        .modal-overlay.open .modal-box { transform: translateY(0); }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }
        .modal-header h3 { font-size: 1rem; font-weight: 700; color: #fff; }
        .modal-header h3 i { color: var(--accent); margin-right: 8px; }
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
        }
        .modal-close:hover { color: var(--text); }
        .modal-body { padding: 24px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .form-input {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.15s;
        }
        .form-input:focus { border-color: var(--primary); }
        .form-input::placeholder { color: var(--text-muted); }
        select.form-input { cursor: pointer; }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 16px 24px;
            border-top: 1px solid var(--border);
        }
        .btn-danger { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .btn-danger:hover { background: rgba(239,68,68,0.25); }

        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.4; }
        .empty-state p { font-size: 0.9rem; }

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
            <h2 class="page-title"><i class="fas fa-calculator"></i> Not Ortalama</h2>
            <button class="btn btn-primary" onclick="yeni()">
                <i class="fas fa-plus"></i> Yeni Not
            </button>
        </div>

        <?php if ($mesaj): ?>
            <div class="toast"><?= htmlspecialchars($mesaj) ?></div>
        <?php endif; ?>

        <div class="stat-row">
            <div class="stat-card yazili">
                <h5><i class="fas fa-pen"></i> Yazılı Notları</h5>
                <h3><?= $stats['toplam'] ?? 0 ?></h3>
            </div>
            <div class="stat-card sozlu">
                <h5><i class="fas fa-comments"></i> Sözlü Notları</h5>
                <h3><?= $stats['toplam'] ?? 0 ?></h3>
            </div>
            <div class="stat-card dinleme">
                <h5><i class="fas fa-headphones"></i> Dinleme Notları</h5>
                <h3><?= $stats['toplam'] ?? 0 ?></h3>
            </div>
            <div class="stat-card ortalama">
                <h5><i class="fas fa-chart-line"></i> Genel Ortalama</h5>
                <h3><?= $stats['ortalama'] ? number_format($stats['ortalama'], 1) : '-' ?></h3>
            </div>
        </div>

        <div class="filter-bar">
            <form method="get" style="display: contents;">
                <div class="filter-group">
                    <label class="filter-label">Dönem</label>
                    <select name="donem" class="filter-input">
                        <option value="1" <?= $filterDonem == 1 ? 'selected' : '' ?>>1. Dönem</option>
                        <option value="2" <?= $filterDonem == 2 ? 'selected' : '' ?>>2. Dönem</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Yıl</label>
                    <input type="number" name="yil" class="filter-input" value="<?= $filterYil ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Sınıf</label>
                    <select name="sinif" class="filter-input">
                        <option value="">Tümü</option>
                        <?php foreach ($siniflar as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= $filterSinif == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?>. Sınıf</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Ders</label>
                    <input type="text" name="ders" class="filter-input" placeholder="Ders adı..." value="<?= htmlspecialchars($filterDers) ?>">
                </div>
                <div class="filter-group" style="justify-content: flex-end;">
                    <button type="submit" class="btn btn-ghost"><i class="fas fa-filter"></i> Filtrele</button>
                    <a href="not_ortalama.php" class="btn btn-ghost" style="padding: 9px 12px;"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Öğrenci</th>
                        <th>Sınıf</th>
                        <th>Ders</th>
                        <th>Yazılı 1</th>
                        <th>Yazılı 2</th>
                        <th>Yazılı 3</th>
                        <th>Sözlü 1</th>
                        <th>Sözlü 2</th>
                        <th>Dinleme 1</th>
                        <th>Dinleme 2</th>
                        <th>Ortalama</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list)): ?>
                        <tr>
                            <td colspan="12">
                                <div class="empty-state"><i class="fas fa-inbox"></i><p>Kayıt bulunamadı.</p></div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($list as $row): ?>
                            <?php $ort = (float)$row['ortalama']; ?>
                            <tr>
                                <td><?= htmlspecialchars($row['ad'] . ' ' . $row['soyad']) ?></td>
                                <td><?= htmlspecialchars($row['sinif']) ?>. Sınıf</td>
                                <td><?= htmlspecialchars($row['ders']) ?></td>
                                <td><?= $row['yazili1'] !== null ? number_format($row['yazili1'], 1) : '-' ?></td>
                                <td><?= $row['yazili2'] !== null ? number_format($row['yazili2'], 1) : '-' ?></td>
                                <td><?= $row['yazili3'] !== null ? number_format($row['yazili3'], 1) : '-' ?></td>
                                <td><?= $row['sozlu1'] !== null ? number_format($row['sozlu1'], 1) : '-' ?></td>
                                <td><?= $row['sozlu2'] !== null ? number_format($row['sozlu2'], 1) : '-' ?></td>
                                <td><?= $row['dinleme1'] !== null ? number_format($row['dinleme1'], 1) : '-' ?></td>
                                <td><?= $row['dinleme2'] !== null ? number_format($row['dinleme2'], 1) : '-' ?></td>
                                <td>
                                    <?php if ($ort > 0): ?>
                                        <span class="puan-badge <?= $ort >= 70 ? 'puan-high' : ($ort >= 50 ? 'puan-mid' : 'puan-low') ?>">
                                            <?= number_format($ort, 2) ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
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
                <h3><i class="fas fa-calculator"></i> Not Ortalama</h3>
                <button class="modal-close" onclick="closeModal('formModal')">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="kaydet">
                    <input type="hidden" name="id" id="formId" value="0">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Öğrenci</label>
                            <select name="ogrenci_id" id="formOgrenciId" class="form-input" required onchange="ogrenciSinifGoster(this)">
                                <option value="">Seçiniz</option>
                                <?php foreach ($ogrenciler as $o): ?>
                                    <option value="<?= $o['id'] ?>" data-sinif="<?= $o['sinif'] ?>"><?= htmlspecialchars($o['ad'] . ' ' . $o['soyad']) ?> – <?= $o['sinif'] ?>. Sınıf</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sınıf</label>
                            <select name="sinif" id="formSinif" class="form-input" required>
                                <?php foreach ($siniflar as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?>. Sınıf</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Ders</label>
                            <input type="text" name="ders" id="formDers" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dönem</label>
                            <select name="donem" id="formDonem" class="form-input">
                                <option value="1" <?= $filterDonem == 1 ? 'selected' : '' ?>>1. Dönem</option>
                                <option value="2" <?= $filterDonem == 2 ? 'selected' : '' ?>>2. Dönem</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Yıl</label>
                            <input type="number" name="yil" id="formYil" class="form-input" value="<?= $filterYil ?>">
                        </div>
                    </div>

                    <div style="margin: 20px 0; border-top: 1px solid var(--border); padding-top: 20px;">
                        <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 14px;">
                            <i class="fas fa-pen" style="color: var(--primary); margin-right: 6px;"></i> Yazılı Notları
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Yazılı 1</label>
                                <input type="number" name="yazili1" id="formYazili1" class="form-input" min="0" max="100" step="0.1" placeholder="0-100">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Yazılı 2</label>
                                <input type="number" name="yazili2" id="formYazili2" class="form-input" min="0" max="100" step="0.1" placeholder="0-100">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Yazılı 3</label>
                                <input type="number" name="yazili3" id="formYazili3" class="form-input" min="0" max="100" step="0.1" placeholder="0-100">
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 14px;">
                            <i class="fas fa-comments" style="color: var(--accent); margin-right: 6px;"></i> Sözlü Notları
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Sözlü 1</label>
                                <input type="number" name="sozlu1" id="formSozlu1" class="form-input" min="0" max="100" step="0.1" placeholder="0-100">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Sözlü 2</label>
                                <input type="number" name="sozlu2" id="formSozlu2" class="form-input" min="0" max="100" step="0.1" placeholder="0-100">
                            </div>
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 14px;">
                            <i class="fas fa-headphones" style="color: #4facfe; margin-right: 6px;"></i> Dinleme Notları
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Dinleme 1</label>
                                <input type="number" name="dinleme1" id="formDinleme1" class="form-input" min="0" max="100" step="0.1" placeholder="0-100">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Dinleme 2</label>
                                <input type="number" name="dinleme2" id="formDinleme2" class="form-input" min="0" max="100" step="0.1" placeholder="0-100">
                            </div>
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
                <h3><i class="fas fa-trash" style="color: #f87171;"></i> Not Sil</h3>
                <button class="modal-close" onclick="closeModal('silModal')">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="sil">
                    <input type="hidden" name="id" id="silId" value="0">
                    <p style="color: var(--text); font-size: 0.9rem;">Bu notu silmek istediğinizden emin misiniz?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('silModal')">İptal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Sil</button>
                </div>
            </form>
        </div>
    </div>

    <script>
function ogrenciSinifGoster(sel){var opt=sel.options[sel.selectedIndex],grp=document.getElementById("sinifGrup"),inp=document.getElementById("formSinif");if(opt && opt.dataset.sinif){inp.value=opt.dataset.sinif+". Sınıf";grp.style.display=""}else{inp.value="";grp.style.display="none"}}
        const notVerileri = <?= json_encode($list) ?>;

        function yeni() {
            document.getElementById('formId').value = '0';
            document.getElementById('formOgrenciId').value = '';
            document.getElementById('formSinif').value = '<?= htmlspecialchars($siniflar[0] ?? '') ?>';
            document.getElementById('formDers').value = '';
            document.getElementById('formDonem').value = '<?= $filterDonem ?>';
            document.getElementById('formYil').value = '<?= $filterYil ?>';
            document.getElementById('formYazili1').value = '';
            document.getElementById('formYazili2').value = '';
            document.getElementById('formYazili3').value = '';
            document.getElementById('formSozlu1').value = '';
            document.getElementById('formSozlu2').value = '';
            document.getElementById('formDinleme1').value = '';
            document.getElementById('formDinleme2').value = '';
            openModal('formModal');
        }

        function duzenle(id) {
            const r = notVerileri.find(x => x.id == id);
            if (!r) return;
            document.getElementById('formId').value = r.id;
            document.getElementById('formOgrenciId').value = r.ogrenci_id;
            document.getElementById('formSinif').value = r.sinif;
            document.getElementById('formDers').value = r.ders;
            document.getElementById('formDonem').value = r.donem;
            document.getElementById('formYil').value = r.yil;
            document.getElementById('formYazili1').value = r.yazili1 ?? '';
            document.getElementById('formYazili2').value = r.yazili2 ?? '';
            document.getElementById('formYazili3').value = r.yazili3 ?? '';
            document.getElementById('formSozlu1').value = r.sozlu1 ?? '';
            document.getElementById('formSozlu2').value = r.sozlu2 ?? '';
            document.getElementById('formDinleme1').value = r.dinleme1 ?? '';
            document.getElementById('formDinleme2').value = r.dinleme2 ?? '';
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
