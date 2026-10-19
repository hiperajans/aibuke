<?php
/**
 * Aibuke - Dijital Bahçe API
 * GET: action = status|visit
 * POST: action = water (soru çözdükçe otomatik çağrılır)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
session_start();
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$studentId = $_SESSION['student_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'status';

// Whitelist validation
$allowedActions = ['status', 'water', 'visit'];
if (!in_array($action, $allowedActions, true)) {
    echo json_encode(['error' => 'Geçersiz aksiyon']);
    exit;
}

// Bahçe yoksa oluştur
function getOrCreateGarden($pdo, $studentId) {
    $stmt = $pdo->prepare("SELECT * FROM aibuke_gardens WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $garden = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$garden) {
        $pdo->prepare("INSERT INTO aibuke_gardens (student_id, decorations) VALUES (?, '[]')")->execute([$studentId]);
        $stmt->execute([$studentId]);
        $garden = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return $garden;
}

// Bahçe seviye hesabı
function calculateGardenLevel($garden) {
    $leaves = $garden['total_leaves'];
    $flowers = $garden['total_flowers'];
    $streak = $garden['streak_days'];

    // Level thresholds
    if ($leaves >= 200 && $streak >= 30) return 6; // Tam bahçe + kelebekler
    if ($leaves >= 100 && $streak >= 14) return 5; // Kocaman ağaç
    if ($leaves >= 50 && $streak >= 7)   return 4; // Çiçek açmış
    if ($leaves >= 20)                    return 3; // Yeşil yapraklar
    if ($leaves >= 5)                     return 2; // Filizlenmiş
    return 1; // Kuru toprak
}

function getGardenVisual($level) {
    $levels = [
        1 => ['emoji' => '🏜️', 'name' => 'Kuru Toprak', 'desc' => 'Tohumlar ekildi, sulaman lazım!'],
        2 => ['emoji' => '🌱', 'name' => 'Filizlenmiş', 'desc' => 'İlk filizler çıkıyor, harika!'],
        3 => ['emoji' => '🌿', 'name' => 'Yeşil Yapraklar', 'desc' => 'Bahçen yeşeriyor!'],
        4 => ['emoji' => '🌸', 'name' => 'Çiçek Açmış', 'desc' => 'Bahçende çiçekler açıyor!'],
        5 => ['emoji' => '🌳', 'name' => 'Kocaman Ağaç', 'desc' => 'Muhteşem bir ağacın var!'],
        6 => ['emoji' => '🏡', 'name' => 'Hayal Bahçesi', 'desc' => 'Kelebeklerin uçuştuğu bir cennet!'],
    ];
    return $levels[$level] ?? $levels[1];
}

switch ($action) {

    case 'status':
        $garden = getOrCreateGarden($pdo, $studentId);
        $level = calculateGardenLevel($garden);

        // Streak güncelle
        $today = date('Y-m-d');
        if ($garden['last_watered'] !== $today) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            if ($garden['last_watered'] === $yesterday) {
                $newStreak = $garden['streak_days'] + 1;
            } else {
                $newStreak = $garden['last_watered'] ? max(0, $garden['streak_days'] - 1) : 0;
            }
            $pdo->prepare("UPDATE aibuke_gardens SET streak_days = ?, garden_level = ? WHERE student_id = ?")
                ->execute([$newStreak, $level, $studentId]);
            $garden['streak_days'] = $newStreak;
        }

        if ($garden['garden_level'] != $level) {
            $pdo->prepare("UPDATE aibuke_gardens SET garden_level = ? WHERE student_id = ?")->execute([$level, $studentId]);
        }

        $visual = getGardenVisual($level);
        $decorations = json_decode($garden['decorations'] ?: '[]', true);

        // Rozetlerden süs oluştur
        require_once __DIR__ . '/../includes/PointsManager.php';
        $pm = new PointsManager($pdo);
        $badges = $pm->getStudentBadges($studentId);
        $gardenDecos = [];
        foreach ($badges as $b) {
            $gardenDecos[] = $b['badge_icon'];
        }

        echo json_encode([
            'level' => $level,
            'visual' => $visual,
            'leaves' => $garden['total_leaves'],
            'flowers' => $garden['total_flowers'],
            'streak' => $garden['streak_days'],
            'decorations' => $gardenDecos,
            'last_watered' => $garden['last_watered']
        ], JSON_UNESCAPED_UNICODE);
        break;

    // Soru çözüldükçe çağrılır
    case 'water':
        $garden = getOrCreateGarden($pdo, $studentId);
        $today = date('Y-m-d');

        $leafAdd = 1;
        $flowerAdd = 0;

        // Her 10 yaprakta 1 çiçek
        if (($garden['total_leaves'] + 1) % 10 === 0) {
            $flowerAdd = 1;
        }

        // Streak güncellemesi
        $newStreak = $garden['streak_days'];
        if ($garden['last_watered'] !== $today) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $newStreak = ($garden['last_watered'] === $yesterday) ? $garden['streak_days'] + 1 : 1;
        }

        $newLeaves = $garden['total_leaves'] + $leafAdd;
        $newFlowers = $garden['total_flowers'] + $flowerAdd;
        $newLevel = calculateGardenLevel(array_merge($garden, [
            'total_leaves' => $newLeaves,
            'total_flowers' => $newFlowers,
            'streak_days' => $newStreak
        ]));

        $leveledUp = ($newLevel > $garden['garden_level']);

        $pdo->prepare("UPDATE aibuke_gardens SET total_leaves = ?, total_flowers = ?, streak_days = ?, garden_level = ?, last_watered = ? WHERE student_id = ?")
            ->execute([$newLeaves, $newFlowers, $newStreak, $newLevel, $today, $studentId]);

        $response = [
            'success' => true,
            'new_leaf' => true,
            'new_flower' => $flowerAdd > 0,
            'leaves' => $newLeaves,
            'flowers' => $newFlowers,
            'level' => $newLevel,
            'leveled_up' => $leveledUp
        ];

        if ($leveledUp) {
            $visual = getGardenVisual($newLevel);
            $response['level_name'] = $visual['name'];
            $response['level_emoji'] = $visual['emoji'];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        break;

    // Arkadaş bahçesini ziyaret et
    case 'visit':
        $friendId = (int) ($_GET['friend_id'] ?? 0);
        if (!$friendId) { echo json_encode(['error' => 'Öğrenci seçilmedi']); exit; }

        // Aynı okul kontrolü
        $student = getStudentInfo($pdo, $studentId);
        $friend = getStudentInfo($pdo, $friendId);
        if (!$friend || $friend['school_id'] !== $student['school_id']) {
            echo json_encode(['error' => 'Bahçe bulunamadı']);
            exit;
        }

        $garden = getOrCreateGarden($pdo, $friendId);
        $level = calculateGardenLevel($garden);
        $visual = getGardenVisual($level);

        echo json_encode([
            'friend_name' => $friend['first_name'],
            'level' => $level,
            'visual' => $visual,
            'leaves' => $garden['total_leaves'],
            'flowers' => $garden['total_flowers'],
            'streak' => $garden['streak_days']
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Geçersiz aksiyon']);
}
