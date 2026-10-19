<?php
/**
 * Aibuke - Haftalık Analiz (Gelişim Karnesi + Davranış Pattern + Öğrenme Stili)
 * Cron: Pazar 20:00
 * php /var/www/aibuke/cron/weekly_analysis.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Beyin.php';

echo "=== Haftalık Analiz ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

$beyin = new Beyin();

// Son 7 günde aktif öğrenciler
$stmt = $pdo->query("
    SELECT DISTINCT s.id, s.first_name, s.last_name, s.nickname, s.class, s.school_id,
           sp.exam_type, sp.persona_mode, sp.target_profession, sp.learning_style, sp.learning_style_confidence
    FROM students s
    JOIN aibuke_student_profiles sp ON s.id = sp.student_id
    WHERE s.id IN (
        SELECT DISTINCT student_id FROM aibuke_daily_usage
        WHERE usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    )
");
$students = $stmt->fetchAll();

echo "Analiz edilecek öğrenci: " . count($students) . "\n\n";

foreach ($students as $s) {
    echo "--- {$s['first_name']} {$s['last_name']} ---\n";

    // ============================
    // 1. Haftalık istatistikler
    // ============================
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_messages,
            AVG(sentiment_score) as avg_sentiment,
            AVG(CHAR_LENGTH(content)) as avg_msg_length,
            SUM(CASE WHEN has_image = 1 THEN 1 ELSE 0 END) as photo_count
        FROM aibuke_messages
        WHERE student_id = ? AND role = 'student'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$s['id']]);
    $msgStats = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT usage_date) as active_days,
               AVG(message_count) as avg_daily_msgs
        FROM aibuke_daily_usage
        WHERE student_id = ? AND usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$s['id']]);
    $usageStats = $stmt->fetch();

    // Doğru cevap sayısı (bu hafta)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as correct_count FROM aibuke_points
        WHERE student_id = ? AND action_type = 'correct_answer'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$s['id']]);
    $correctCount = (int) $stmt->fetchColumn();

    $totalQuestions = max(1, (int) ($msgStats['total_messages'] / 3)); // yaklaşık
    $correctRate = $totalQuestions > 0 ? round($correctCount / $totalQuestions * 100) : 0;

    $weeklyStats = [
        'total_messages'  => (int) $msgStats['total_messages'],
        'questions_solved' => $totalQuestions,
        'correct_rate'    => $correctRate,
        'active_days'     => (int) $usageStats['active_days'],
        'avg_session_min' => round(((int) $usageStats['avg_daily_msgs']) * 1.5), // yaklaşık
        'subjects'        => []
    ];

    // Geçen hafta verileri
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_messages FROM aibuke_messages
        WHERE student_id = ? AND role = 'student'
        AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$s['id']]);
    $prevWeek = $stmt->fetch();
    $weeklyStats['prev_week'] = ['total_messages' => (int) $prevWeek['total_messages'], 'correct_rate' => 0];

    // ============================
    // 2. Gelişim Karnesi
    // ============================
    $result = $beyin->generateProgressReport($s, $s, $weeklyStats);
    if ($result['success']) {
        echo "  [OK] Gelişim karnesi oluşturuldu\n";
    } else {
        echo "  [HATA] Gelişim karnesi: {$result['error']}\n";
    }
    sleep(1);

    // ============================
    // 3. Davranış Pattern Analizi
    // ============================
    // Aktif saatler
    $stmt = $pdo->prepare("
        SELECT HOUR(created_at) as h, COUNT(*) as cnt
        FROM aibuke_messages
        WHERE student_id = ? AND role = 'student'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY h ORDER BY cnt DESC LIMIT 3
    ");
    $stmt->execute([$s['id']]);
    $activeHours = $stmt->fetchAll();
    $hoursStr = implode(', ', array_map(fn($r) => "{$r['h']}:00", $activeHours));

    // Streak
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT usage_date) as streak_approx
        FROM aibuke_daily_usage
        WHERE student_id = ? AND usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$s['id']]);
    $streak = (int) $stmt->fetchColumn();

    $behaviorData = [
        'active_hours'    => $hoursStr ?: 'Veri yok',
        'avg_session_min' => $weeklyStats['avg_session_min'],
        'total_messages'  => $weeklyStats['total_messages'],
        'avg_sentiment'   => round((float) $msgStats['avg_sentiment'], 1),
        'photo_count'     => (int) $msgStats['photo_count'],
        'avg_msg_length'  => round((float) $msgStats['avg_msg_length']),
        'streak'          => $streak
    ];

    $result = $beyin->analyzeBehaviorPatterns($s, $s, $behaviorData);
    if ($result['success'] && isset($result['patterns']['patterns'])) {
        // Eski pattern'leri pasifle
        $stmt = $pdo->prepare("
            UPDATE aibuke_student_behavior_patterns SET is_active = 0 WHERE student_id = ?
        ");
        $stmt->execute([$s['id']]);

        // Yeni pattern'leri kaydet
        $insertStmt = $pdo->prepare("
            INSERT INTO aibuke_student_behavior_patterns
            (student_id, pattern_type, description, ai_recommendation, confidence_score)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($result['patterns']['patterns'] as $p) {
            $insertStmt->execute([
                $s['id'],
                $p['type'] ?? 'study_habit',
                $p['description'] ?? '',
                $p['recommendation'] ?? '',
                $p['confidence'] ?? 50
            ]);
        }
        echo "  [OK] Davranış pattern'leri güncellendi\n";
    } else {
        echo "  [HATA] Davranış pattern: " . ($result['error'] ?? 'parse hatası') . "\n";
    }
    sleep(1);

    // ============================
    // 4. Öğrenme Stili Tespiti
    // ============================
    // En az 30 mesaj yoksa atla
    if ((int) $msgStats['total_messages'] < 10) {
        echo "  [ATLA] Öğrenme stili - yetersiz veri\n\n";
        continue;
    }

    // Kelime sıklıkları (son 30 gün)
    $stmt = $pdo->prepare("
        SELECT content FROM aibuke_messages
        WHERE student_id = ? AND role = 'student'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$s['id']]);
    $allMessages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $allText = mb_strtolower(implode(' ', $allMessages));

    $visualKw = preg_match_all('/göster|çiz|şekil|grafik|resim|diyagram/u', $allText);
    $auditoryKw = preg_match_all('/anlat|dinle|sesli|oku|söyle/u', $allText);
    $kinestheticKw = preg_match_all('/dene|yap|çöz|adım|uygula|pratik/u', $allText);

    // Son 30 gün fotoğraf
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM aibuke_messages
        WHERE student_id = ? AND has_image = 1
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$s['id']]);
    $photoCount30 = (int) $stmt->fetchColumn();

    $styleData = [
        'avg_msg_length'      => round((float) $msgStats['avg_msg_length']),
        'photo_uploads'       => $photoCount30,
        'visual_keywords'     => $visualKw,
        'auditory_keywords'   => $auditoryKw,
        'kinesthetic_keywords' => $kinestheticKw,
        'tts_usage'           => (function() use ($pdo, $s) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM aibuke_voice_messages WHERE student_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $st->execute([$s['id']]);
            return (int) $st->fetchColumn();
        })(),
        'total_messages'      => count($allMessages)
    ];

    $result = $beyin->detectLearningStyle($s, $styleData);
    if ($result['success'] && isset($result['style']['style'])) {
        $style = $result['style']['style'];
        $confidence = $result['style']['confidence'] ?? 30;

        $stmt = $pdo->prepare("
            UPDATE aibuke_student_profiles
            SET learning_style = ?, learning_style_confidence = ?
            WHERE student_id = ?
        ");
        $stmt->execute([$style, $confidence, $s['id']]);
        echo "  [OK] Öğrenme stili: {$style} (güven: {$confidence}%)\n";
    } else {
        echo "  [HATA] Öğrenme stili: " . ($result['error'] ?? 'parse hatası') . "\n";
    }

    echo "\n";
    sleep(1);
}

echo "=== Haftalık analiz tamamlandı ===\n";
