<?php
/**
 * Aibuke - YouTube Video Önerisi API
 * GET /api/youtube.php?q=türev+konu+anlatımı&exam=tyt
 */

require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');
$examType = trim($_GET['exam'] ?? 'tyt');

if (empty($query)) {
    echo json_encode(['error' => 'Arama sorgusu gerekli', 'videos' => []]);
    exit;
}

// Arama sorgusunu eğitim odaklı yap
$searchQuery = $query . ' ' . strtoupper($examType) . ' konu anlatımı';

$apiKey = GEMINI_API_KEY; // Aynı Google Cloud projesi
$url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query([
    'part' => 'snippet',
    'q' => $searchQuery,
    'type' => 'video',
    'maxResults' => 2,
    'relevanceLanguage' => 'tr',
    'regionCode' => 'TR',
    'safeSearch' => 'strict',
    'videoDuration' => 'medium', // 4-20 dk arası videolar
    'key' => $apiKey
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    error_log("YouTube API Error: HTTP $httpCode - $response");
    echo json_encode(['error' => 'Video araması başarısız', 'videos' => []]);
    exit;
}

$data = json_decode($response, true);
$videos = [];

foreach ($data['items'] ?? [] as $item) {
    $videos[] = [
        'id' => $item['id']['videoId'],
        'title' => $item['snippet']['title'],
        'channel' => $item['snippet']['channelTitle'],
        'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
        'url' => 'https://www.youtube.com/watch?v=' . $item['id']['videoId']
    ];
}

echo json_encode(['success' => true, 'videos' => $videos, 'query' => $query], JSON_UNESCAPED_UNICODE);
