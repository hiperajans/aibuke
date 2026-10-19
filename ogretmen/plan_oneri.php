<?php
/**
 * Aibuke Ogretmen - Plan Oneri API
 * Tarih/sinif girdisi ile ders oneri doner
 * Endpoint: GET ?tarih=YYYY-MM-DD&sinif=5A
 */

require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();

$ogretmen = getOgretmenInfo();
$schoolId = (int)$ogretmen['school_id'];
$ogretmenId = $ogretmen['id'];

header('Content-Type: application/json');

$tarih = trim($_GET['tarih'] ?? '');
$sinif  = trim($_GET['sinif'] ?? '');

if (!$tarih || !$sinif) {
    echo json_encode(['success' => false, 'error' => 'tarih ve sinif parametreleri zorunlu']);
    exit;
}

// Tarih format kontrolü
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih)) {
    echo json_encode(['success' => false, 'error' => 'Gecersiz tarih formatı (YYYY-MM-DD)']);
    exit;
}

// 1. Okul takvim kontrolü — tatil mi?
$stmt = $pdo->prepare("SELECT event_name, event_type FROM okul_takvim
    WHERE school_id IN (0, ?) AND ? BETWEEN start_date AND end_date AND is_teaching_day = 0
    LIMIT 1");
$stmt->execute([$schoolId, $tarih]);
$tatilEvent = $stmt->fetch(PDO::FETCH_ASSOC);

if ($tatilEvent) {
    echo json_encode([
        'success' => true,
        'type' => 'tatil',
        'event_name' => $tatilEvent['event_name'],
        'event_type' => $tatilEvent['event_type'],
        'tarih' => $tarih,
        'sinif' => $sinif,
        'oneri' => null
    ]);
    exit;
}

// 2. Hangi donem ve hafta?
$haftaRow = $pdo->prepare("
    SELECT h.* FROM yillik_plan_haftalar h
    WHERE h.school_id IN (0, ?)
      AND ? BETWEEN h.baslangic_tarihi AND h.bitis_tarihi
    ORDER BY h.school_id DESC, h.donem ASC
    LIMIT 1
");
$haftaRow->execute([$schoolId, $tarih]);
$hafta = $haftaRow->fetch(PDO::FETCH_ASSOC);

if (!$hafta) {
    echo json_encode([
        'success' => true,
        'type' => 'donem_disinda',
        'tarih' => $tarih,
        'sinif' => $sinif,
        'oneri' => null,
        'mesaj' => 'Bu tarih icin donem plani bulunamadi'
    ]);
    exit;
}

$donem   = (int)$hafta['donem'];
$haftaNo = (int)$hafta['hafta_no'];

// Gun no: tarihin hafta icindeki gunu (Pazartesi = 1)
$bas = new DateTime($hafta['baslangic_tarihi']);
$tarihDT = new DateTime($tarih);
$gunDiff = $bas->diff($tarihDT)->days;
$gunNo = ($gunDiff % 7) + 1; // Pazartesi = 1

// 3. Ogretmenin branch_id'sini al
$stmt = $pdo->prepare("SELECT branch_id FROM staff WHERE id = ?");
$stmt->execute([$ogretmenId]);
$branchId = (int)$stmt->fetchColumn();

if (!$branchId) {
    echo json_encode([
        'success' => true,
        'type' => 'brans_yok',
        'tarih' => $tarih,
        'sinif' => $sinif,
        'oneri' => null,
        'mesaj' => 'Brans bilgisi tanimlanmamis'
    ]);
    exit;
}

// 4. Plan oneresinde oncelik: okul plani > merkezi sablon (school_id=0)
$stmt = $pdo->prepare("
    SELECT * FROM yillik_plan_mufredat
    WHERE branch_id = ? AND sinif = ? AND donem = ? AND hafta_no = ? AND gun_no = ?
      AND school_id IN (?, 0)
    ORDER BY school_id DESC
    LIMIT 1
");
$stmt->execute([$branchId, $sinif, $donem, $haftaNo, $gunNo, $schoolId]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    echo json_encode([
        'success' => true,
        'type' => 'plan_yok',
        'donem' => $donem,
        'hafta_no' => $haftaNo,
        'hafta_tarih' => [
            'baslangic' => $hafta['baslangic_tarihi'],
            'bitis' => $hafta['bitis_tarihi']
        ],
        'tarih' => $tarih,
        'sinif' => $sinif,
        'oneri' => null,
        'mesaj' => 'Bu hafta icin plan henuz girilmemis'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'type' => 'oneri',
    'donem' => $donem,
    'hafta_no' => $haftaNo,
    'hafta_tarih' => [
        'baslangic' => $hafta['baslangic_tarihi'],
        'bitis' => $hafta['bitis_tarihi']
    ],
    'tarih' => $tarih,
    'sinif' => $sinif,
    'oneri' => [
        'konu_basligi' => $plan['konu_basligi'],
        'konu_detay' => $plan['konu_detay'],
        'kazanimlar' => $plan['kazanimlar'],
        'oneri_ev_odevi' => $plan['oneri_ev_odevi'],
        'kaynak' => $plan['kaynak'],
        'saat_sayisi' => (int)$plan['saat_sayisi']
    ],
    'source' => (int)$plan['school_id'] === 0 ? 'merkezi' : 'okul'
]);