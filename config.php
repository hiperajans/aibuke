<?php
/**
 * Aibuke Ogretmen - Yapılandırma Dosyası
 * Veritabanı bağlantısı ve API anahtarları
 *
 * Gizli değerler koda yazılmaz; .env dosyasından okunur (bkz. .env.example).
 */

// =================================================================
// 0. .env DOSYASINI YÜKLE
// =================================================================
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Yorum satırını atla
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!getenv($key)) { // Sadece henüz tanımlanmamış olanları set et
                putenv("$key=$value");
            }
        }
    }
}

// =================================================================
// 1. VERİTABANI BAĞLANTI BİLGİLERİ
// =================================================================

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'aibuke';
$user = getenv('DB_USER') ?: 'aibuke';
$pass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;port=3306;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci"
    ]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET character_set_connection=utf8mb4");
    $pdo->exec("SET character_set_client=utf8mb4");
    $pdo->exec("SET character_set_results=utf8mb4");
    $pdo->exec("SET collation_connection=utf8mb4_turkish_ci");
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// =================================================================
// 2. API ANAHTARLARI
// =================================================================

/**
 * Gemini Flash API - Soru çözümü, OCR, konu anlatımı
 * Endpoint: https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent
 * Fiyat: $0.075/1M input, $0.30/1M output
 */
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL', 'gemini-3.1-flash-lite-preview');

/**
 * MiniMax API - Soru havuzu PDF parse, hata analizi, plan üretimi
 * Endpoint: https://api.minimax.io/v1/text/chatcompletion_v2
 * Model: MiniMax-M3
 */
define('MINIMAX_API_KEY', getenv('MINIMAX_API_KEY') ?: '');
define('MINIMAX_MODEL', 'MiniMax-M3');

/**
 * Google Cloud TTS Neural2 - Sesli Mentor (Faz 2)
 * Ses: tr-TR-Wavenet-A (kadın)
 * Ücretsiz limit: 1M karakter/ay
 */
define('GOOGLE_TTS_API_KEY', getenv('GOOGLE_TTS_API_KEY') ?: '');

/**
 * Web Push (VAPID) - bildirimler için, boş bırakılırsa push gönderilmez
 */
define('VAPID_PUBLIC_KEY', getenv('VAPID_PUBLIC_KEY') ?: '');
define('VAPID_PRIVATE_KEY', getenv('VAPID_PRIVATE_KEY') ?: '');

// =================================================================
// 3. AYARLAR
// =================================================================

// Uygulamanın dış adresi (veli linkleri, push bildirimleri)
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost', '/'));

// Rate limiting
define('DAILY_MESSAGE_LIMIT', 50); // Günlük mesaj limiti
define('MAX_MESSAGE_LENGTH', 2000); // Maksimum mesaj karakteri
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Session ayarları — session başlamadan önce set et
define('SESSION_NAME', 'AIBUKE_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_name(SESSION_NAME);
}

// AI Modelleri için zaman aşımı (saniye)
define('AI_TIMEOUT', 120);

// Context penceresi ayarları
define('MAX_CONTEXT_MESSAGES', 10); // AI'a gönderilecek son mesaj sayısı (implicit cache tekrar eden kısımları otomatik ucuzlatır)
define('CONVERSATION_SUMMARY_THRESHOLD', 30); // Özet için mesaj sayısı
