<?php
/**
 * Aibuke Admin - Dashboard
 */
require_once __DIR__ . '/auth.php';
session_start();
requireAdminLogin();
$admin = getAdminInfo();
$schoolId = $admin['school_id'];

// İstatistikler
$stats = [];

// Aktif öğrenci sayısı (son 7 gün)
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM aibuke_daily_usage WHERE student_id IN (SELECT id FROM students WHERE school_id = ?) AND usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute([$schoolId]);
$stats['active_students'] = (int) $stmt->fetchColumn();

// Toplam öğrenci
$stmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_student_profiles sp JOIN students s ON sp.student_id = s.id WHERE s.school_id = ?");
$stmt->execute([$schoolId]);
$stats['total_students'] = (int) $stmt->fetchColumn();

// Bugünkü mesaj sayısı
$stmt = $pdo->prepare("SELECT COALESCE(SUM(message_count), 0) FROM aibuke_daily_usage WHERE student_id IN (SELECT id FROM students WHERE school_id = ?) AND usage_date = CURDATE()");
$stmt->execute([$schoolId]);
$stats['today_messages'] = (int) $stmt->fetchColumn();

// Red Flag sayısı (çözülmemiş)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_sentiment_alerts sa JOIN students s ON sa.student_id = s.id WHERE s.school_id = ? AND sa.is_resolved = 0");
$stmt->execute([$schoolId]);
$stats['red_flags'] = (int) $stmt->fetchColumn();
$redFlagCount = $stats['red_flags'];

// Ortalama duygu durumu (son 7 gün)
$stmt = $pdo->prepare("SELECT ROUND(AVG(m.sentiment_score), 2) FROM aibuke_messages m JOIN students s ON m.student_id = s.id WHERE s.school_id = ? AND m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND m.role = 'aibuke'");
$stmt->execute([$schoolId]);
$stats['avg_sentiment'] = (float) $stmt->fetchColumn();

// Son 7 gün günlük mesaj sayıları (grafik için)
$stmt = $pdo->prepare("SELECT du.usage_date, SUM(du.message_count) as total FROM aibuke_daily_usage du JOIN students s ON du.student_id = s.id WHERE s.school_id = ? AND du.usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY du.usage_date ORDER BY du.usage_date");
$stmt->execute([$schoolId]);
$dailyMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sınıf bazlı öğrenci dağılımı
$stmt = $pdo->prepare("SELECT s.class, COUNT(*) as count FROM aibuke_student_profiles sp JOIN students s ON sp.student_id = s.id WHERE s.school_id = ? GROUP BY s.class ORDER BY s.class");
$stmt->execute([$schoolId]);
$classDist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// En aktif 5 öğrenci
$stmt = $pdo->prepare("SELECT s.first_name, s.last_name, s.class, SUM(du.message_count) as total_msgs, MAX(du.usage_date) as last_active FROM aibuke_daily_usage du JOIN students s ON du.student_id = s.id WHERE s.school_id = ? AND du.usage_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY du.student_id ORDER BY total_msgs DESC LIMIT 5");
$stmt->execute([$schoolId]);
$topStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Red Flag listesi
$stmt = $pdo->prepare("SELECT sa.*, s.first_name, s.last_name, s.class FROM aibuke_sentiment_alerts sa JOIN students s ON sa.student_id = s.id WHERE s.school_id = ? AND sa.is_resolved = 0 ORDER BY sa.created_at DESC LIMIT 10");
$stmt->execute([$schoolId]);
$redFlags = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aibüke Admin — <?= htmlspecialchars($admin['school']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --bg: #0f0f23; --surface: #1a1a2e; --border: #2d2d44; --text: #e2e8f0; --muted: #94a3b8; --primary: #6366f1; --accent: #ec4899; --success: #22c55e; --error: #ef4444; --warning: #f59e0b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }
        .main { margin-left: 260px; padding: 24px 30px; min-height: 100vh; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header h1 { font-size: 22px; font-weight: 600; }
        .page-header .meta { color: var(--muted); font-size: 13px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; display: flex; align-items: center; gap: 14px; }
        .stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .stat-icon.blue { background: rgba(99,102,241,0.15); color: var(--primary); }
        .stat-icon.green { background: rgba(34,197,94,0.15); color: var(--success); }
        .stat-icon.pink { background: rgba(236,72,153,0.15); color: var(--accent); }
        .stat-icon.red { background: rgba(239,68,68,0.15); color: var(--error); }
        .stat-icon.yellow { background: rgba(245,158,11,0.15); color: var(--warning); }
        .stat-info .stat-value { font-size: 22px; font-weight: 700; }
        .stat-info .stat-label { font-size: 12px; color: var(--muted); margin-top: 2px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
        .card-title { font-size: 14px; font-weight: 600; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; color: var(--muted); font-weight: 500; padding: 8px 10px; border-bottom: 1px solid var(--border); }
        td { padding: 10px; border-bottom: 1px solid var(--border); }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-red { background: rgba(239,68,68,0.15); color: var(--error); }
        .badge-green { background: rgba(34,197,94,0.15); color: var(--success); }
        .badge-yellow { background: rgba(245,158,11,0.15); color: var(--warning); }
        .chart-bars { display: flex; align-items: flex-end; gap: 6px; height: 100px; margin-top: 10px; }
        .chart-bar { flex: 1; background: var(--primary); border-radius: 4px 4px 0 0; min-height: 4px; position: relative; transition: height 0.3s; }
        .chart-bar:hover { opacity: 0.8; }
        .chart-bar .chart-val { position: absolute; top: -18px; left: 50%; transform: translateX(-50%); font-size: 10px; color: var(--muted); white-space: nowrap; }
        .chart-labels { display: flex; gap: 6px; margin-top: 6px; }
        .chart-labels span { flex: 1; text-align: center; font-size: 10px; color: var(--muted); }
        .btn { padding: 6px 14px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; transition: all 0.15s; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-success { background: var(--success); color: #fff; }
        .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--muted); }
        .btn:hover { opacity: 0.85; }
        .resolve-btn { cursor: pointer; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="page-header">
            <h1>Dashboard</h1>
            <span class="meta"><?= date('d.m.Y H:i') ?></span>
        </div>

        <!-- İstatistik Kartları -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $stats['active_students'] ?></div>
                    <div class="stat-label">Aktif Öğrenci (7 gün)</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-message"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $stats['today_messages'] ?></div>
                    <div class="stat-label">Bugünkü Mesajlar</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pink"><i class="fas fa-graduation-cap"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $stats['total_students'] ?></div>
                    <div class="stat-label">Toplam Öğrenci</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon <?= $stats['red_flags'] > 0 ? 'red' : 'green' ?>">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $stats['red_flags'] ?></div>
                    <div class="stat-label">Açık Red Flag</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-face-smile"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $stats['avg_sentiment'] >= 0 ? '+' : '' ?><?= $stats['avg_sentiment'] ?></div>
                    <div class="stat-label">Ortalama Duygu (7 gün)</div>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <!-- Günlük Mesaj Grafiği -->
            <div class="card">
                <div class="card-title"><i class="fas fa-chart-bar"></i> Günlük Mesajlar (7 Gün)</div>
                <?php
                $maxMsg = max(array_column($dailyMessages, 'total') ?: [1]);
                ?>
                <div class="chart-bars">
                    <?php foreach ($dailyMessages as $d): ?>
                    <div class="chart-bar" style="height: <?= max(4, ($d['total'] / $maxMsg) * 100) ?>%">
                        <span class="chart-val"><?= $d['total'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-labels">
                    <?php foreach ($dailyMessages as $d): ?>
                    <span><?= date('d/m', strtotime($d['usage_date'])) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Sınıf Dağılımı -->
            <div class="card">
                <div class="card-title"><i class="fas fa-school"></i> Sınıf Dağılımı</div>
                <table>
                    <tr><th>Sınıf</th><th>Öğrenci</th></tr>
                    <?php foreach ($classDist as $c): ?>
                    <tr><td><?= htmlspecialchars($c['class']) ?></td><td><?= $c['count'] ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($classDist)): ?>
                    <tr><td colspan="2" style="color:var(--muted)">Henüz veri yok</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="grid-2">
            <!-- En Aktif Öğrenciler -->
            <div class="card">
                <div class="card-title"><i class="fas fa-trophy"></i> En Aktif Öğrenciler (30 Gün)</div>
                <table>
                    <tr><th>Öğrenci</th><th>Sınıf</th><th>Mesaj</th><th>Son Giriş</th></tr>
                    <?php foreach ($topStudents as $ts): ?>
                    <tr>
                        <td><?= htmlspecialchars($ts['first_name'] . ' ' . mb_substr($ts['last_name'], 0, 1) . '.') ?></td>
                        <td><?= htmlspecialchars($ts['class']) ?></td>
                        <td><?= $ts['total_msgs'] ?></td>
                        <td><?= date('d/m', strtotime($ts['last_active'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- Red Flag Uyarıları -->
            <div class="card">
                <div class="card-title"><i class="fas fa-flag" style="color:var(--error)"></i> Red Flag Uyarıları</div>
                <?php if (empty($redFlags)): ?>
                    <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Açık uyarı yok</p>
                <?php else: ?>
                <table>
                    <tr><th>Öğrenci</th><th>Tür</th><th>Tarih</th><th></th></tr>
                    <?php foreach ($redFlags as $rf): ?>
                    <tr>
                        <td><?= htmlspecialchars($rf['first_name'] . ' ' . mb_substr($rf['last_name'], 0, 1) . '.') ?> <small>(<?= $rf['class'] ?>)</small></td>
                        <td><span class="badge badge-<?= $rf['alert_type'] === 'critical' ? 'red' : 'yellow' ?>"><?= $rf['alert_type'] ?></span></td>
                        <td><?= date('d/m H:i', strtotime($rf['created_at'])) ?></td>
                        <td><button class="btn btn-sm btn-success resolve-btn" data-id="<?= $rf['id'] ?>" onclick="resolveAlert(this)"><i class="fas fa-check"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="/admin/assets/mobile.js?v=<?= filemtime(__DIR__.'/assets/mobile.js') ?>"></script>
    <script>
    async function resolveAlert(btn) {
        const id = btn.dataset.id;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const res = await fetch('/admin/api/alerts.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'resolve', alert_id: id})
            });
            if (res.ok) btn.closest('tr').remove();
        } catch(e) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i>'; }
    }
    </script>
</body>
</html>
