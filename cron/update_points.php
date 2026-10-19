<?php
/**
 * Aibuke - Puan Güncelleme
 * 30dk çalışma puanı + aylık şampiyon rozeti
 * Cron: Her gün 23:00
 * php /var/www/aibuke/cron/update_points.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/PointsManager.php';

echo "=== Puan Güncelleme ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

$pm = new PointsManager($pdo);

// ============================
// 1. 30dk aktif çalışma tespiti
// ============================
// Bugün 10+ mesaj gönderen öğrenciler (yaklaşık 30dk çalışma)
$stmt = $pdo->query("
    SELECT student_id, message_count
    FROM aibuke_daily_usage
    WHERE usage_date = CURDATE() AND message_count >= 10
");
$activeStudents = $stmt->fetchAll();

$studyPoints = 0;
foreach ($activeStudents as $row) {
    $result = $pm->addPoints($row['student_id'], 'study_30min', '30dk+ aktif çalışma');
    if ($result) $studyPoints++;
}
echo "[OK] 30dk çalışma puanı: {$studyPoints} öğrenci\n";

// ============================
// 2. Aylık şampiyon rozeti (ayın son günü)
// ============================
$isLastDay = date('d') === date('t'); // Ayın son günü mü?

if ($isLastDay) {
    echo "\n--- Aylık Şampiyon Seçimi ---\n";

    // Her okul için aylık birinci
    $stmt = $pdo->query("
        SELECT school_id, student_id, SUM(points) as total
        FROM aibuke_points
        WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
        GROUP BY school_id, student_id
        ORDER BY school_id, total DESC
    ");
    $allRanks = $stmt->fetchAll();

    $champions = [];
    $currentSchool = null;

    foreach ($allRanks as $r) {
        if ($r['school_id'] !== $currentSchool) {
            $currentSchool = $r['school_id'];
            $champions[] = $r;
        }
    }

    // Şampiyon rozeti ver
    $champBadge = $pdo->query("SELECT id FROM aibuke_badges WHERE badge_code = 'sampiyon'")->fetch();

    if ($champBadge) {
        foreach ($champions as $champ) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO aibuke_student_badges (student_id, badge_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$champ['student_id'], $champBadge['id']]);
            echo "[OK] Şampiyon: öğrenci #{$champ['student_id']} (okul #{$champ['school_id']}, {$champ['total']} puan)\n";
        }
    }
}

echo "\n=== Puan güncelleme tamamlandı ===\n";
