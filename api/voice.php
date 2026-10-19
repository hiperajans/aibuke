<?php
/**
 * Aibuke - Sesli Mesaj API
 *
 * GET ?action=pending  → Okunmamış sesli mesajları getir
 * POST ?action=mark_read → Mesajı okundu işaretle
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
$action = $_GET['action'] ?? $_POST['action'] ?? 'pending';

switch ($action) {

    case 'pending':
        // Bugünün okunmamış sesli mesajlarını getir
        $stmt = $pdo->prepare("
            SELECT id, message_type, text_content, audio_path, created_at
            FROM aibuke_voice_messages
            WHERE student_id = ?
              AND is_sent = 0
              AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$studentId]);
        $messages = $stmt->fetchAll();

        // audio_path → tam URL
        foreach ($messages as &$m) {
            $m['audio_url'] = '/uploads/voice/' . $m['audio_path'];
            unset($m['audio_path']);
        }

        echo json_encode([
            'success' => true,
            'messages' => $messages
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'mark_read':
        $messageId = (int) ($_POST['message_id'] ?? 0);
        if (!$messageId) {
            echo json_encode(['error' => 'message_id gerekli']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE aibuke_voice_messages
            SET is_sent = 1, sent_at = NOW()
            WHERE id = ? AND student_id = ?
        ");
        $stmt->execute([$messageId, $studentId]);

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Geçersiz action']);
}
