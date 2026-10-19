<?php
/**
 * Aibuke Ogretmen - Kimlik Dogrulama Fonksiyonlari
 */

// Oturum kontrolu
function requireLogin() {
    if (!isset($_SESSION['student_id'])) {
        header('Location: index.php');
        exit;
    }
}

// Ogrenci bilgilerini getir
function getStudentInfo($pdo, $studentId) {
    $stmt = $pdo->prepare("
        SELECT s.*, sch.name as school_name
        FROM students s
        LEFT JOIN schools sch ON s.school_id = sch.id
        WHERE s.id = ?
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Giris kontrolu
function loginStudent($pdo, $username, $password) {
    $stmt = $pdo->prepare("
        SELECT id, password
        FROM students
        WHERE username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ['success' => false, 'message' => 'Kullanıcı bulunamadı'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Şifre hatalı'];
    }

    // Oturum baslat
    $_SESSION['student_id'] = $user['id'];
    $_SESSION['login_time'] = time();

    return ['success' => true, 'student_id' => $user['id']];
}

// Oturumu kapat
function logoutStudent() {
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    header('Location: index.php');
    exit;
}

// Gunluk mesaj limiti kontrolu
function checkDailyLimit($pdo, $studentId) {
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT message_count FROM aibuke_daily_usage
        WHERE student_id = ? AND usage_date = ?
    ");
    $stmt->execute([$studentId, $today]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);

    $count = $usage ? $usage['message_count'] : 0;
    $limit = defined('DAILY_MESSAGE_LIMIT') ? DAILY_MESSAGE_LIMIT : 50;

    return [
        'remaining' => max(0, $limit - $count),
        'used' => $count,
        'limit' => $limit,
        'can_send' => $count < $limit
    ];
}

// Mesaj sayisini artir
function incrementMessageCount($pdo, $studentId, $tokensUsed = 0) {
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("
        INSERT INTO aibuke_daily_usage (student_id, usage_date, message_count, tokens_consumed)
        VALUES (?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE message_count = message_count + 1, tokens_consumed = tokens_consumed + ?
    ");
    $stmt->execute([$studentId, $today, $tokensUsed, $tokensUsed]);
}
