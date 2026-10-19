<?php
/**
 * Aibuke - Günlük Turnuva Oluşturucu
 * Cron: 0 19 * * * php /var/www/aibuke/cron/daily_tournament.php
 * Her akşam 19:00'da yarım saat sonra başlayacak turnuva oluşturur.
 * Sorular soru havuzundan (aibuke_question_pool) çekilir.
 */
require_once __DIR__ . '/../config.php';

// Havuzda yeterli soru olan rastgele bir ders+konu seç
$stmt = $pdo->query("
    SELECT subject, topic, COUNT(*) as cnt
    FROM aibuke_question_pool
    WHERE is_active = 1 AND has_image = 0
    GROUP BY subject, topic
    HAVING cnt >= 5
    ORDER BY RAND()
    LIMIT 1
");
$pick = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pick) {
    echo "Havuzda yeterli soru yok, çıkılıyor.\n";
    exit;
}

$title = ($pick['topic'] ?: $pick['subject']) . " Turnuvası";

// En az kullanılan 20 sorudan 5 tanesini seç
$topicFilter = $pick['topic'] ? "AND topic = ?" : "";
$params = [$pick['subject']];
if ($pick['topic']) $params[] = $pick['topic'];

$stmt = $pdo->prepare("
    SELECT id FROM aibuke_question_pool
    WHERE subject = ? AND is_active = 1 AND has_image = 0 {$topicFilter}
    ORDER BY times_used ASC
    LIMIT 20
");
$stmt->execute($params);
$candidateIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (count($candidateIds) < 5) {
    echo "Yeterli soru bulunamadı.\n";
    exit;
}

$randKeys = array_rand($candidateIds, 5);
if (!is_array($randKeys)) $randKeys = [$randKeys];
$selectedIds = array_map(fn($k) => $candidateIds[$k], $randKeys);

$placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
$stmt = $pdo->prepare("SELECT * FROM aibuke_question_pool WHERE id IN ({$placeholders})");
$stmt->execute($selectedIds);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kullanım sayısını artır
$pdo->prepare("UPDATE aibuke_question_pool SET times_used = times_used + 1 WHERE id IN ({$placeholders})")
    ->execute($selectedIds);

// Tüm aktif okulları al
$schools = $pdo->query("SELECT DISTINCT school_id FROM aibuke_student_profiles sp JOIN students s ON sp.student_id = s.id WHERE s.school_id > 0")->fetchAll(PDO::FETCH_COLUMN);

if (empty($schools)) {
    echo "Aktif okul yok, çıkılıyor.\n";
    exit;
}

$startsAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
$created = 0;

foreach ($schools as $schoolId) {
    // Bugün bu okul için turnuva var mı
    $stmt = $pdo->prepare("SELECT id FROM aibuke_tournaments WHERE school_id = ? AND DATE(starts_at) = CURDATE()");
    $stmt->execute([$schoolId]);
    if ($stmt->fetch()) continue;

    $pdo->prepare("INSERT INTO aibuke_tournaments (school_id, title, subject, topic, tournament_type, starts_at) VALUES (?, ?, ?, ?, 'konu', ?)")
        ->execute([$schoolId, $title, $pick['subject'], $pick['topic'], $startsAt]);
    $tournamentId = $pdo->lastInsertId();

    $order = 1;
    foreach ($questions as $q) {
        $pdo->prepare("INSERT INTO aibuke_tournament_questions (tournament_id, question_order, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $tournamentId, $order++,
                $q['question_text'],
                $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'],
                strtoupper($q['correct_answer']),
                $q['explanation'] ?? ''
            ]);
    }
    $created++;
}

echo "Turnuva oluşturuldu: {$title} — {$created} okul\n";
