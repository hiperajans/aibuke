<?php
/**
 * Aibuke - Geri Çağırma Kontrolü
 * 1/3/7 gün gelmemiş öğrencilere bildirim gönder
 * Cron: Her gün 18:00
 * php /var/www/aibuke/cron/comeback_check.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Beyin.php';

echo "=== Geri Çağırma Kontrolü ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

$beyin = new Beyin();

// Gelmemiş öğrencileri bul (1, 3, 7 gün yokluk)
$stmt = $pdo->query("
    SELECT s.id, s.first_name, s.last_name, s.nickname, s.class, s.school_id,
           sp.exam_type, sp.persona_mode, sp.target_profession,
           MAX(du.usage_date) as last_active,
           DATEDIFF(CURDATE(), MAX(du.usage_date)) as days_absent
    FROM students s
    JOIN aibuke_student_profiles sp ON s.id = sp.student_id
    JOIN aibuke_daily_usage du ON s.id = du.student_id
    GROUP BY s.id
    HAVING days_absent IN (1, 3, 7)
");
$absentStudents = $stmt->fetchAll();

echo "Gelmemiş öğrenci: " . count($absentStudents) . "\n\n";

$sent = 0;

foreach ($absentStudents as $s) {
    $daysAbsent = (int) $s['days_absent'];

    // Günlük max 1 bildirim kontrolü (spam önleme)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM aibuke_points
        WHERE student_id = ? AND description LIKE '%geri çağırma%'
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$s['id']]);
    if ((int) $stmt->fetchColumn() > 0) {
        echo "[ATLA] {$s['first_name']} - bugün zaten bildirim gönderilmiş\n";
        continue;
    }

    // Son çalışılan konuları al
    $stmt = $pdo->prepare("
        SELECT DISTINCT subject FROM aibuke_student_progress
        WHERE student_id = ? ORDER BY last_studied_at DESC LIMIT 3
    ");
    $stmt->execute([$s['id']]);
    $lastTopics = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Geri çağırma mesajı üret
    $result = $beyin->generateComebackMessage($s, $s, $daysAbsent, $lastTopics);

    if ($result['success']) {
        $message = $result['text'];

        // Bildirim gönder
        echo "[{$daysAbsent} gün] {$s['first_name']} - {$message}\n";

        // Push notification
        $stmt = $pdo->prepare("
            SELECT endpoint, p256dh_key, auth_key FROM aibuke_push_subscriptions
            WHERE student_id = ? AND is_active = 1
        ");
        $stmt->execute([$s['id']]);
        $sub = $stmt->fetch();
        if ($sub) {
            $payload = json_encode([
                'title' => 'Aibüke Öğretmen',
                'body'  => $message,
                'icon'  => '/assets/images/aibuke-icon.png',
                'url'   => APP_URL . '/chat.php'
            ]);
            $ch = curl_init($sub['endpoint']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'TTL: 86400'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            $pushResult = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode >= 200 && $httpCode < 300) $sent++;
        }

        // Gönderim logu kaydet (spam önleme)
        $pdo->prepare("INSERT INTO aibuke_points (student_id, school_id, action_type, points, description) VALUES (?, ?, 'comeback', 0, 'geri çağırma bildirimi')")
            ->execute([$s['id'], $s['school_id']]);
    } else {
        echo "[HATA] {$s['first_name']} - {$result['error']}\n";
    }

    sleep(1);
}

echo "\n=== {$sent} geri çağırma bildirimi gönderildi ===\n";
