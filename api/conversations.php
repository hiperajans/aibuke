<?php
/**
 * Aibuke Ogretmen - Konusma Yonetimi API
 */

require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit;
}

$studentId = $_SESSION['student_id'];

// GET: Konusma listele
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
        SELECT id, title, summary, is_active, created_at, updated_at
        FROM aibuke_conversations
        WHERE student_id = ?
        ORDER BY updated_at DESC
        LIMIT 20
    ");
    $stmt->execute([$studentId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['conversations' => $conversations]);
    exit;
}

// POST: Yeni konusma olustur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $title = $data['title'] ?? 'Yeni Sohbet';

    $stmt = $pdo->prepare("
        INSERT INTO aibuke_conversations (student_id, school_id, title)
        VALUES (?, (SELECT school_id FROM students WHERE id = ?), ?)
    ");
    $stmt->execute([$studentId, $studentId, $title]);

    echo json_encode([
        'success' => true,
        'conversation_id' => $pdo->lastInsertId()
    ]);
    exit;
}

// DELETE: Konusma sil
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE aibuke_conversations
        SET is_active = 0
        WHERE id = ? AND student_id = ?
    ");
    $stmt->execute([$id, $studentId]);

    echo json_encode(['success' => true]);
    exit;
}
