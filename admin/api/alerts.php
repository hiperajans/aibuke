<?php
require_once __DIR__ . '/../auth.php';
session_start();
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Yetkisiz']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'resolve':
        $alertId = (int) ($input['alert_id'] ?? 0);
        if ($alertId > 0) {
            $stmt = $pdo->prepare("UPDATE aibuke_sentiment_alerts SET is_resolved = 1, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['admin_name'], $alertId]);
            echo json_encode(['success' => true]);
        }
        break;
    default:
        echo json_encode(['error' => 'Geçersiz aksiyon']);
}
