<?php
require_once __DIR__ . '/auth.php';
session_start();
requireAdminLogin();
$admin = getAdminInfo();
$schoolId = $admin['school_id'];

// Turnuvaları listele
$stmt = $pdo->prepare("
    SELECT t.*,
           (SELECT COUNT(*) FROM aibuke_tournament_participants WHERE tournament_id = t.id) as participant_count
    FROM aibuke_tournaments t
    WHERE t.school_id = ?
    ORDER BY t.created_at DESC
    LIMIT 30
");
$stmt->execute([$schoolId]);
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Turnuva oluşturma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tournament'])) {
    $subject = trim($_POST['subject'] ?? 'Matematik');
    $topic = trim($_POST['topic'] ?? '');

    // API'yi çağır
    $ch = curl_init('http://localhost/api/tournaments.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'action' => 'create_daily',
            'subject' => $subject,
            'topic' => $topic,
            'school_id' => $schoolId
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIE => session_name() . '=' . session_id()
    ]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($result['success'])) {
        header('Location: /admin/tournaments.php?created=1');
        exit;
    }
    $error = $result['error'] ?? 'Bilinmeyen hata';
}

// Red flag count for sidebar
$stmtRF = $pdo->prepare("SELECT COUNT(*) FROM aibuke_sentiment_alerts sa JOIN students s ON sa.student_id = s.id WHERE s.school_id = ? AND sa.is_resolved = 0");
$stmtRF->execute([$schoolId]);
$redFlagCount = (int) $stmtRF->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turnuvalar — Aibüke Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0f0f23; --surface: #1a1a2e; --border: #2d2d44; --text: #e2e8f0; --muted: #94a3b8; --primary: #6366f1; --success: #22c55e; --error: #ef4444; --warning: #f59e0b; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .page-header h1 { font-size: 22px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-red { background: rgba(239,68,68,0.15); color: var(--error); }
        .badge-green { background: rgba(34,197,94,0.15); color: var(--success); }
        .badge-yellow { background: rgba(245,158,11,0.15); color: var(--warning); }
        .badge-blue { background: rgba(99,102,241,0.15); color: var(--primary); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; color: var(--muted); font-weight: 500; padding: 10px; border-bottom: 1px solid var(--border); }
        td { padding: 10px; border-bottom: 1px solid var(--border); }
        input, select { padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text); font-size: 13px; }
        .btn { padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn:hover { opacity: 0.85; }
        .success-msg { background: rgba(34,197,94,0.12); color: var(--success); padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
        .error-msg { background: rgba(239,68,68,0.12); color: var(--error); padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
    </style>
    </head>
<body>
<?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="page-header">
            <h1>Turnuvalar</h1>
        </div>

        <?php if (isset($_GET['created'])): ?>
            <div class="success-msg">Turnuva başarıyla oluşturuldu!</div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Yeni Turnuva Oluştur -->
        <div class="card">
            <div style="font-size:14px;font-weight:600;margin-bottom:14px;">Yeni Turnuva Oluştur</div>
            <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                <div>
                    <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Ders</label>
                    <select name="subject">
                        <option>Matematik</option>
                        <option>Fizik</option>
                        <option>Kimya</option>
                        <option>Biyoloji</option>
                        <option>Türkçe</option>
                        <option>Tarih</option>
                        <option>Coğrafya</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Konu (opsiyonel)</label>
                    <input type="text" name="topic" placeholder="Türev, İntegral...">
                </div>
                <button type="submit" name="create_tournament" class="btn btn-primary">AI ile Soru Üret</button>
            </form>
        </div>

        <!-- Turnuva Listesi -->
        <div class="card">
            <div style="font-size:14px;font-weight:600;margin-bottom:14px;">Turnuva Geçmişi</div>
            <?php if (empty($tournaments)): ?>
                <div style="text-align:center;color:var(--muted);padding:30px;">Henüz turnuva oluşturulmadı.</div>
            <?php else: ?>
            <table>
                <tr><th>Turnuva</th><th>Durum</th><th>Katılımcı</th><th>Başlangıç</th></tr>
                <?php foreach ($tournaments as $t):
                    $statusBadge = match($t['status']) {
                        'waiting' => '<span class="badge badge-yellow">Bekliyor</span>',
                        'active' => '<span class="badge badge-green">Aktif</span>',
                        'finished' => '<span class="badge badge-blue">Tamamlandı</span>',
                        default => ''
                    };
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($t['title']) ?></strong><br><small style="color:var(--muted);"><?= $t['subject'] ?><?= $t['topic'] ? " — {$t['topic']}" : '' ?></small></td>
                    <td><?= $statusBadge ?></td>
                    <td><?= $t['participant_count'] ?> kişi</td>
                    <td><?= date('d.m.Y H:i', strtotime($t['starts_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <script src="/admin/assets/mobile.js?v=<?= filemtime(__DIR__.'/assets/mobile.js') ?>"></script>
</body>
</html>
