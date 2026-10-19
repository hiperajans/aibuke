<?php
/**
 * Aibuke Ogretmen - Sohbet Gecmisi API
 */

require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

$studentId = $_SESSION['student_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

// Aktif konusmayi al
$stmt = $pdo->prepare("
    SELECT id FROM aibuke_conversations
    WHERE student_id = ? AND is_active = 1
    ORDER BY updated_at DESC
    LIMIT 1
");
$stmt->execute([$studentId]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    echo json_encode(['messages' => []]);
    exit;
}

// Son N mesaji al (yeniden eskiye, sonra tersine çevir)
$stmt = $pdo->prepare("
    SELECT id, role, content, has_image, sentiment_score, created_at, youtube_query
    FROM aibuke_messages
    WHERE conversation_id = ?
    ORDER BY created_at DESC
    LIMIT ?
");
$stmt->execute([$conversation['id'], $limit]);
$messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

// Formatla — DB rolleri (student/aibuke/blocked) → frontend rolleri (user/ai)
$roleMap = ['student' => 'user', 'aibuke' => 'ai', 'blocked' => 'user'];

$formatted = array_map(function($msg) use ($roleMap) {
    return [
        'id' => $msg['id'],
        'role' => $roleMap[$msg['role']] ?? $msg['role'],
        'content' => $msg['content'],
        'has_image' => (bool)$msg['has_image'],
        'sentiment_score' => $msg['sentiment_score'],
        'created_at' => date('H:i', strtotime($msg['created_at'])),
        'youtube_query' => $msg['youtube_query'] ?? null
    ];
}, $messages);

echo json_encode([
    'messages' => $formatted,
    'conversation_id' => $conversation['id']
]);
