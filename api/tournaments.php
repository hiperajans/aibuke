<?php
/**
 * Aibuke - Soru Turnuvaları API
 * POST/GET: action = list|join|question|answer|results|create_daily
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
session_start();
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$studentId = $_SESSION['student_id'];
$student = getStudentInfo($pdo, $studentId);
$schoolId = $student['school_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ─── Aktif / Yaklaşan Turnuvalar ───
    case 'list':
        // Aktif ve bekleyen turnuvalar
        $stmt = $pdo->prepare("
            SELECT t.*,
                   (SELECT COUNT(*) FROM aibuke_tournament_participants WHERE tournament_id = t.id) as participant_count,
                   (SELECT id FROM aibuke_tournament_participants WHERE tournament_id = t.id AND student_id = ?) as my_participation
            FROM aibuke_tournaments t
            WHERE t.school_id = ? AND t.status IN ('waiting','active')
            AND t.starts_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
            ORDER BY t.starts_at ASC
            LIMIT 10
        ");
        $stmt->execute([$studentId, $schoolId]);
        $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Son tamamlanan turnuvalar
        $stmt = $pdo->prepare("
            SELECT t.*,
                   (SELECT COUNT(*) FROM aibuke_tournament_participants WHERE tournament_id = t.id) as participant_count,
                   tp.score as my_score, tp.correct_count as my_correct
            FROM aibuke_tournaments t
            LEFT JOIN aibuke_tournament_participants tp ON t.id = tp.tournament_id AND tp.student_id = ?
            WHERE t.school_id = ? AND t.status = 'finished'
            ORDER BY t.finished_at DESC
            LIMIT 5
        ");
        $stmt->execute([$studentId, $schoolId]);
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['upcoming' => $upcoming, 'recent' => $recent], JSON_UNESCAPED_UNICODE);
        break;

    // ─── Turnuvaya Katıl ───
    case 'join':
        $tournamentId = (int) ($_POST['tournament_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM aibuke_tournaments WHERE id = ? AND school_id = ? AND status IN ('waiting','active')");
        $stmt->execute([$tournamentId, $schoolId]);
        $tournament = $stmt->fetch();
        if (!$tournament) { echo json_encode(['error' => 'Turnuva bulunamadı']); exit; }

        // Zaten katılmış mı
        $stmt = $pdo->prepare("SELECT id FROM aibuke_tournament_participants WHERE tournament_id = ? AND student_id = ?");
        $stmt->execute([$tournamentId, $studentId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => true, 'already_joined' => true]);
            exit;
        }

        $pdo->prepare("INSERT INTO aibuke_tournament_participants (tournament_id, student_id) VALUES (?, ?)")
            ->execute([$tournamentId, $studentId]);

        // Turnuva henüz başlamamışsa ve yeterli kişi varsa başlat
        if ($tournament['status'] === 'waiting') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_tournament_participants WHERE tournament_id = ?");
            $stmt->execute([$tournamentId]);
            $count = (int) $stmt->fetchColumn();
            if ($count >= 2 || strtotime($tournament['starts_at']) <= time()) {
                $pdo->prepare("UPDATE aibuke_tournaments SET status = 'active' WHERE id = ? AND status = 'waiting'")
                    ->execute([$tournamentId]);
            }
        }

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        break;

    // ─── Sıradaki Soru ───
    case 'question':
        $tournamentId = (int) ($_GET['tournament_id'] ?? 0);

        // Katılım kontrolü
        $stmt = $pdo->prepare("SELECT * FROM aibuke_tournament_participants WHERE tournament_id = ? AND student_id = ?");
        $stmt->execute([$tournamentId, $studentId]);
        $participation = $stmt->fetch();
        if (!$participation) { echo json_encode(['error' => 'Katılmamışsın']); exit; }
        if ($participation['finished']) { echo json_encode(['finished' => true, 'score' => $participation['score']]); exit; }

        // Turnuva bilgisi
        $stmt = $pdo->prepare("SELECT * FROM aibuke_tournaments WHERE id = ?");
        $stmt->execute([$tournamentId]);
        $tournament = $stmt->fetch();

        // Cevaplanmamış ilk soru
        $stmt = $pdo->prepare("
            SELECT tq.* FROM aibuke_tournament_questions tq
            WHERE tq.tournament_id = ?
            AND tq.id NOT IN (SELECT question_id FROM aibuke_tournament_answers WHERE tournament_id = ? AND student_id = ?)
            ORDER BY tq.question_order ASC
            LIMIT 1
        ");
        $stmt->execute([$tournamentId, $tournamentId, $studentId]);
        $question = $stmt->fetch();

        if (!$question) {
            // Tüm sorular cevaplandı
            $pdo->prepare("UPDATE aibuke_tournament_participants SET finished = 1 WHERE tournament_id = ? AND student_id = ?")
                ->execute([$tournamentId, $studentId]);
            echo json_encode(['finished' => true, 'score' => $participation['score']]);
            exit;
        }

        // Cevapları correct_answer olmadan gönder
        $answered = $pdo->prepare("SELECT COUNT(*) FROM aibuke_tournament_answers WHERE tournament_id = ? AND student_id = ?");
        $answered->execute([$tournamentId, $studentId]);
        $answeredCount = (int) $answered->fetchColumn();

        echo json_encode([
            'question' => [
                'id' => $question['id'],
                'order' => $question['question_order'],
                'text' => $question['question_text'],
                'options' => [
                    'A' => $question['option_a'],
                    'B' => $question['option_b'],
                    'C' => $question['option_c'],
                    'D' => $question['option_d']
                ]
            ],
            'current' => $answeredCount + 1,
            'total' => $tournament['question_count'],
            'time_limit' => $tournament['time_per_question']
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ─── Cevap Gönder ───
    case 'answer':
        $tournamentId = (int) ($_POST['tournament_id'] ?? 0);
        $questionId = (int) ($_POST['question_id'] ?? 0);
        $answer = strtoupper(trim($_POST['answer'] ?? ''));
        $timeSpent = (int) ($_POST['time_spent'] ?? 0);

        if (!in_array($answer, ['A','B','C','D','X'])) {
            echo json_encode(['error' => 'Geçersiz cevap']);
            exit;
        }
        // X = süre doldu, cevap verilmedi
        if ($answer === 'X') $answer = '';

        // Katılım kontrolü
        $stmt = $pdo->prepare("SELECT * FROM aibuke_tournament_participants WHERE tournament_id = ? AND student_id = ?");
        $stmt->execute([$tournamentId, $studentId]);
        $participation = $stmt->fetch();
        if (!$participation) { echo json_encode(['error' => 'Katılmamışsın']); exit; }

        // Soruyu al
        $stmt = $pdo->prepare("SELECT * FROM aibuke_tournament_questions WHERE id = ? AND tournament_id = ?");
        $stmt->execute([$questionId, $tournamentId]);
        $question = $stmt->fetch();
        if (!$question) { echo json_encode(['error' => 'Soru bulunamadı']); exit; }

        // Daha önce cevaplanmış mı
        $stmt = $pdo->prepare("SELECT id FROM aibuke_tournament_answers WHERE tournament_id = ? AND student_id = ? AND question_id = ?");
        $stmt->execute([$tournamentId, $studentId, $questionId]);
        if ($stmt->fetch()) { echo json_encode(['error' => 'Zaten cevapladın']); exit; }

        $isCorrect = ($answer !== '' && $answer === $question['correct_answer']) ? 1 : 0;

        // Puan hesapla: doğru + hızlı = daha fazla puan
        $points = 0;
        if ($isCorrect) {
            $tournament = $pdo->prepare("SELECT time_per_question FROM aibuke_tournaments WHERE id = ?");
            $tournament->execute([$tournamentId]);
            $maxTime = (int) $tournament->fetchColumn();
            // Base 10 + speed bonus (max 10)
            $speedBonus = max(0, round(10 * (1 - $timeSpent / $maxTime)));
            $points = 10 + $speedBonus;
        }

        // Cevabı kaydet
        $pdo->prepare("INSERT INTO aibuke_tournament_answers (tournament_id, student_id, question_id, selected_answer, is_correct, time_spent) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$tournamentId, $studentId, $questionId, $answer, $isCorrect, $timeSpent]);

        // Katılımcı skorunu güncelle
        $pdo->prepare("UPDATE aibuke_tournament_participants SET score = score + ?, correct_count = correct_count + ?, wrong_count = wrong_count + ? WHERE tournament_id = ? AND student_id = ?")
            ->execute([$points, $isCorrect, $isCorrect ? 0 : 1, $tournamentId, $studentId]);

        // Kalan soru var mı
        $totalQ = $pdo->prepare("SELECT COUNT(*) FROM aibuke_tournament_questions WHERE tournament_id = ?");
        $totalQ->execute([$tournamentId]);
        $totalCount = (int) $totalQ->fetchColumn();

        $answeredQ = $pdo->prepare("SELECT COUNT(*) FROM aibuke_tournament_answers WHERE tournament_id = ? AND student_id = ?");
        $answeredQ->execute([$tournamentId, $studentId]);
        $answeredCount = (int) $answeredQ->fetchColumn();

        $isLast = ($answeredCount >= $totalCount);
        if ($isLast) {
            // Ortalama süre güncelle
            $avgStmt = $pdo->prepare("SELECT AVG(time_spent) FROM aibuke_tournament_answers WHERE tournament_id = ? AND student_id = ?");
            $avgStmt->execute([$tournamentId, $studentId]);
            $avgTime = round((float) $avgStmt->fetchColumn(), 1);
            $pdo->prepare("UPDATE aibuke_tournament_participants SET finished = 1, avg_time_seconds = ? WHERE tournament_id = ? AND student_id = ?")
                ->execute([$avgTime, $tournamentId, $studentId]);

            // Tüm katılımcılar bittiyse turnuvayı kapat
            $unfinished = $pdo->prepare("SELECT COUNT(*) FROM aibuke_tournament_participants WHERE tournament_id = ? AND finished = 0");
            $unfinished->execute([$tournamentId]);
            if ((int) $unfinished->fetchColumn() === 0) {
                $pdo->prepare("UPDATE aibuke_tournaments SET status = 'finished', finished_at = NOW() WHERE id = ?")
                    ->execute([$tournamentId]);

                // Puan ver (1. = +100, 2. = +75, 3. = +60)
                awardTournamentPoints($pdo, $tournamentId);
            }
        }

        echo json_encode([
            'correct' => (bool) $isCorrect,
            'correct_answer' => $question['correct_answer'],
            'explanation' => $question['explanation'],
            'points' => $points,
            'is_last' => $isLast
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ─── Sonuç Tablosu ───
    case 'results':
        $tournamentId = (int) ($_GET['tournament_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM aibuke_tournaments WHERE id = ? AND school_id = ?");
        $stmt->execute([$tournamentId, $schoolId]);
        $tournament = $stmt->fetch();
        if (!$tournament) { echo json_encode(['error' => 'Turnuva bulunamadı']); exit; }

        // Sıralama
        $stmt = $pdo->prepare("
            SELECT tp.*, s.first_name, s.nickname
            FROM aibuke_tournament_participants tp
            JOIN students s ON tp.student_id = s.id
            WHERE tp.tournament_id = ?
            ORDER BY tp.score DESC, tp.avg_time_seconds ASC
        ");
        $stmt->execute([$tournamentId]);
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Benim detaylı cevaplarım
        $stmt = $pdo->prepare("
            SELECT ta.*, tq.question_text, tq.correct_answer, tq.explanation, tq.question_order
            FROM aibuke_tournament_answers ta
            JOIN aibuke_tournament_questions tq ON ta.question_id = tq.id
            WHERE ta.tournament_id = ? AND ta.student_id = ?
            ORDER BY tq.question_order
        ");
        $stmt->execute([$tournamentId, $studentId]);
        $myAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'tournament' => $tournament,
            'leaderboard' => $leaderboard,
            'my_answers' => $myAnswers
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ─── Günlük Turnuva Oluştur (Cron veya admin tarafından) ───
    case 'create_daily':
        // Basit bir güvenlik: sadece CLI veya admin
        if (php_sapi_name() !== 'cli' && !isset($_SESSION['admin_id'])) {
            echo json_encode(['error' => 'Yetkisiz']);
            exit;
        }

        $subject = trim($_POST['subject'] ?? 'Matematik');
        $topic = trim($_POST['topic'] ?? '');
        $targetSchoolId = (int) ($_POST['school_id'] ?? $schoolId);

        // Bugün bu okul için zaten turnuva var mı
        $stmt = $pdo->prepare("SELECT id FROM aibuke_tournaments WHERE school_id = ? AND DATE(starts_at) = CURDATE()");
        $stmt->execute([$targetSchoolId]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => 'Bugün zaten turnuva var']);
            exit;
        }

        // AI ile soru üret
        require_once __DIR__ . '/../includes/Beyin.php';
        $beyin = new Beyin();

        $topicStr = $topic ? "{$subject} - {$topic}" : $subject;
        $title = $topicStr . " Turnuvası";

        $systemPrompt = "Sen bir sınav sorusu hazırlayan öğretmensin. Türkçe sorular üret.";
        $userPrompt = "Lise öğrencileri için {$topicStr} konusunda 5 adet çoktan seçmeli soru hazırla.
Her soru 4 seçenekli (A, B, C, D) olacak.

JSON formatında yanıt ver:
{\"questions\": [
  {\"text\": \"Soru metni?\", \"A\": \"Seçenek A\", \"B\": \"Seçenek B\", \"C\": \"Seçenek C\", \"D\": \"Seçenek D\", \"answer\": \"B\", \"explanation\": \"Kısa açıklama\"}
]}

Sadece JSON döndür, başka bir şey yazma.";

        $result = $beyin->callPublic($systemPrompt, $userPrompt, 0.4, 2048);
        if (!$result['success']) {
            echo json_encode(['error' => 'Soru üretilemedi']);
            exit;
        }

        $parsed = $beyin->parseJSON($result['text']);
        if (!$parsed || !isset($parsed['questions']) || count($parsed['questions']) < 5) {
            echo json_encode(['error' => 'Soru parse hatası', 'raw' => $result['text']]);
            exit;
        }

        // Turnuva oluştur: 30 dk sonra başlasın
        $startsAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $pdo->prepare("INSERT INTO aibuke_tournaments (school_id, title, subject, topic, tournament_type, starts_at) VALUES (?, ?, ?, ?, 'konu', ?)")
            ->execute([$targetSchoolId, $title, $subject, $topic, $startsAt]);
        $tournamentId = $pdo->lastInsertId();

        // Soruları ekle
        $order = 1;
        foreach ($parsed['questions'] as $q) {
            $pdo->prepare("INSERT INTO aibuke_tournament_questions (tournament_id, question_order, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    $tournamentId, $order++,
                    $q['text'],
                    $q['A'], $q['B'], $q['C'], $q['D'],
                    strtoupper($q['answer']),
                    $q['explanation'] ?? ''
                ]);
        }

        echo json_encode(['success' => true, 'tournament_id' => $tournamentId, 'title' => $title, 'starts_at' => $startsAt], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Geçersiz aksiyon']);
}

// ─── Turnuva puan ödülleri ───
function awardTournamentPoints($pdo, $tournamentId) {
    $rewards = [1 => 100, 2 => 75, 3 => 60, 4 => 50, 5 => 40];

    $stmt = $pdo->prepare("
        SELECT tp.student_id, tp.score, s.school_id
        FROM aibuke_tournament_participants tp
        JOIN students s ON tp.student_id = s.id
        WHERE tp.tournament_id = ?
        ORDER BY tp.score DESC, tp.avg_time_seconds ASC
    ");
    $stmt->execute([$tournamentId]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rank = 1;
    foreach ($participants as $p) {
        $bonus = $rewards[$rank] ?? 30;
        $desc = "Turnuva #{$tournamentId} — {$rank}. sıra";
        $pdo->prepare("INSERT INTO aibuke_points (student_id, school_id, action_type, points, description) VALUES (?, ?, 'correct_answer', ?, ?)")
            ->execute([$p['student_id'], $p['school_id'], $bonus, $desc]);
        $rank++;
    }
}
