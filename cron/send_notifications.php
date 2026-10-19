<?php
/**
 * Aibuke - Bildirim Gönderici
 * Cron örnekleri:
 *   08:00 → php send_notifications.php morning
 *   14:00 → php send_notifications.php reminder
 *   21:00 → php send_notifications.php night
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Beyin.php';

$type = $argv[1] ?? 'reminder';
$validTypes = ['morning', 'reminder', 'night'];

if (!in_array($type, $validTypes)) {
    echo "Kullanım: php send_notifications.php [morning|reminder|night]\n";
    exit(1);
}

echo "=== Bildirim Gönderimi ({$type}) ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

$beyin = new Beyin();

// Aktif öğrencileri al (son 14 günde girmiş)
$stmt = $pdo->query("
    SELECT DISTINCT s.id, s.first_name, s.last_name, s.nickname, s.class, s.school_id,
           sp.exam_type, sp.persona_mode, sp.target_profession
    FROM students s
    JOIN aibuke_student_profiles sp ON s.id = sp.student_id
    WHERE s.id IN (
        SELECT DISTINCT student_id FROM aibuke_daily_usage
        WHERE usage_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    )
");
$students = $stmt->fetchAll();

echo "Hedef öğrenci: " . count($students) . "\n\n";

$sent = 0;

foreach ($students as $s) {
    $result = $beyin->generateNotification($s, $s, $type);

    if ($result['success']) {
        $message = $result['text'];

        // Push notification gönder (VAPID)
        $notifSent = sendPushNotification($pdo, $s['id'], 'Aibuke Öğretmen', $message);

        if ($notifSent) {
            $sent++;
            echo "[OK] {$s['first_name']} - {$message}\n";
        } else {
            echo "[ATLA] {$s['first_name']} - push aboneliği yok\n";
        }
    } else {
        echo "[HATA] {$s['first_name']} - {$result['error']}\n";
    }

    // Rate limit
    usleep(500000); // 0.5 saniye
}

echo "\n=== {$sent} bildirim gönderildi ===\n";

/**
 * Push notification gönder (VAPID)
 */
function sendPushNotification($pdo, $studentId, $title, $body) {
    // Push aboneliğini kontrol et
    $stmt = $pdo->prepare("
        SELECT endpoint, p256dh_key, auth_key
        FROM aibuke_push_subscriptions
        WHERE student_id = ? AND is_active = 1
    ");
    $stmt->execute([$studentId]);
    $sub = $stmt->fetch();

    if (!$sub) return false;

    // VAPID anahtarlarını kontrol et
    $vapidPublic = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : null;
    $vapidPrivate = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : null;

    if (!$vapidPublic || !$vapidPrivate) {
        // VAPID henüz yapılandırılmamış
        error_log("VAPID keys not configured for push notifications");
        return false;
    }

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'icon'  => '/assets/images/aibuke-icon.png',
        'url'   => APP_URL . '/chat.php'
    ]);

    // Web Push kütüphanesi varsa kullan, yoksa basit curl
    $ch = curl_init($sub['endpoint']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'TTL: 86400'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}
