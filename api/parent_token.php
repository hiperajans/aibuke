<?php
/**
 * Aibuke - Veli Token Oluşturma API
 * POST: action = generate → Öğrenci kendi velisi için token oluşturur
 * GET: action = status → Token durumu
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
session_start();
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$studentId = $_SESSION['student_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'status';

switch ($action) {
    case 'status':
        $stmt = $pdo->prepare("SELECT token, parent_email, kvkk_consent, last_accessed, created_at FROM aibuke_parent_tokens WHERE student_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$studentId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $existing['url'] = APP_URL . '/veli/?t=' . $existing['token'];
        }
        echo json_encode(['token' => $existing], JSON_UNESCAPED_UNICODE);
        break;

    case 'generate':
        // Mevcut aktif token var mı
        $stmt = $pdo->prepare("SELECT id, token FROM aibuke_parent_tokens WHERE student_id = ? AND is_active = 1");
        $stmt->execute([$studentId]);
        $existing = $stmt->fetch();

        if ($existing) {
            echo json_encode([
                'success' => true,
                'token' => $existing['token'],
                'url' => APP_URL . '/veli/?t=' . $existing['token'],
                'message' => 'Mevcut bağlantın aktif.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Yeni token oluştur
        $token = bin2hex(random_bytes(32));
        $email = trim($_POST['parent_email'] ?? '');

        $pdo->prepare("INSERT INTO aibuke_parent_tokens (student_id, token, parent_email) VALUES (?, ?, ?)")
            ->execute([$studentId, $token, $email ?: null]);

        $url = APP_URL . '/veli/?t=' . $token;

        echo json_encode([
            'success' => true,
            'token' => $token,
            'url' => $url,
            'message' => 'Veli bağlantısı oluşturuldu.'
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'revoke':
        $pdo->prepare("UPDATE aibuke_parent_tokens SET is_active = 0 WHERE student_id = ?")->execute([$studentId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Geçersiz aksiyon']);
}
