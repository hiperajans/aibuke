<?php
/**
 * Aibuke - Puan & Rozet API
 *
 * GET /api/points.php?action=profile     → Öğrenci puan profili
 * GET /api/points.php?action=leaderboard → Okul liderlik tablosu
 * GET /api/points.php?action=badges      → Öğrenci rozetleri
 */

require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/PointsManager.php';

$pm = new PointsManager($pdo);
$studentId = $_SESSION['student_id'];
$action = $_GET['action'] ?? 'profile';

// Öğrenci bilgilerini al
$student = getStudentInfo($pdo, $studentId);
if (!$student) {
    http_response_code(404);
    echo json_encode(['error' => 'Öğrenci bulunamadı']);
    exit;
}

$schoolId = $student['school_id'];

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'profile':
        $profile = $pm->getStudentProfile($studentId, $schoolId);
        echo json_encode($profile, JSON_UNESCAPED_UNICODE);
        break;

    case 'leaderboard':
        $limit = min((int) ($_GET['limit'] ?? 20), 50);
        $period = in_array($_GET['period'] ?? '', ['weekly', 'monthly', 'all']) ? $_GET['period'] : 'all';
        $class = !empty($_GET['class']) ? trim($_GET['class']) : null;
        $leaderboard = $pm->getLeaderboard($schoolId, $limit, $period, $class);

        // İsimleri kısalt (gizlilik)
        foreach ($leaderboard as &$entry) {
            $name = $entry['nickname'] ?: $entry['first_name'];
            $entry['display_name'] = $name . ' ' . mb_substr($entry['last_name'], 0, 1) . '.';
            unset($entry['first_name'], $entry['last_name'], $entry['nickname']);
        }

        echo json_encode([
            'leaderboard' => $leaderboard,
            'my_rank'     => $pm->getStudentRank($studentId, $schoolId),
            'my_points'   => $pm->getTotalPoints($studentId)
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'badges':
        $badges = $pm->getStudentBadges($studentId);
        echo json_encode(['badges' => $badges], JSON_UNESCAPED_UNICODE);
        break;

    case 'competition':
        $data = $pm->getCompetitionData($studentId, $schoolId);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        break;

    case 'calendar':
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
        $year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        $stmt = $pdo->prepare("
            SELECT usage_date, message_count
            FROM aibuke_daily_usage
            WHERE student_id = ? AND usage_date BETWEEN ? AND ?
            ORDER BY usage_date
        ");
        $stmt->execute([$studentId, $startDate, $endDate]);
        $days = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $streak = $pm->calculateStreak($studentId);

        echo json_encode([
            'year'    => $year,
            'month'   => $month,
            'days'    => $days,
            'streak'  => $streak
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Geçersiz action']);
}
