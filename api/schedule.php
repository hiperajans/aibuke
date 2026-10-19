<?php
/**
 * Aibuke - Haftalık Çalışma Programı API
 *
 * GET  /api/schedule.php           → Aktif programı getir
 * POST /api/schedule.php           → Programı kaydet (JSON body: schedule_data)
 * GET  /api/schedule.php?action=generate → Yeni program üret
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

$action = $_GET['action'] ?? '';

// ─── GET: Aktif programı getir ───
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action !== 'generate') {
    $stmt = $pdo->prepare("
        SELECT id, title, schedule_data, schedule_type, is_active, created_at
        FROM aibuke_study_schedules
        WHERE student_id = ? AND is_active = 1
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $schedule = $stmt->fetch();

    if (!$schedule) {
        echo json_encode(['schedule' => null]);
        exit;
    }

    $schedule['schedule_data'] = json_decode($schedule['schedule_data'], true);
    echo json_encode(['schedule' => $schedule], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── POST: Yeni program üret (panel'den gelen config ile) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'generate') {
    require_once __DIR__ . '/../includes/StudentContext.php';
    require_once __DIR__ . '/../includes/Beyin.php';

    $input = json_decode(file_get_contents('php://input'), true);

    $schoolStart  = $input['school_start'] ?? '08:30';
    $schoolEnd    = $input['school_end'] ?? '15:30';
    $studyHours   = max(1, min(12, (int)($input['study_hours'] ?? 3)));
    $focusSubjects = $input['focus_subjects'] ?? [];
    $weekendPref  = $input['weekend'] ?? 'study';
    $extraNotes   = trim($input['notes'] ?? '');

    $student = getStudentBasicInfo($pdo, $studentId);
    $profile = getOrCreateProfile($pdo, $studentId, $student);

    // Override study hours in profile context
    $profile['study_hours_per_day'] = $studyHours;

    // Progress data
    $stmt = $pdo->prepare("
        SELECT subject, questions_solved, correct_answers
        FROM aibuke_student_progress
        WHERE student_id = ?
        ORDER BY updated_at DESC
        LIMIT 8
    ");
    $stmt->execute([$studentId]);
    $progress = $stmt->fetchAll();

    // Build extra preferences string from config
    $prefs = [];
    $prefs[] = "Okul saatleri: {$schoolStart} - {$schoolEnd} (bu saatlerde okulda, çalışamaz)";
    $prefs[] = "Günlük çalışma hedefi: {$studyHours} saat (okul dışında)";

    if (!empty($focusSubjects)) {
        $prefs[] = "Ağırlık verilecek dersler: " . implode(', ', $focusSubjects);
    }

    $weekendLabels = [
        'study' => 'Hafta sonu tam gün çalışabilir',
        'half'  => 'Hafta sonu yarım gün çalışabilir',
        'rest'  => 'Hafta sonu dinlenecek, program yapma'
    ];
    $prefs[] = $weekendLabels[$weekendPref] ?? $weekendLabels['study'];

    if ($extraNotes) {
        $prefs[] = "Öğrencinin notu: {$extraNotes}";
    }

    $extraPrefsStr = implode("\n", $prefs);

    $beyin = new Beyin();
    $result = $beyin->generateWeeklySchedule($student, $profile, $progress, $extraPrefsStr);

    if (!$result['success']) {
        http_response_code(500);
        echo json_encode(['error' => 'Program oluşturulamadı, tekrar dene'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'schedule' => $result['schedule']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── POST: Programı kaydet ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $scheduleData = $input['schedule_data'] ?? null;
    $title = $input['title'] ?? 'Haftalık Çalışma Programı';

    if (!$scheduleData) {
        http_response_code(400);
        echo json_encode(['error' => 'Program verisi eksik']);
        exit;
    }

    // Eski aktif programları pasifle
    $stmt = $pdo->prepare("
        UPDATE aibuke_study_schedules SET is_active = 0
        WHERE student_id = ? AND is_active = 1
    ");
    $stmt->execute([$studentId]);

    // Yeni programı kaydet
    $stmt = $pdo->prepare("
        INSERT INTO aibuke_study_schedules (student_id, title, schedule_data, schedule_type)
        VALUES (?, ?, ?, 'weekly')
    ");
    $stmt->execute([
        $studentId,
        $title,
        json_encode($scheduleData, JSON_UNESCAPED_UNICODE)
    ]);

    // Puan ver
    require_once __DIR__ . '/../includes/PointsManager.php';
    $pm = new PointsManager($pdo);
    // plan_completed action_type kullan
    $points = $pm->addPoints($studentId, 'plan_completed', 'Çalışma programı oluşturuldu');

    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'message' => 'Program kaydedildi!',
        'points_earned' => $points
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
