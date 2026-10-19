<?php
require_once __DIR__ . '/auth.php';
session_start();
requireAdminLogin();
$admin = getAdminInfo();
$schoolId = $admin['school_id'];

$classFilter = $_GET['class'] ?? '';

$query = "
    SELECT s.id, s.first_name, s.last_name, s.class, s.nickname,
           sp.exam_type, sp.learning_style, sp.target_profession,
           COALESCE(pt.total_points, 0) as total_points,
           du.last_active, du.total_msgs,
           COALESCE(sa.open_alerts, 0) as open_alerts
    FROM students s
    JOIN aibuke_student_profiles sp ON s.id = sp.student_id
    LEFT JOIN (
        SELECT student_id, SUM(points) as total_points
        FROM aibuke_points GROUP BY student_id
    ) pt ON s.id = pt.student_id
    LEFT JOIN (
        SELECT student_id, MAX(usage_date) as last_active, SUM(message_count) as total_msgs
        FROM aibuke_daily_usage GROUP BY student_id
    ) du ON s.id = du.student_id
    LEFT JOIN (
        SELECT student_id, COUNT(*) as open_alerts
        FROM aibuke_sentiment_alerts WHERE is_resolved = 0 GROUP BY student_id
    ) sa ON s.id = sa.student_id
    WHERE s.school_id = ?
";
$params = [$schoolId];
if ($classFilter) {
    $query .= " AND s.class = ?";
    $params[] = $classFilter;
}
$query .= " ORDER BY s.class, s.first_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sınıf listesi
$stmtC = $pdo->prepare("SELECT DISTINCT s.class FROM students s JOIN aibuke_student_profiles sp ON s.id = sp.student_id WHERE s.school_id = ? ORDER BY s.class");
$stmtC->execute([$schoolId]);
$classes = $stmtC->fetchAll(PDO::FETCH_COLUMN);

// Red flag sayısı (sidebar için)
$stmtRF = $pdo->prepare("SELECT COUNT(*) FROM aibuke_sentiment_alerts sa JOIN students s ON sa.student_id = s.id WHERE s.school_id = ? AND sa.is_resolved = 0");
$stmtRF->execute([$schoolId]);
$redFlagCount = (int) $stmtRF->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğrenciler — Aibüke Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0f0f23; --surface: #1a1a2e; --border: #2d2d44; --text: #e2e8f0; --muted: #94a3b8; --primary: #6366f1; --accent: #ec4899; --success: #22c55e; --error: #ef4444; --warning: #f59e0b; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .page-header h1 { font-size: 22px; }
        .filters { display: flex; gap: 8px; align-items: center; }
        .filter-btn { padding: 6px 14px; border: 1px solid var(--border); border-radius: 20px; background: transparent; color: var(--muted); font-size: 12px; cursor: pointer; text-decoration: none; transition: all 0.15s; }
        .filter-btn:hover { border-color: var(--primary); color: var(--primary); }
        .filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; color: var(--muted); font-weight: 500; padding: 12px 14px; border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.2); }
        td { padding: 11px 14px; border-bottom: 1px solid var(--border); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(99,102,241,0.05); }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-red { background: rgba(239,68,68,0.15); color: var(--error); }
        .badge-green { background: rgba(34,197,94,0.15); color: var(--success); }
        .badge-blue { background: rgba(99,102,241,0.15); color: var(--primary); }
        .badge-yellow { background: rgba(245,158,11,0.15); color: var(--warning); }
        .text-muted { color: var(--muted); }
    </style>
    </head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="page-header">
            <h1>Öğrenciler <small class="text-muted" style="font-size:14px;font-weight:400;">(<?= count($students) ?>)</small></h1>
            <div class="filters">
                <a class="filter-btn <?= !$classFilter ? 'active' : '' ?>" href="?">Tümü</a>
                <?php foreach ($classes as $c): ?>
                <a class="filter-btn <?= $classFilter === $c ? 'active' : '' ?>" href="?class=<?= urlencode($c) ?>"><?= htmlspecialchars($c) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Öğrenci</th>
                        <th>Sınıf</th>
                        <th>Sınav</th>
                        <th>Öğrenme Stili</th>
                        <th>Mesaj</th>
                        <th>Puan</th>
                        <th>Son Giriş</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $st): ?>
                    <tr>
                        <td><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></td>
                        <td><?= htmlspecialchars($st['class']) ?></td>
                        <td><span class="badge badge-blue"><?= htmlspecialchars(strtoupper($st['exam_type'])) ?></span></td>
                        <td>
                            <?php
                            $styleNames = ['gorsel'=>'Görsel','isitsel'=>'İşitsel','okuma_yazma'=>'Okuma/Yazma','kinestetik'=>'Kinestetik'];
                            echo $st['learning_style'] ? ($styleNames[$st['learning_style']] ?? $st['learning_style']) : '<span class="text-muted">—</span>';
                            ?>
                        </td>
                        <td><?= $st['total_msgs'] ?? 0 ?></td>
                        <td><?= $st['total_points'] ?></td>
                        <td><?= $st['last_active'] ? date('d/m', strtotime($st['last_active'])) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <?php if ($st['open_alerts'] > 0): ?>
                                <span class="badge badge-red"><i class="fas fa-flag"></i> <?= $st['open_alerts'] ?></span>
                            <?php else: ?>
                                <span class="badge badge-green">Normal</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="/admin/assets/mobile.js?v=<?= filemtime(__DIR__.'/assets/mobile.js') ?>"></script>
</body>
</html>
