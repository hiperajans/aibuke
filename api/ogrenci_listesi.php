<?php
/**
 * Öğretmenin okulundaki öğrencileri döndürür
 * GET /api/ogrenci_listesi.php?sinif=9-A
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ogretmen/auth.php';

session_start();
requireTeacherLogin();

header('Content-Type: application/json; charset=utf-8');

$ogretmen = getOgretmenInfo();
$schoolId = (int)$ogretmen['school_id'];
$sinif    = trim($_GET['sinif'] ?? '');

if ($sinif) {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, class
        FROM students
        WHERE school_id = ? AND class = ?
        ORDER BY last_name, first_name
    ");
    $stmt->execute([$schoolId, $sinif]);
} else {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, class
        FROM students
        WHERE school_id = ?
        ORDER BY class, last_name, first_name
    ");
    $stmt->execute([$schoolId]);
}

$ogrenciler = $stmt->fetchAll(PDO::FETCH_ASSOC);

$list = array_map(fn($s) => [
    'id'    => $s['id'],
    'ad'    => $s['first_name'] . ' ' . $s['last_name'],
    'sinif' => $s['class'],
], $ogrenciler);

echo json_encode(['success' => true, 'ogrenciler' => $list], JSON_UNESCAPED_UNICODE);
