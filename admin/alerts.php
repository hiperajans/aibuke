<?php
require_once __DIR__ . '/auth.php';
session_start();
requireAdminLogin();
$admin = getAdminInfo();
$schoolId = $admin['school_id'];

$filter = $_GET['filter'] ?? 'open';

$query = "SELECT sa.*, s.first_name, s.last_name, s.class
    FROM aibuke_sentiment_alerts sa
    JOIN students s ON sa.student_id = s.id
    WHERE s.school_id = ?";
if ($filter === 'open') $query .= " AND sa.is_resolved = 0";
elseif ($filter === 'resolved') $query .= " AND sa.is_resolved = 1";
$query .= " ORDER BY sa.created_at DESC LIMIT 50";

$stmt = $pdo->prepare($query);
$stmt->execute([$schoolId]);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtRF = $pdo->prepare("SELECT COUNT(*) FROM aibuke_sentiment_alerts sa JOIN students s ON sa.student_id = s.id WHERE s.school_id = ? AND sa.is_resolved = 0");
$stmtRF->execute([$schoolId]);
$redFlagCount = (int) $stmtRF->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uyarılar — Aibüke Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0f0f23; --surface: #1a1a2e; --border: #2d2d44; --text: #e2e8f0; --muted: #94a3b8; --primary: #6366f1; --success: #22c55e; --error: #ef4444; --warning: #f59e0b; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }
        .main { padding: 24px 30px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .page-header h1 { font-size: 22px; }
        .filters { display: flex; gap: 8px; }
        .filter-btn { padding: 6px 14px; border: 1px solid var(--border); border-radius: 20px; background: transparent; color: var(--muted); font-size: 12px; cursor: pointer; text-decoration: none; transition: all 0.15s; }
        .filter-btn:hover { border-color: var(--primary); color: var(--primary); }
        .filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .alert-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; margin-bottom: 12px; display: flex; align-items: flex-start; gap: 14px; }
        .alert-card.critical { border-left: 3px solid var(--error); }
        .alert-card.red_flag { border-left: 3px solid var(--warning); }
        .alert-card.resolved { opacity: 0.5; }
        .alert-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .alert-icon.critical { background: rgba(239,68,68,0.15); color: var(--error); }
        .alert-icon.red_flag { background: rgba(245,158,11,0.15); color: var(--warning); }
        .alert-body { flex: 1; }
        .alert-name { font-weight: 600; font-size: 14px; }
        .alert-meta { font-size: 12px; color: var(--muted); margin-top: 3px; }
        .alert-msgs { font-size: 12px; color: var(--muted); margin-top: 6px; background: rgba(0,0,0,0.2); padding: 8px 10px; border-radius: 8px; }
        .alert-actions { flex-shrink: 0; }
        .btn { padding: 6px 14px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; }
        .btn-success { background: var(--success); color: #fff; }
        .btn:hover { opacity: 0.85; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; }
        .badge-red { background: rgba(239,68,68,0.15); color: var(--error); }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="page-header">
            <h1>Uyarılar</h1>
            <div class="filters">
                <a class="filter-btn <?= $filter === 'open' ? 'active' : '' ?>" href="?filter=open">Açık</a>
                <a class="filter-btn <?= $filter === 'resolved' ? 'active' : '' ?>" href="?filter=resolved">Çözülmüş</a>
                <a class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" href="?filter=all">Tümü</a>
            </div>
        </div>

        <?php if (empty($alerts)): ?>
            <div style="text-align:center;color:var(--muted);padding:60px;">
                <i class="fas fa-check-circle" style="font-size:40px;margin-bottom:12px;color:var(--success);display:block;"></i>
                Açık uyarı yok
            </div>
        <?php endif; ?>

        <?php foreach ($alerts as $a): ?>
        <div class="alert-card <?= $a['alert_type'] ?> <?= $a['is_resolved'] ? 'resolved' : '' ?>" id="alert-<?= $a['id'] ?>">
            <div class="alert-icon <?= $a['alert_type'] ?>">
                <i class="fas fa-<?= $a['alert_type'] === 'critical' ? 'exclamation-triangle' : 'flag' ?>"></i>
            </div>
            <div class="alert-body">
                <div class="alert-name"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?> <small style="color:var(--muted)">(<?= $a['class'] ?>)</small></div>
                <div class="alert-meta">
                    <?php
                    $typeNames = ['red_flag'=>'Kırmızı Bayrak','gold_flag'=>'Altın Bayrak','critical'=>'Kritik Güvenlik Uyarısı'];
                    echo $typeNames[$a['alert_type']] ?? ucfirst($a['alert_type']);
                    ?> — <?= date('d.m.Y H:i', strtotime($a['created_at'])) ?>
                    <?php if ($a['is_resolved']): ?> — <span style="color:var(--success)">Çözüldü: <?= htmlspecialchars($a['resolved_by']) ?></span><?php endif; ?>
                </div>
                <?php if ($a['trigger_messages']): ?>
                <div class="alert-msgs">
                    <?php $msgs = json_decode($a['trigger_messages'], true);
                    if ($msgs && isset($msgs['safety_type'])): ?>
                        <div style="margin-bottom:4px;"><span style="color:var(--error);font-weight:600;">🚨 <?= htmlspecialchars($msgs['label'] ?? $msgs['safety_type']) ?></span></div>
                        <div>"<?= htmlspecialchars(mb_substr($msgs['message'] ?? '', 0, 200)) ?>"</div>
                    <?php elseif ($msgs):
                        foreach (array_slice((array)$msgs, 0, 3) as $m): ?>
                        <div style="margin-bottom:3px;">"<?= htmlspecialchars(mb_substr(is_string($m) ? $m : json_encode($m), 0, 120)) ?>..."</div>
                    <?php endforeach; endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!$a['is_resolved']): ?>
            <div class="alert-actions">
                <button class="btn btn-success" onclick="resolveAlert(<?= $a['id'] ?>, this)"><i class="fas fa-check"></i> Çözüldü</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <script src="/admin/assets/mobile.js?v=<?= filemtime(__DIR__.'/assets/mobile.js') ?>"></script>
    <script>
    async function resolveAlert(id, btn) {
        btn.disabled = true;
        try {
            await fetch('/admin/api/alerts.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'resolve', alert_id: id})
            });
            document.getElementById('alert-' + id).classList.add('resolved');
            btn.remove();
        } catch(e) { btn.disabled = false; }
    }
    </script>
</body>
</html>
