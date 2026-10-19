<?php
/**
 * Aibuke - Sesli Mesaj Üretici (Google Neural2 TTS)
 * Özel anlar için sunucu tarafında MP3 üretir
 * Cron: Her sabah 07:30
 * php /var/www/aibuke/cron/generate_voice.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Beyin.php';
require_once __DIR__ . '/../includes/PointsManager.php';

echo "=== Sesli Mesaj Üretimi ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

// Google TTS key kontrolü
if (!defined('GOOGLE_TTS_API_KEY') || GOOGLE_TTS_API_KEY === 'YOUR-GOOGLE-TTS-KEY') {
    echo "GOOGLE_TTS_API_KEY tanımlanmamış, çıkılıyor.\n";
    exit;
}

// Aylık kullanım kontrolü
$currentMonth = date('Y-m-01');
$stmt = $pdo->prepare("SELECT total_characters FROM aibuke_tts_usage WHERE usage_month = ?");
$stmt->execute([$currentMonth]);
$used = (int) ($stmt->fetchColumn() ?: 0);

if ($used >= 900000) { // 1M limitin %90'ına yaklaşılmışsa dur
    echo "Aylık TTS limiti %90'a ulaştı ({$used} karakter). Durduruluyor.\n";
    exit;
}

$beyin = new Beyin();
$pm = new PointsManager($pdo);

// Voice dizini
$voiceDir = __DIR__ . '/../uploads/voice';
if (!is_dir($voiceDir)) {
    mkdir($voiceDir, 0755, true);
}

// Aktif öğrenciler (son 3 günde girmiş)
$stmt = $pdo->query("
    SELECT DISTINCT s.id, s.first_name, s.nickname,
           sp.exam_type, sp.target_profession, sp.persona_mode
    FROM students s
    JOIN aibuke_student_profiles sp ON s.id = sp.student_id
    WHERE s.id IN (
        SELECT DISTINCT student_id FROM aibuke_daily_usage
        WHERE usage_date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
    )
    LIMIT 50
");
$students = $stmt->fetchAll();

echo "Hedef öğrenci: " . count($students) . "\n\n";

$generated = 0;
$totalChars = 0;

foreach ($students as $s) {
    // Bugün zaten sesli mesaj var mı?
    $stmt = $pdo->prepare("
        SELECT id FROM aibuke_voice_messages
        WHERE student_id = ? AND message_type = 'morning_greeting'
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$s['id']]);
    if ($stmt->fetch()) continue;

    // Bağlamsal veri topla (tekrarsız, kişiselleştirilmiş mesaj için)
    $extraContext = buildStudentContext($pdo, $pm, $s['id']);

    // Kısa sabah mesajı üret
    $result = $beyin->generateNotification($s, $s, 'morning', $extraContext);
    if (!$result['success']) continue;

    $text = $result['text'];
    $charCount = mb_strlen($text);

    // Limit kontrolü
    if ($used + $totalChars + $charCount > 950000) {
        echo "Limit yaklaşıyor, durduruluyor.\n";
        break;
    }

    // Emojileri temizle (TTS okumasın)
    $ttsText = preg_replace('/[\x{1F000}-\x{1FFFF}]|[\x{2600}-\x{27BF}]|[\x{FE00}-\x{FE0F}]|[\x{1F900}-\x{1F9FF}]|[\x{200D}]|[\x{20E3}]|[\x{E0020}-\x{E007F}]|#/u', '', $text);
    $ttsText = preg_replace('/\s{2,}/', ' ', trim($ttsText));

    // Google TTS ile ses üret
    $audioContent = generateTTSAudio($ttsText);
    if (!$audioContent) {
        echo "[HATA] {$s['first_name']} - TTS başarısız\n";
        continue;
    }

    // MP3 kaydet (unique dosya adı - cache sorunu önlenir)
    $filename = 'morning_' . $s['id'] . '_' . date('Ymd_His') . '.mp3';
    file_put_contents("{$voiceDir}/{$filename}", base64_decode($audioContent));

    // DB kaydet
    $stmt = $pdo->prepare("
        INSERT INTO aibuke_voice_messages (student_id, message_type, text_content, audio_path, char_count)
        VALUES (?, 'morning_greeting', ?, ?, ?)
    ");
    $stmt->execute([$s['id'], $text, $filename, $charCount]);

    $totalChars += $charCount;
    $generated++;
    echo "[OK] {$s['first_name']} - {$charCount} karakter\n";

    usleep(500000);
}

// TTS kullanım güncelle
if ($totalChars > 0) {
    $stmt = $pdo->prepare("
        INSERT INTO aibuke_tts_usage (usage_month, total_characters)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE total_characters = total_characters + ?
    ");
    $stmt->execute([$currentMonth, $totalChars, $totalChars]);
}

echo "\n=== {$generated} sesli mesaj üretildi ({$totalChars} karakter) ===\n";

/**
 * Öğrenci için bağlamsal veri topla (mesaj çeşitliliği için)
 */
function buildStudentContext($pdo, $pm, $studentId) {
    $ctx = [];

    // 1) Streak
    $ctx['streak_days'] = $pm->calculateStreak($studentId);

    // 2) Toplam puan
    $ctx['total_points'] = $pm->getTotalPoints($studentId);

    // 3) Dünkü aktivite
    $stmt = $pdo->prepare("
        SELECT message_count FROM aibuke_daily_usage
        WHERE student_id = ? AND usage_date = CURDATE() - INTERVAL 1 DAY
    ");
    $stmt->execute([$studentId]);
    $ctx['yesterday_messages'] = (int) ($stmt->fetchColumn() ?: 0);

    // 4) Son çalışılan konular (son 3 gün)
    $stmt = $pdo->prepare("
        SELECT DISTINCT subject FROM aibuke_student_progress
        WHERE student_id = ? AND last_studied_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
        ORDER BY last_studied_at DESC LIMIT 3
    ");
    $stmt->execute([$studentId]);
    $ctx['recent_subjects'] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // 5) Zayıf konular (strength < 60)
    $stmt = $pdo->prepare("
        SELECT subject, topic FROM aibuke_student_progress
        WHERE student_id = ? AND strength_score < 60
        ORDER BY strength_score ASC LIMIT 2
    ");
    $stmt->execute([$studentId]);
    $ctx['weak_topics'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 6) Son 5 sesli mesaj metni (tekrar önleme)
    $stmt = $pdo->prepare("
        SELECT text_content FROM aibuke_voice_messages
        WHERE student_id = ? ORDER BY created_at DESC LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $ctx['previous_messages'] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // 7) Son kazanılan rozet
    $stmt = $pdo->prepare("
        SELECT b.badge_name FROM aibuke_student_badges sb
        JOIN aibuke_badges b ON sb.badge_id = b.id
        WHERE sb.student_id = ?
        ORDER BY sb.earned_at DESC LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $ctx['last_badge'] = $stmt->fetchColumn() ?: '';

    // 8) Bugün haftanın hangi günü
    $days = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
    $ctx['day_of_week'] = $days[(int) date('w')];

    return $ctx;
}

/**
 * Google Cloud TTS Neural2 ile ses üret
 */
function generateTTSAudio($text) {
    $url = 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . GOOGLE_TTS_API_KEY;

    $data = [
        'input' => ['text' => $text],
        'voice' => [
            'languageCode' => 'tr-TR',
            'name' => 'tr-TR-Wavenet-A',
            'ssmlGender' => 'FEMALE'
        ],
        'audioConfig' => [
            'audioEncoding' => 'MP3',
            'speakingRate' => 1.15,
            'pitch' => 1.5
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Google TTS Cron Error: HTTP $httpCode");
        return null;
    }

    $result = json_decode($response, true);
    return $result['audioContent'] ?? null;
}
