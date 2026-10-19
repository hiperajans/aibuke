<?php
/**
 * Aibuke Admin - Kimlik Doğrulama
 */
require_once __DIR__ . '/../config.php';

function requireAdminLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin/');
        exit;
    }
}

function loginAdmin($pdo, $username, $password) {
    $stmt = $pdo->prepare("
        SELECT sa.*, s.name as school_name
        FROM school_admins sa
        JOIN schools s ON sa.school_id = s.id
        WHERE sa.username = ? AND sa.is_active = 1
    ");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($password, $admin['password'])) {
        return false;
    }

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_school_id'] = $admin['school_id'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['admin_school'] = $admin['school_name'];
    $_SESSION['admin_role'] = $admin['role'];

    $pdo->prepare("UPDATE school_admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

    return true;
}

function getAdminInfo() {
    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'school_id' => $_SESSION['admin_school_id'] ?? null,
        'name' => $_SESSION['admin_name'] ?? '',
        'school' => $_SESSION['admin_school'] ?? '',
        'role' => $_SESSION['admin_role'] ?? ''
    ];
}
