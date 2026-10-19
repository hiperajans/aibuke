<?php
/**
 * Aibuke - Günlük Plan API
 *
 * GET /api/plans.php              → Bugünkü plan
 * GET /api/plans.php?date=Y-m-d   → Belirli gün planı
 * POST /api/plans.php             → Planı tamamlandı işaretle
 */

require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit;
}

$studentId = $_SESSION['student_id'];
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = $_GET['date'] ?? date('Y-m-d');

    // Tarih formatı kontrolü
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Geçersiz tarih formatı']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT plan_date, plan_content, weak_topics, motivation_tip, completed, created_at
        FROM aibuke_daily_plans
        WHERE student_id = ? AND plan_date = ?
    ");
    $stmt->execute([$studentId, $date]);
    $plan = $stmt->fetch();

    if (!$plan) {
        echo json_encode(['plan' => null, 'message' => 'Bugün için plan henüz oluşturulmamış']);
        exit;
    }

    // JSON alanları decode et
    $plan['plan_content'] = json_decode($plan['plan_content'], true);
    $plan['weak_topics'] = json_decode($plan['weak_topics'], true);

    echo json_encode(['plan' => $plan], JSON_UNESCAPED_UNICODE);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = date('Y-m-d');

    $stmt = $pdo->prepare("
        UPDATE aibuke_daily_plans SET completed = 1
        WHERE student_id = ? AND plan_date = ? AND completed = 0
    ");
    $stmt->execute([$studentId, $date]);

    if ($stmt->rowCount() > 0) {
        // Puan ver
        require_once __DIR__ . '/../includes/PointsManager.php';
        $pm = new PointsManager($pdo);
        $points = $pm->addPoints($studentId, 'plan_completed', 'Günlük plan tamamlandı');

        echo json_encode([
            'success' => true,
            'message' => 'Plan tamamlandı olarak işaretlendi!',
            'points_earned' => $points
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Plan bulunamadı veya zaten tamamlanmış']);
    }
}
