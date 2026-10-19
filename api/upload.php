<?php
/**
 * Aibuke Ogretmen - Fotograf Yukleme API
 */

require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit;
}

// Upload klasoru
$uploadDir = __DIR__ . '/../uploads/';

// Dosya kontrolu
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Dosya yüklenemedi']);
    exit;
}

$file = $_FILES['image'];

// Boyut kontrolu
$maxSize = defined('MAX_IMAGE_SIZE') ? MAX_IMAGE_SIZE : (5 * 1024 * 1024);
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'Dosya çok büyük. Max 5MB.']);
    exit;
}

// Tip kontrolu
$allowedTypes = defined('UPLOAD_ALLOWED_TYPES') ? UPLOAD_ALLOWED_TYPES : ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Sadece JPG, PNG ve WEBP dosyaları yüklenebilir']);
    exit;
}

// Extension
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array(strtolower($ext), $allowedExts)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz dosya tipi']);
    exit;
}

// Benzersiz dosya adi
$newName = sprintf('%d_%d_%s.%s', $_SESSION['student_id'], time(), bin2hex(random_bytes(4)), $ext);
$targetPath = $uploadDir . $newName;

// Yukle
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    chmod($targetPath, 0644);

    echo json_encode([
        'success' => true,
        'filename' => $newName,
        'url' => '/uploads/' . $newName,
        'size' => $file['size']
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Dosya kaydedilemedi']);
}
