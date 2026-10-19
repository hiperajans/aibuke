<?php
/**
 * Aibuke - Sesli Mentor API (Google Cloud TTS Neural2)
 *
 * POST /api/tts.php → Metin → MP3 ses dosyası
 *
 * Özel anlar için sunucu tarafında ses üretimi:
 * - Sabah motivasyon mesajı
 * - Başarı kutlama
 * - Red Flag teselli
 *
 * Normal chat için Web Speech API (client-side, $0) kullanılır
 */

require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$text = trim($_POST['text'] ?? '');
$type = $_POST['type'] ?? 'chat'; // chat, morning, achievement, comfort

if (empty($text)) {
    http_response_code(400);
    echo json_encode(['error' => 'Metin boş']);
    exit;
}

// Max 500 karakter (bildirim mesajları kısa)
$text = mb_substr($text, 0, 500);

// Google Cloud TTS API key kontrolü
if (!defined('GOOGLE_TTS_API_KEY') || GOOGLE_TTS_API_KEY === 'YOUR-GOOGLE-TTS-KEY') {
    // API key yoksa fallback: client-side Web Speech API kullanılacak
    echo json_encode([
        'success' => true,
        'method' => 'web_speech_api',
        'text' => $text,
        'message' => 'Ses tarayıcıda üretilecek'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Aylık kullanım kontrolü
$currentMonth = date('Y-m-01');
$stmt = $pdo->prepare("
    SELECT total_characters, free_limit FROM aibuke_tts_usage
    WHERE usage_month = ?
");
$stmt->execute([$currentMonth]);
$usage = $stmt->fetch();

$totalUsed = $usage ? $usage['total_characters'] : 0;
$freeLimit = $usage ? $usage['free_limit'] : 1000000;
$charCount = mb_strlen($text);

if ($totalUsed + $charCount > $freeLimit) {
    // Ücretsiz limit aşılacak, client-side'a düş
    echo json_encode([
        'success' => true,
        'method' => 'web_speech_api',
        'text' => $text,
        'message' => 'Aylık ses limiti dolmak üzere, tarayıcı sesi kullanılıyor'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Emojileri temizle (TTS okumasın)
$text = preg_replace('/[\x{1F000}-\x{1FFFF}]|[\x{2600}-\x{27BF}]|[\x{FE00}-\x{FE0F}]|[\x{1F900}-\x{1F9FF}]|[\x{200D}]|[\x{20E3}]|[\x{E0020}-\x{E007F}]/u', '', $text);
$text = preg_replace('/\s{2,}/', ' ', trim($text));

// Google Cloud TTS Neural2 isteği
$url = 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . GOOGLE_TTS_API_KEY;

$data = [
    'input' => ['text' => $text],
    'voice' => [
        'languageCode' => 'tr-TR',
        'name' => 'tr-TR-Wavenet-A', // Kadın sesi
        'ssmlGender' => 'FEMALE'
    ],
    'audioConfig' => [
        'audioEncoding' => 'MP3',
        'speakingRate' => 1.15,   // Doğal hızda, akıcı
        'pitch' => 1.5,           // Biraz ince, öğretmen tonu
        'volumeGainDb' => 0
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
    error_log("Google TTS Error: HTTP $httpCode - $response");
    // Fallback to Web Speech API
    echo json_encode([
        'success' => true,
        'method' => 'web_speech_api',
        'text' => $text,
        'message' => 'Ses servisi geçici olarak kullanılamıyor'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = json_decode($response, true);
$audioContent = $result['audioContent'] ?? null;

if (!$audioContent) {
    echo json_encode(['success' => false, 'error' => 'Ses üretilemedi']);
    exit;
}

// MP3 dosyasını kaydet
$filename = 'tts_' . $_SESSION['student_id'] . '_' . time() . '.mp3';
$filepath = __DIR__ . '/../uploads/voice/' . $filename;

// voice dizini yoksa oluştur
$voiceDir = __DIR__ . '/../uploads/voice';
if (!is_dir($voiceDir)) {
    mkdir($voiceDir, 0755, true);
}

file_put_contents($filepath, base64_decode($audioContent));

// Kullanım sayacını güncelle
$stmt = $pdo->prepare("
    INSERT INTO aibuke_tts_usage (usage_month, total_characters)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE total_characters = total_characters + ?
");
$stmt->execute([$currentMonth, $charCount, $charCount]);

// Sesli mesaj kaydı
$stmt = $pdo->prepare("
    INSERT INTO aibuke_voice_messages (student_id, message_type, text_content, audio_path, char_count, is_sent, sent_at)
    VALUES (?, ?, ?, ?, ?, 1, NOW())
");
$stmt->execute([
    $_SESSION['student_id'],
    $type,
    $text,
    $filename,
    $charCount
]);

echo json_encode([
    'success' => true,
    'method' => 'neural2',
    'audio_url' => '/uploads/voice/' . $filename,
    'char_count' => $charCount,
    'monthly_used' => $totalUsed + $charCount,
    'monthly_limit' => $freeLimit
], JSON_UNESCAPED_UNICODE);
