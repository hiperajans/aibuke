<?php
require_once __DIR__ . '/auth.php';
session_start();
requireAdminLogin();
$admin = getAdminInfo();
$schoolId = $admin['school_id'];

// Konu bazlı hata heatmap (hangi konuda zorlanıyorlar)
$stmt = $pdo->prepare("
    SELECT sp.subject,
           SUM(sp.error_type_careless) as careless,
           SUM(sp.error_type_knowledge) as knowledge,
           SUM(sp.error_type_time) as time_err,
           SUM(sp.error_type_confusion) as confusion,
           SUM(sp.correct_answers) as correct,
           SUM(sp.questions_solved - sp.correct_answers) as wrong
    FROM aibuke_student_progress sp
    JOIN students s ON sp.student_id = s.id
    WHERE s.school_id = ?
    GROUP BY sp.subject
    ORDER BY (SUM(sp.questions_solved - sp.correct_answers)) DESC
    LIMIT 15
");
$stmt->execute([$schoolId]);
$topicHeatmap = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Haftalık duygu durumu trendi (son 4 hafta)
$stmt = $pdo->prepare("
    SELECT YEARWEEK(m.created_at, 1) as yw,
           MIN(DATE(m.created_at)) as week_start,
           ROUND(AVG(m.sentiment_score), 2) as avg_sentiment,
           COUNT(*) as msg_count
    FROM aibuke_messages m
    JOIN students s ON m.student_id = s.id
    WHERE s.school_id = ? AND m.role = 'student' AND m.created_at >= DATE_SUB(NOW(), INTERVAL 28 DAY)
    GROUP BY yw
    ORDER BY yw
");
$stmt->execute([$schoolId]);
$sentimentTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sınıf bazlı aktivite karşılaştırma
$stmt = $pdo->prepare("
    SELECT s.class,
           COUNT(DISTINCT du.student_id) as active_students,
           SUM(du.message_count) as total_messages,
           ROUND(AVG(du.message_count), 1) as avg_per_student
    FROM aibuke_daily_usage du
    JOIN students s ON du.student_id = s.id
    WHERE s.school_id = ? AND du.usage_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY s.class
    ORDER BY total_messages DESC
");
$stmt->execute([$schoolId]);
$classActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Öğrenme stili dağılımı
$stmt = $pdo->prepare("
    SELECT sp.learning_style, COUNT(*) as count
    FROM aibuke_student_profiles sp
    JOIN students s ON sp.student_id = s.id
    WHERE s.school_id = ? AND sp.learning_style IS NOT NULL
    GROUP BY sp.learning_style
");
$stmt->execute([$schoolId]);
$styleDist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Token/maliyet tahmini (son 30 gün)
$stmt = $pdo->prepare("
    SELECT SUM(m.tokens_used) as total_tokens, COUNT(*) as total_messages
    FROM aibuke_messages m
    JOIN students s ON m.student_id = s.id
    WHERE s.school_id = ? AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute([$schoolId]);
$usage = $stmt->fetch(PDO::FETCH_ASSOC);
$estimatedCost = round(($usage['total_tokens'] ?? 0) * 0.0000003, 2); // ~$0.30/1M tokens avg

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
    <title>Raporlar — Aibüke Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0f0f23; --surface: #1a1a2e; --border: #2d2d44; --text: #e2e8f0; --muted: #94a3b8; --primary: #6366f1; --accent: #ec4899; --success: #22c55e; --error: #ef4444; --warning: #f59e0b; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }
        .main { padding: 24px 30px; overflow-x: hidden; }
        .page-header h1 { font-size: 22px; margin-bottom: 24px; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .grid-2 > * { min-width: 0; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; overflow: hidden; }
        .card-title { font-size: 14px; font-weight: 600; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }
        .card-mt { margin-top: 24px; }

        .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; color: var(--muted); font-weight: 500; padding: 8px 10px; border-bottom: 1px solid var(--border); white-space: nowrap; }
        td { padding: 9px 10px; border-bottom: 1px solid var(--border); white-space: nowrap; }
        tr:last-child td { border-bottom: none; }

        .heat { display: inline-block; min-width: 28px; text-align: center; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
        .heat-0 { background: transparent; color: var(--muted); }
        .heat-1 { background: rgba(245,158,11,0.15); color: var(--warning); }
        .heat-2 { background: rgba(245,158,11,0.3); color: var(--warning); }
        .heat-3 { background: rgba(239,68,68,0.2); color: var(--error); }
        .heat-4 { background: rgba(239,68,68,0.4); color: var(--error); }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-red { background: rgba(239,68,68,0.15); color: var(--error); }
        .sentiment-bar { display: inline-block; height: 8px; border-radius: 4px; }
        .sentiment-pos { background: var(--success); }
        .sentiment-neg { background: var(--error); }
        .sentiment-neutral { background: var(--muted); }

        .cost-box { text-align: center; padding: 20px; }
        .cost-value { font-size: 28px; font-weight: 700; color: var(--success); }
        .cost-label { font-size: 12px; color: var(--muted); margin-top: 4px; }

        .flex-col-gap { display: flex; flex-direction: column; gap: 20px; }

        .style-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .style-label { width: 90px; font-size: 12px; flex-shrink: 0; }
        .style-bar-bg { flex: 1; height: 8px; background: var(--border); border-radius: 4px; min-width: 0; }
        .style-bar-fill { height: 100%; background: var(--primary); border-radius: 4px; }
        .style-count { font-size: 12px; color: var(--muted); flex-shrink: 0; }

        .report-item { background: rgba(99,102,241,0.05); border-radius: 10px; padding: 14px; margin-bottom: 12px; }
        .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .report-header-date { font-size: 11px; color: var(--muted); }
        .report-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; font-size: 12px; }
        .report-stats-label { color: var(--muted); }
        .report-footer { margin-top: 8px; font-size: 11px; color: var(--muted); }
        .empty-text { color: var(--muted); text-align: center; padding: 20px; }

        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .report-stats { grid-template-columns: repeat(2, 1fr); }
            .card { padding: 14px; }
            .card-mt { margin-top: 16px; }
            .style-label { width: 70px; }
            .cost-value { font-size: 22px; }
        }
        @media (max-width: 480px) {
            .report-stats { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="page-header"><h1>Raporlar</h1></div>

        <div class="grid-2">
            <!-- Konu Heatmap -->
            <div class="card">
                <div class="card-title"><i class="fas fa-fire"></i> Konu Heatmap — Zorlanılan Alanlar</div>
                <?php if (empty($topicHeatmap)): ?>
                    <p class="empty-text">Henüz veri yok</p>
                <?php else: ?>
                <div class="table-scroll">
                <table>
                    <tr><th>Konu</th><th>Dikkatsiz.</th><th>Konu Eks.</th><th>Zaman</th><th>Kavram K.</th></tr>
                    <?php foreach ($topicHeatmap as $t):
                        $vals = [$t['careless'], $t['knowledge'], $t['time_err'], $t['confusion']];
                        $maxV = max($vals) ?: 1;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($t['subject']) ?></td>
                        <?php foreach ($vals as $v):
                            $level = $v == 0 ? 0 : min(4, ceil(($v / $maxV) * 4));
                        ?>
                        <td><span class="heat heat-<?= $level ?>"><?= $v ?></span></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Duygu Durumu Trendi -->
            <div class="card">
                <div class="card-title"><i class="fas fa-heart-pulse"></i> Duygu Durumu Trendi (4 Hafta)</div>
                <?php if (empty($sentimentTrend)): ?>
                    <p class="empty-text">Henüz veri yok</p>
                <?php else: ?>
                <div class="table-scroll">
                <table>
                    <tr><th>Hafta</th><th>Mesaj</th><th>Ort. Duygu</th><th>Gösterge</th></tr>
                    <?php foreach ($sentimentTrend as $w):
                        $sent = (float) $w['avg_sentiment'];
                        $cls = $sent > 0.3 ? 'pos' : ($sent < -0.3 ? 'neg' : 'neutral');
                        $width = min(80, abs($sent) * 40);
                    ?>
                    <tr>
                        <td><?= date('d/m', strtotime($w['week_start'])) ?></td>
                        <td><?= $w['msg_count'] ?></td>
                        <td><?= $sent >= 0 ? '+' : '' ?><?= $sent ?></td>
                        <td><span class="sentiment-bar sentiment-<?= $cls ?>" style="width:<?= max(8, $width) ?>px"></span></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid-2">
            <!-- Sınıf Bazlı Aktivite -->
            <div class="card">
                <div class="card-title"><i class="fas fa-school"></i> Sınıf Bazlı Aktivite (30 Gün)</div>
                <div class="table-scroll">
                <table>
                    <tr><th>Sınıf</th><th>Aktif Öğrenci</th><th>Toplam Mesaj</th><th>Kişi Başı Ort.</th></tr>
                    <?php foreach ($classActivity as $ca): ?>
                    <tr>
                        <td><?= htmlspecialchars($ca['class']) ?></td>
                        <td><?= $ca['active_students'] ?></td>
                        <td><?= $ca['total_messages'] ?></td>
                        <td><?= $ca['avg_per_student'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                </div>
            </div>

            <div class="flex-col-gap">
                <!-- Öğrenme Stili Dağılımı -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-brain"></i> Öğrenme Stili Dağılımı</div>
                    <?php
                    $styleNames = ['gorsel'=>'Görsel','isitsel'=>'İşitsel','okuma_yazma'=>'Okuma/Yazma','kinestetik'=>'Kinestetik'];
                    foreach ($styleDist as $sd): ?>
                    <div class="style-row">
                        <span class="style-label"><?= $styleNames[$sd['learning_style']] ?? $sd['learning_style'] ?></span>
                        <div class="style-bar-bg">
                            <div class="style-bar-fill" style="width:<?= min(100, $sd['count'] * 10) ?>%"></div>
                        </div>
                        <span class="style-count"><?= $sd['count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Maliyet -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-coins"></i> Token Kullanımı (30 Gün)</div>
                    <div class="cost-box">
                        <div class="cost-value">~$<?= $estimatedCost ?></div>
                        <div class="cost-label"><?= number_format($usage['total_tokens'] ?? 0) ?> token / <?= number_format($usage['total_messages'] ?? 0) ?> mesaj</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aylık Okul Raporları (B2B) -->
        <?php
        $stmtReports = $pdo->prepare("SELECT * FROM aibuke_school_reports WHERE school_id = ? ORDER BY report_month DESC LIMIT 6");
        $stmtReports->execute([$schoolId]);
        $monthlyReports = $stmtReports->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <?php if (!empty($monthlyReports)): ?>
        <div class="card card-mt">
            <div class="card-title"><i class="fas fa-calendar-alt"></i> Aylık Okul Raporları (B2B)</div>
            <?php foreach ($monthlyReports as $mr):
                $rc = json_decode($mr['report_content'], true);
            ?>
            <div class="report-item">
                <div class="report-header">
                    <strong><?= htmlspecialchars($rc['month'] ?? date('F Y', strtotime($mr['report_month']))) ?></strong>
                    <span class="report-header-date"><?= date('d.m.Y', strtotime($mr['created_at'])) ?></span>
                </div>
                <div class="report-stats">
                    <div><span class="report-stats-label">Aktif Öğrenci</span><br><strong><?= $rc['active_students'] ?? 0 ?></strong></div>
                    <div><span class="report-stats-label">Toplam Mesaj</span><br><strong><?= number_format($rc['total_messages'] ?? 0) ?></strong></div>
                    <div><span class="report-stats-label">Ort. Aktivite</span><br><strong><?= $rc['avg_activity'] ?? 0 ?> msg/kişi</strong></div>
                    <div><span class="report-stats-label">Red Flag</span><br><strong style="color:<?= ($rc['red_flag_count'] ?? 0) > 0 ? 'var(--error)' : 'var(--success)' ?>;"><?= $rc['red_flag_count'] ?? 0 ?></strong></div>
                </div>
                <?php if (!empty($rc['hard_topics'])): ?>
                <div class="report-footer">Zorlanılan: <?= implode(', ', $rc['hard_topics']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <script src="/admin/assets/mobile.js?v=<?= filemtime(__DIR__.'/assets/mobile.js') ?>"></script>
</body>
</html>
