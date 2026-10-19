<?php
/**
 * Aibuke - Deneme Sınavı Optik Analizi API
 *
 * POST /api/exam_analysis.php   → Karne fotoğrafı yükle + analiz başlat
 * GET  /api/exam_analysis.php   → Geçmiş analizleri listele
 */

require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/GeminiAPI.php';
require_once __DIR__ . '/../includes/PointsManager.php';

$studentId = $_SESSION['student_id'];
$student = getStudentInfo($pdo, $studentId);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Geçmiş analizleri listele
    $stmt = $pdo->prepare("
        SELECT id, exam_type, exam_date, total_correct, total_wrong, total_empty,
               net_score, ai_recommendation, created_at
        FROM aibuke_exam_analyses
        WHERE student_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$studentId]);
    $analyses = $stmt->fetchAll();

    echo json_encode(['analyses' => $analyses], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: Yeni analiz
if (!isset($_FILES['exam_photo']) || $_FILES['exam_photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Karne fotoğrafı yüklenmedi']);
    exit;
}

$file = $_FILES['exam_photo'];
$examType = trim($_POST['exam_type'] ?? ''); // lgs_deneme, yks_tyt, yks_ayt, okul_sinavi

// Dosya kontrolü
$maxSize = defined('MAX_IMAGE_SIZE') ? MAX_IMAGE_SIZE : 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'Dosya çok büyük (max 5MB)']);
    exit;
}

$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Sadece JPEG, PNG veya WebP']);
    exit;
}

// Dosyayı kaydet
$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$filename = 'exam_' . $studentId . '_' . time() . '.' . $ext;
$filepath = __DIR__ . '/../uploads/' . $filename;
move_uploaded_file($file['tmp_name'], $filepath);

// Base64'e çevir
$imageBase64 = base64_encode(file_get_contents($filepath));

// Gemini ile analiz et
$gemini = new GeminiAPI();

$prompt = "Bu bir deneme sınavı sonuç karnesi/optik form fotoğrafı.

Lütfen şu bilgileri JSON formatında çıkar:
{
  \"exam_type\": \"LGS/YKS-TYT/YKS-AYT/Okul Sınavı\",
  \"subjects\": [
    {
      \"name\": \"Matematik\",
      \"correct\": 12,
      \"wrong\": 5,
      \"empty\": 3,
      \"net\": 10.75,
      \"topics_weak\": [\"Parabol\", \"Türev\"],
      \"topics_strong\": [\"Limit\", \"Fonksiyon\"]
    }
  ],
  \"total_correct\": 50,
  \"total_wrong\": 20,
  \"total_empty\": 10,
  \"overall_net\": 45.0,
  \"recommendation\": \"Kısa bir değerlendirme ve öneri (Türkçe, 2-3 cümle)\"
}

Eğer fotoğraftan okunamıyorsa veya sınav karnesi değilse, şu JSON'ı döndür:
{\"error\": \"Bu fotoğraftan sınav sonucu okunamadı. Lütfen daha net bir fotoğraf çek.\"}

Sadece JSON döndür, başka metin yazma.";

$context = [
    'persona_mode' => 'koc',
    'student' => $student,
    'profile' => ['exam_type' => $examType ?: 'yks']
];

$result = $gemini->analyzeImage($imageBase64, $prompt, $context);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(['error' => $result['error']]);
    exit;
}

// JSON parse
$analysisText = $result['text'];
$analysis = null;

// JSON çıkar
if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $analysisText, $m)) {
    $analysis = json_decode(trim($m[1]), true);
} elseif (preg_match('/\{[\s\S]*\}/', $analysisText, $m)) {
    $analysis = json_decode($m[0], true);
}

if (!$analysis || isset($analysis['error'])) {
    echo json_encode([
        'success' => false,
        'error' => $analysis['error'] ?? 'Karne analiz edilemedi. Lütfen daha net bir fotoğraf çek.',
        'raw' => $analysisText
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Zayıf ve güçlü konuları topla
$weakTopics = [];
$strongTopics = [];
foreach ($analysis['subjects'] ?? [] as $subj) {
    foreach ($subj['topics_weak'] ?? [] as $t) {
        $weakTopics[] = $subj['name'] . ': ' . $t;
    }
    foreach ($subj['topics_strong'] ?? [] as $t) {
        $strongTopics[] = $subj['name'] . ': ' . $t;
    }
}

// DB'ye kaydet
$stmt = $pdo->prepare("
    INSERT INTO aibuke_exam_analyses
    (student_id, school_id, exam_type, exam_date, image_path, analysis_result,
     weak_topics, strong_topics, total_correct, total_wrong, total_empty, net_score, ai_recommendation)
    VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $studentId,
    $student['school_id'],
    $analysis['exam_type'] ?? $examType,
    $filename,
    json_encode($analysis, JSON_UNESCAPED_UNICODE),
    json_encode($weakTopics, JSON_UNESCAPED_UNICODE),
    json_encode($strongTopics, JSON_UNESCAPED_UNICODE),
    $analysis['total_correct'] ?? 0,
    $analysis['total_wrong'] ?? 0,
    $analysis['total_empty'] ?? 0,
    $analysis['overall_net'] ?? 0,
    $analysis['recommendation'] ?? ''
]);

$analysisId = $pdo->lastInsertId();

// Zayıf konuları student_progress'e yansıt
foreach ($analysis['subjects'] ?? [] as $subj) {
    foreach ($subj['topics_weak'] ?? [] as $topic) {
        $stmt = $pdo->prepare("
            INSERT INTO aibuke_student_progress (student_id, subject, topic, strength_score, questions_solved, correct_answers)
            VALUES (?, ?, ?, 30, ?, ?)
            ON DUPLICATE KEY UPDATE
                strength_score = LEAST(strength_score, 30),
                questions_solved = questions_solved + VALUES(questions_solved),
                correct_answers = correct_answers + VALUES(correct_answers)
        ");
        $stmt->execute([
            $studentId,
            $subj['name'],
            $topic,
            ($subj['correct'] ?? 0) + ($subj['wrong'] ?? 0),
            $subj['correct'] ?? 0
        ]);
    }
}

// Puan ver
$pm = new PointsManager($pdo);
$pm->addPoints($studentId, 'exam_uploaded', 'Deneme sınavı analizi');

// Geçmiş ile karşılaştırma
$stmt = $pdo->prepare("
    SELECT net_score, created_at FROM aibuke_exam_analyses
    WHERE student_id = ? AND id != ?
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$studentId, $analysisId]);
$prevExam = $stmt->fetch();

$comparison = null;
if ($prevExam) {
    $diff = ($analysis['overall_net'] ?? 0) - $prevExam['net_score'];
    $comparison = [
        'previous_net' => $prevExam['net_score'],
        'current_net' => $analysis['overall_net'] ?? 0,
        'difference' => round($diff, 2),
        'improved' => $diff > 0
    ];
}

echo json_encode([
    'success' => true,
    'analysis_id' => $analysisId,
    'analysis' => $analysis,
    'weak_topics' => $weakTopics,
    'strong_topics' => $strongTopics,
    'comparison' => $comparison,
    'recommendation' => $analysis['recommendation'] ?? '',
    'points_earned' => 20
], JSON_UNESCAPED_UNICODE);
