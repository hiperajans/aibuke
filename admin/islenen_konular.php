<?php
/**
 * Aibuke Admin - İşlenen Konular
 */
require_once __DIR__ . '/auth.php';
session_start();
requireAdminLogin();
$admin    = getAdminInfo();
$schoolId = $admin['school_id'];

// Red flag sayısı (sidebar badge için)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_sentiment_alerts sa JOIN students s ON sa.student_id = s.id WHERE s.school_id = ? AND sa.is_resolved = 0");
$stmt->execute([$schoolId]);
$redFlagCount = (int) $stmt->fetchColumn();

// Filtreler
$filtreTarih   = $_GET['tarih']    ?? date('Y-m-d');
$filtreOgretmen = (int)($_GET['ogretmen'] ?? 0);
$filtreSinif   = $_GET['sinif']    ?? '';
$filtreDers    = $_GET['ders']     ?? '';

// Öğretmen listesi (filtre dropdown için)
$stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.full_name, b.branch_name as ders
    FROM staff s
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE s.school_id = ? AND s.role = 'teacher'
    ORDER BY s.full_name
");
$stmt->execute([$schoolId]);
$ogretmenler = $stmt->fetchAll();

// Sınıf listesi
$stmt = $pdo->prepare("SELECT DISTINCT class FROM students WHERE school_id = ? ORDER BY class");
$stmt->execute([$schoolId]);
$siniflar = $stmt->fetchAll(PDO::FETCH_COLUMN);

// İşlenen konular sorgusu
$sql = "
    SELECT ik.*, s.full_name as ogretmen_adi, b.branch_name
    FROM islenen_konular ik
    JOIN staff s ON ik.ogretmen_id = s.id
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE ik.school_id = ?
";
$params = [$schoolId];

if ($filtreTarih) {
    $sql .= " AND ik.ders_tarihi = ?";
    $params[] = $filtreTarih;
}
if ($filtreOgretmen) {
    $sql .= " AND ik.ogretmen_id = ?";
    $params[] = $filtreOgretmen;
}
if ($filtreSinif) {
    $sql .= " AND ik.sinif = ?";
    $params[] = $filtreSinif;
}
if ($filtreDers) {
    $escapedDers = str_replace(['_', '%'], ['\_', '\%'], $filtreDers);
    $sql .= " AND ik.ders LIKE ?";
    $params[] = "%{$escapedDers}%";
}

$sql .= " ORDER BY ik.ders_tarihi DESC, ik.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$konular = $stmt->fetchAll();

// Son 30 günde girilen toplam konu sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) FROM islenen_konular WHERE school_id = ? AND ders_tarihi >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute([$schoolId]);
$toplamKonu = (int) $stmt->fetchColumn();

// Bugün konu giren öğretmen sayısı
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT ogretmen_id) FROM islenen_konular WHERE school_id = ? AND ders_tarihi = CURDATE()");
$stmt->execute([$schoolId]);
$bugunGiren = (int) $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İşlenen Konular — Aibüke Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0f0f23; --surface: #1a1a2e; --border: #2d2d44;
            --text: #e2e8f0; --muted: #94a3b8;
            --primary: #6366f1; --accent: #ec4899;
            --success: #22c55e; --error: #ef4444; --warning: #f59e0b;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .page-header h1 { font-size: 22px; font-weight: 600; }
        .page-header .meta { color: var(--muted); font-size: 13px; }

        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 22px; }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
            padding: 16px 18px; display: flex; align-items: center; gap: 14px;
        }
        .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px; }
        .stat-icon.blue { background: rgba(99,102,241,0.15); color: var(--primary); }
        .stat-icon.green { background: rgba(34,197,94,0.15); color: var(--success); }
        .stat-icon.yellow { background: rgba(245,158,11,0.15); color: var(--warning); }
        .stat-info .stat-value { font-size: 20px; font-weight: 700; }
        .stat-info .stat-label { font-size: 12px; color: var(--muted); margin-top: 2px; }

        .filter-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
            padding: 16px 20px; margin-bottom: 20px;
        }
        .filter-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 12px; color: var(--muted); }
        .filter-group input,
        .filter-group select {
            padding: 8px 12px; background: #0f0f23; border: 1px solid var(--border);
            border-radius: 8px; color: var(--text); font-size: 13px; outline: none; min-width: 150px;
        }
        .filter-group input:focus,
        .filter-group select:focus { border-color: var(--primary); }

        .btn { padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: #5558e6; }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
        .btn-ghost:hover { color: var(--text); border-color: var(--text); }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .card-header { padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { font-size: 14px; font-weight: 600; }
        .card-header small { color: var(--muted); font-size: 12px; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; color: var(--muted); font-weight: 500; padding: 10px 16px; border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.15); }
        td { padding: 12px 16px; border-bottom: 1px solid var(--border); vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(99,102,241,0.04); }

        .badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-blue  { background: rgba(99,102,241,0.15); color: var(--primary); }
        .badge-green { background: rgba(34,197,94,0.15); color: var(--success); }
        .badge-pink  { background: rgba(236,72,153,0.15); color: var(--accent); }

        .konu-baslik { font-weight: 600; margin-bottom: 4px; }
        .konu-detay  { color: var(--muted); font-size: 12px; margin-top: 3px; }
        .konu-kazanim { color: #a78bfa; font-size: 11px; margin-top: 3px; }
        .konu-odev   { color: var(--warning); font-size: 11px; margin-top: 3px; }

        .empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
        .empty-state i { font-size: 36px; opacity: 0.3; margin-bottom: 12px; display: block; }
        .empty-state p { font-size: 14px; }

        details summary { cursor: pointer; list-style: none; }
        details summary::-webkit-details-marker { display: none; }
        details[open] .detail-arrow { transform: rotate(90deg); }
        .detail-arrow { display: inline-block; transition: transform 0.15s; margin-right: 4px; font-size: 10px; }
    </style>
    </style>
    <link rel="stylesheet" href="/admin/assets/mobile.css?v=<?= filemtime(__DIR__.'/assets/mobile.css') ?>">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-chalkboard-teacher" style="color:var(--primary);margin-right:8px;"></i>İşlenen Konular</h1>
            <p class="meta">Öğretmenlerin sınıfta işlediği konuların kaydı</p>
        </div>
    </div>

    <!-- İstatistikler -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-book-open"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $toplamKonu ?></div>
                <div class="stat-label">Son 30 Günde İşlenen Konu</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $bugunGiren ?></div>
                <div class="stat-label">Bugün Konu Giren Öğretmen</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= count($ogretmenler) ?></div>
                <div class="stat-label">Toplam Öğretmen</div>
            </div>
        </div>
    </div>

    <!-- Filtreler -->
    <div class="filter-card">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Tarih</label>
                <input type="date" name="tarih" value="<?= htmlspecialchars($filtreTarih) ?>">
            </div>
            <div class="filter-group">
                <label>Öğretmen</label>
                <select name="ogretmen">
                    <option value="">Tüm Öğretmenler</option>
                    <?php foreach ($ogretmenler as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $filtreOgretmen == $o['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($o['full_name']) ?> (<?= htmlspecialchars($o['ders'] ?? '?') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Sınıf</label>
                <select name="sinif">
                    <option value="">Tüm Sınıflar</option>
                    <?php foreach ($siniflar as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= $filtreSinif === $s ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrele</button>
                <a href="/admin/islenen_konular.php" class="btn btn-ghost">Temizle</a>
            </div>
        </form>
    </div>

    <!-- Konu Tablosu -->
    <div class="card">
        <div class="card-header">
            <h3>
                <?php if ($filtreTarih): ?>
                    <?= date('d F Y', strtotime($filtreTarih)) ?> tarihinde işlenen konular
                <?php else: ?>
                    Tüm İşlenen Konular
                <?php endif; ?>
            </h3>
            <small><?= count($konular) ?> kayıt</small>
        </div>

        <?php if (empty($konular)): ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <p>Bu tarihte işlenen konu kaydı bulunamadı.<br>
                <?php if ($filtreTarih === date('Y-m-d')): ?>
                    Öğretmenler bugünkü konuları girmemiş olabilir.
                <?php else: ?>
                    Farklı bir tarih veya filtre deneyin.
                <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width:90px">Tarih</th>
                    <th style="width:160px">Öğretmen</th>
                    <th style="width:70px">Sınıf</th>
                    <th>Konu</th>
                    <th style="width:100px">Kaydedilme</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($konular as $k): ?>
                <tr>
                    <td><?= date('d.m.Y', strtotime($k['ders_tarihi'])) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($k['ogretmen_adi']) ?></strong><br>
                        <span class="badge badge-pink"><?= htmlspecialchars($k['ders'] ?? '?') ?></span>
                    </td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($k['sinif']) ?></span></td>
                    <td>
                        <details>
                            <summary>
                                <span class="detail-arrow">▶</span>
                                <span class="konu-baslik"><?= htmlspecialchars($k['konu_basligi']) ?></span>
                            </summary>
                            <?php if ($k['konu_detay']): ?>
                                <div class="konu-detay" style="margin-top:8px;padding-left:14px;">
                                    <strong style="color:var(--muted);">Konu Detayı:</strong><br>
                                    <?= nl2br(htmlspecialchars($k['konu_detay'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($k['kazanimlar']): ?>
                                <div class="konu-kazanim" style="padding-left:14px;margin-top:6px;">
                                    <i class="fas fa-bullseye"></i> <strong>Kazanımlar:</strong> <?= nl2br(htmlspecialchars($k['kazanimlar'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($k['ev_odevi']): ?>
                                <div class="konu-odev" style="padding-left:14px;margin-top:6px;">
                                    <i class="fas fa-home"></i> <strong>Ev Ödevi:</strong> <?= nl2br(htmlspecialchars($k['ev_odevi'])) ?>
                                </div>
                            <?php endif; ?>
                        </details>
                    </td>
                    <td style="color:var(--muted);font-size:12px;">
                        <?= date('H:i', strtotime($k['created_at'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<script src="/admin/assets/mobile.js?v=<?= filemtime(__DIR__.'/assets/mobile.js') ?>"></script>
</body>
</html>
