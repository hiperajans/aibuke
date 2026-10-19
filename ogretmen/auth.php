<?php
/**
 * Aibuke Öğretmen - Kimlik Doğrulama
 */
require_once __DIR__ . '/../config.php';

function requireTeacherLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['ogretmen_id'])) {
        header('Location: /ogretmen/');
        exit;
    }
}

function loginOgretmen($pdo, $username, $password) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.school_id, s.full_name, s.role, s.password, s.sinif_ogretmeni,
               b.branch_name as ders,
               sc.name as school_name,
               sc.principal_name as mudur_adi
        FROM staff s
        LEFT JOIN branches b ON s.branch_id = b.id
        JOIN schools sc ON s.school_id = sc.id
        WHERE s.username = ?
          AND s.role = 'teacher'
    ");
    $stmt->execute([$username]);
    $ogretmen = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ogretmen || !password_verify($password, $ogretmen['password'])) {
        return false;
    }

    $_SESSION['ogretmen_id']              = $ogretmen['id'];
    $_SESSION['ogretmen_school_id']       = $ogretmen['school_id'];
    $_SESSION['ogretmen_name']            = $ogretmen['full_name'];
    $_SESSION['ogretmen_ders']            = $ogretmen['ders'] ?? 'Belirtilmemiş';
    $_SESSION['ogretmen_school']          = $ogretmen['school_name'];
    $_SESSION['ogretmen_sinif_ogretmeni'] = $ogretmen['sinif_ogretmeni'];
    $_SESSION['ogretmen_mudur_adi']       = $ogretmen['mudur_adi'] ?? '';

    // Login successful

    return true;
}

function getOgretmenInfo() {
    return [
        'id'               => $_SESSION['ogretmen_id']              ?? null,
        'school_id'        => $_SESSION['ogretmen_school_id']       ?? null,
        'name'             => $_SESSION['ogretmen_name']            ?? '',
        'ders'             => $_SESSION['ogretmen_ders']            ?? '',
        'school'           => $_SESSION['ogretmen_school']          ?? '',
        'sinif_ogretmeni'  => $_SESSION['ogretmen_sinif_ogretmeni'] ?? null,
        'mudur_adi'        => $_SESSION['ogretmen_mudur_adi']       ?? '',
    ];
}
