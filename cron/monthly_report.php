<?php
/**
 * Aibuke - Aylık Okul Raporu Oluşturucu (B2B)
 * Cron: 0 8 1 * * php /var/www/aibuke/cron/monthly_report.php
 * Her ayın 1'inde anonimleştirilmiş okul raporu üretir.
 */
require_once __DIR__ . '/../config.php';

$reportMonth = date('Y-m-01', strtotime('-1 month')); // Geçen ay
$monthLabel = date('F Y', strtotime($reportMonth));

// Tüm aktif okulları al
$schools = $pdo->query("SELECT DISTINCT s.school_id, sc.school_name FROM aibuke_student_profiles sp JOIN students s ON sp.student_id = s.id JOIN schools sc ON s.school_id = sc.id")->fetchAll(PDO::FETCH_ASSOC);

foreach ($schools as $school) {
    $schoolId = $school['school_id'];

    // Zaten rapor var mı
    $stmt = $pdo->prepare("SELECT id FROM aibuke_school_reports WHERE school_id = ? AND report_month = ?");
    $stmt->execute([$schoolId, $reportMonth]);
    if ($stmt->fetch()) continue;

    // ─── Veri Toplama ───

    // Aktif öğrenci sayısı
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT m.student_id) FROM aibuke_messages m JOIN students s ON m.student_id = s.id WHERE s.school_id = ? AND m.created_at >= ? AND m.created_at < DATE_ADD(?, INTERVAL 1 MONTH)");
    $stmt->execute([$schoolId, $reportMonth, $reportMonth]);
    $activeStudents = (int) $stmt->fetchColumn();

    // Toplam mesaj sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_messages m JOIN students s ON m.student_id = s.id WHERE s.school_id = ? AND m.created_at >= ? AND m.created_at < DATE_ADD(?, INTERVAL 1 MONTH)");
    $stmt->execute([$schoolId, $reportMonth, $reportMonth]);
    $totalMessages = (int) $stmt->fetchColumn();

    // Sınıf bazlı stres (ortalama sentiment)
    $stmt = $pdo->prepare("SELECT s.class, ROUND(AVG(m.sentiment_score), 2) as avg_sentiment, COUNT(DISTINCT m.student_id) as students FROM aibuke_messages m JOIN students s ON m.student_id = s.id WHERE s.school_id = ? AND m.role = 'student' AND m.created_at >= ? AND m.created_at < DATE_ADD(?, INTERVAL 1 MONTH) GROUP BY s.class");
    $stmt->execute([$schoolId, $reportMonth, $reportMonth]);
    $classSentiment = [];
    while ($row = $stmt->fetch()) {
        $classSentiment[$row['class']] = ['sentiment' => (float) $row['avg_sentiment'], 'students' => $row['students']];
    }

    // En zorlanılan konular
    $stmt = $pdo->prepare("SELECT sp.subject, SUM(sp.questions_solved - sp.correct_answers) as total_wrong, SUM(sp.correct_answers) as total_correct FROM aibuke_student_progress sp JOIN students s ON sp.student_id = s.id WHERE s.school_id = ? GROUP BY sp.subject ORDER BY total_wrong DESC LIMIT 5");
    $stmt->execute([$schoolId]);
    $hardTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Öğrenme stili dağılımı
    $stmt = $pdo->prepare("SELECT sp.learning_style, COUNT(*) as count FROM aibuke_student_profiles sp JOIN students s ON sp.student_id = s.id WHERE s.school_id = ? AND sp.learning_style IS NOT NULL GROUP BY sp.learning_style");
    $stmt->execute([$schoolId]);
    $learnStyles = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Red flag sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_sentiment_alerts sa JOIN students s ON sa.student_id = s.id WHERE s.school_id = ? AND sa.created_at >= ? AND sa.created_at < DATE_ADD(?, INTERVAL 1 MONTH)");
    $stmt->execute([$schoolId, $reportMonth, $reportMonth]);
    $redFlagCount = (int) $stmt->fetchColumn();

    // Genel aktivite (mesaj/öğrenci)
    $avgActivity = $activeStudents > 0 ? round($totalMessages / $activeStudents, 1) : 0;

    // ─── Rapor Oluştur ───
    $reportContent = [
        'month' => $monthLabel,
        'school' => $school['school_name'],
        'active_students' => $activeStudents,
        'total_messages' => $totalMessages,
        'avg_activity' => $avgActivity,
        'class_sentiment' => $classSentiment,
        'hard_topics' => array_column($hardTopics, 'subject'),
        'topic_details' => $hardTopics,
        'learning_styles' => $learnStyles,
        'red_flag_count' => $redFlagCount,
        'generated_at' => date('Y-m-d H:i:s')
    ];

    $pdo->prepare("INSERT INTO aibuke_school_reports (school_id, report_month, report_content, generated_by) VALUES (?, ?, ?, 'system')")
        ->execute([$schoolId, $reportMonth, json_encode($reportContent, JSON_UNESCAPED_UNICODE)]);

    echo "Rapor oluşturuldu: {$school['school_name']} — {$monthLabel}\n";
}

echo "Tamamlandı.\n";
