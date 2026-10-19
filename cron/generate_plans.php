<?php
/**
 * Aibuke - Günlük Çalışma Planı Üretici
 * Cron: Her sabah 07:00
 * php /var/www/aibuke/cron/generate_plans.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Beyin.php';

echo "=== Günlük Plan Üretimi ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

$beyin = new Beyin();
$today = date('Y-m-d');

// Aktif öğrencileri al (son 7 günde en az 1 mesaj göndermiş)
$stmt = $pdo->query("
    SELECT DISTINCT s.id, s.first_name, s.last_name, s.nickname, s.class, s.school_id,
           sp.exam_type, sp.persona_mode, sp.target_profession, sp.learning_style
    FROM students s
    JOIN aibuke_student_profiles sp ON s.id = sp.student_id
    WHERE s.id IN (
        SELECT DISTINCT student_id FROM aibuke_daily_usage
        WHERE usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    )
    AND s.id NOT IN (
        SELECT student_id FROM aibuke_daily_plans WHERE plan_date = '{$today}'
    )
");
$students = $stmt->fetchAll();

echo "Aktif öğrenci sayısı: " . count($students) . "\n\n";

$success = 0;
$fail = 0;

foreach ($students as $s) {
    // Son performans verilerini al
    $stmt = $pdo->prepare("
        SELECT subject,
               ROUND(correct_answers * 100 / GREATEST(questions_solved, 1)) as correct_rate,
               GROUP_CONCAT(topic SEPARATOR ', ') as weak_topics
        FROM aibuke_student_progress
        WHERE student_id = ? AND strength_score < 60
        GROUP BY subject
        LIMIT 5
    ");
    $stmt->execute([$s['id']]);
    $progress = $stmt->fetchAll();

    // Davranış pattern'lerini al
    $stmt = $pdo->prepare("
        SELECT description FROM aibuke_student_behavior_patterns
        WHERE student_id = ? AND is_active = 1
        ORDER BY confidence_score DESC LIMIT 3
    ");
    $stmt->execute([$s['id']]);
    $patterns = $stmt->fetchAll();

    // Son 3 günde işlenen konuları al (sınav türüne göre sadece ilgili dersler)
    $examType = strtoupper($s['exam_type'] ?? '');
    $ortakHaricDersler = ['Beden Eğitimi', 'Beden eğitimi', 'Müzik', 'G.S', 'Görsel Sanatlar', 'Rehberlik', 'B.T', 'Bilişim'];
    if ($examType === 'LGS') {
        // LGS: Türkçe, Matematik, Fen Bilimleri, Sosyal Bilgiler, İngilizce, D.K.A.B
        $haricDersler = array_merge($ortakHaricDersler, ['Teknoloji ve Tasarım']);
    } else {
        // YKS: D.K.A.B ve Din Bilgisi de hariç
        $haricDersler = array_merge($ortakHaricDersler, ['D.K.A.B', 'Din Bilgisi']);
    }
    $islenenKonular = [];
    if (!empty($s['class'])) {
        $placeholders = implode(',', array_fill(0, count($haricDersler), '?'));
        $stmt = $pdo->prepare("
            SELECT ik.ders, ik.sinif, ik.ders_tarihi, ik.konu_basligi, ik.kazanimlar
            FROM islenen_konular ik
            WHERE ik.school_id = ?
              AND ik.sinif = ?
              AND ik.ders_tarihi >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
              AND ik.ders NOT IN ($placeholders)
            ORDER BY ik.ders_tarihi DESC, ik.ders
            LIMIT 10
        ");
        $stmt->execute(array_merge([$s['school_id'], $s['class']], $haricDersler));
        $islenenKonular = $stmt->fetchAll();
    }

    // Plan üret
    $result = $beyin->generateDailyPlan($s, $s, $progress, $patterns, $islenenKonular);

    if ($result['success']) {
        $planJson = json_encode($result['plan'] ?? $result['text'], JSON_UNESCAPED_UNICODE);
        $weakTopics = json_encode(array_column($progress, 'weak_topics'), JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("
            INSERT INTO aibuke_daily_plans (student_id, plan_date, plan_content, weak_topics, motivation_tip)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE plan_content = VALUES(plan_content), weak_topics = VALUES(weak_topics), motivation_tip = VALUES(motivation_tip)
        ");
        $stmt->execute([
            $s['id'],
            $today,
            $planJson,
            $weakTopics,
            $result['plan']['motivasyon'] ?? ''
        ]);
        $success++;
        echo "[OK] {$s['first_name']} {$s['last_name']} - plan oluşturuldu\n";
    } else {
        $fail++;
        echo "[HATA] {$s['first_name']} {$s['last_name']} - {$result['error']}\n";
    }

    // Rate limit: istekler arası 1 saniye bekle
    sleep(1);
}

echo "\n=== Sonuç: {$success} başarılı, {$fail} hatalı ===\n";
