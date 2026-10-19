<?php
/**
 * Aibuke - Sınav Simülasyonu API
 *
 * POST /api/exam_simulation.php?action=start    → Yeni prova başlat
 * POST /api/exam_simulation.php?action=answer   → Cevap gönder
 * POST /api/exam_simulation.php?action=finish   → Provayı bitir / süre doldu
 * GET  /api/exam_simulation.php?action=status    → Aktif prova durumu
 * GET  /api/exam_simulation.php?action=history   → Geçmiş provalar
 */

require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/Beyin.php';
require_once __DIR__ . '/../includes/PointsManager.php';

$studentId = $_SESSION['student_id'];
$student = getStudentInfo($pdo, $studentId);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

// Profil al
$stmt = $pdo->prepare("SELECT * FROM aibuke_student_profiles WHERE student_id = ?");
$stmt->execute([$studentId]);
$profile = $stmt->fetch();
$examType = $profile['exam_type'] ?? 'lgs';

switch ($action) {

    case 'start':
        // Günlük limit kontrolü (max 10 prova/gün)
        $today = date('Y-m-d');
        $stmtCount = $pdo->prepare("
            SELECT COALESCE(exam_sim_count, 0) as cnt FROM aibuke_daily_usage
            WHERE student_id = ? AND usage_date = ?
        ");
        $stmtCount->execute([$studentId, $today]);
        $dailyRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
        $todayExamCount = $dailyRow ? (int)$dailyRow['cnt'] : 0;

        if ($todayExamCount >= 10) {
            echo json_encode(['error' => 'Bugünkü sınav provası hakkın doldu (10/10). Yarın tekrar deneyebilirsin!']);
            exit;
        }

        $subject    = trim($_POST['subject'] ?? 'Matematik');

        // Ders adı eşleme — frontend ile DB arasındaki farkları düzelt
        $subjectAliases = [
            'lgs' => [
                'Tarih' => 'İnkılap Tarihi',
                'İnkılap Tarihi' => 'İnkılap Tarihi',
                'T.C. İnkılap Tarihi' => 'İnkılap Tarihi',
                'Din Kültürü' => 'Din Kültürü',
                'Fen' => 'Fen Bilimleri',
            ],
            'tyt' => [
                'Fen' => 'Fen Bilimleri',
                'Sosyal' => 'Sosyal Bilimler',
            ],
        ];
        if (isset($subjectAliases[$examType][$subject])) {
            $subject = $subjectAliases[$examType][$subject];
        }

        $numQuestions = min(max((int) ($_POST['num_questions'] ?? 5), 1), 20);
        $totalSeconds = max((int) ($_POST['time_seconds'] ?? $numQuestions * 120), 10);
        $timeMinutes  = ceil($totalSeconds / 60);
        $difficulty  = trim($_POST['difficulty'] ?? 'easy');
        $pointsPerQ  = max(0.5, min(2.0, (float) ($_POST['points_per_q'] ?? 0.5)));
        // Çoklu konu desteği
        $topicsJson = $_POST['topics'] ?? '';
        $topics = $topicsJson ? json_decode($topicsJson, true) : [];
        if (!is_array($topics)) $topics = [];
        // Eski tek konu desteği (geriye uyumluluk)
        $singleTopic = trim($_POST['topic'] ?? '');
        if (empty($topics) && $singleTopic) $topics = [$singleTopic];

        $optionCount = $examType === 'lgs' ? 4 : 5;

        // ─── Soru Havuzundan Çek (AI yerine) ───
        // TYT/AYT aynı havuzdan (ikisi de YKS)
        if (in_array($examType, ['tyt', 'ayt', 'yks'])) {
            $where = "subject = ? AND exam_type IN ('tyt','ayt') AND is_active = 1 AND has_image = 0";
            $params = [$subject];
        } else {
            $where = "subject = ? AND exam_type = ? AND is_active = 1 AND has_image = 0";
            $params = [$subject, $examType];
        }

        if (!empty($topics)) {
            $topicPlaceholders = implode(',', array_fill(0, count($topics), '?'));
            $where .= " AND topic IN ({$topicPlaceholders})";
            $params = array_merge($params, $topics);
        }

        // Adım 1: En az kullanılan 50 sorunun ID'sini çek (composite index kullanır)
        $stmt = $pdo->prepare("SELECT id FROM aibuke_question_pool WHERE {$where} ORDER BY times_used ASC LIMIT 50");
        $stmt->execute($params);
        $candidateIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($candidateIds) < $numQuestions) {
            echo json_encode(['error' => 'Bu ders/konuda yeterli soru yok. Havuzda ' . count($candidateIds) . ' soru bulundu, ' . $numQuestions . ' soru istendi.']);
            exit;
        }

        // Adım 2: PHP'de rastgele seç (ORDER BY RAND() yok — performans)
        $randKeys = array_rand($candidateIds, $numQuestions);
        if (!is_array($randKeys)) $randKeys = [$randKeys];
        $selectedIds = array_map(fn($k) => $candidateIds[$k], $randKeys);

        // Adım 3: Seçilen soruları getir
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM aibuke_question_pool WHERE id IN ({$placeholders})");
        $stmt->execute($selectedIds);
        $poolRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Adım 4: Kullanım sayısını artır
        $pdo->prepare("UPDATE aibuke_question_pool SET times_used = times_used + 1 WHERE id IN ({$placeholders})")
            ->execute($selectedIds);

        // Adım 5: Soruları eski formata dönüştür
        $questions = ['questions' => []];
        $qId = 1;
        foreach ($poolRows as $row) {
            $options = ['A' => $row['option_a'], 'B' => $row['option_b'], 'C' => $row['option_c'], 'D' => $row['option_d']];
            if ($optionCount === 5) {
                $options['E'] = $row['option_e'] ?? '';
            }
            $questions['questions'][] = [
                'id' => $qId++,
                'pool_id' => $row['id'],
                'text' => $row['question_text'],
                'options' => $options,
                'correct' => $row['correct_answer'],
                'topic' => $row['topic'] ?? $subject,
                'difficulty' => $row['difficulty'],
                'explanation' => $row['explanation'] ?? ''
            ];
        }

        if (empty($questions['questions'])) {
            echo json_encode(['error' => 'Sorular yüklenemedi, tekrar dene']);
            exit;
        }

        // Session'a kaydet (süre takibi için)
        $_SESSION['exam_sim'] = [
            'subject'       => $subject,
            'questions'     => $questions['questions'],
            'num_questions'  => count($questions['questions']),
            'time_minutes'  => $timeMinutes,
            'total_seconds' => $totalSeconds,
            'points_per_q'  => $pointsPerQ,
            'difficulty'    => $difficulty,
            'started_at'    => time(),
            'answers'       => [],
            'finished'      => false
        ];

        // Öğrenciye soruları gönder (doğru cevapları gizle)
        $safeQuestions = [];
        foreach ($questions['questions'] as $q) {
            $safeQuestions[] = [
                'id'         => $q['id'],
                'text'       => $q['text'],
                'options'    => $q['options'],
                'topic'      => $q['topic'],
            ];
        }

        echo json_encode([
            'success'       => true,
            'questions'     => $safeQuestions,
            'time_seconds'  => $totalSeconds,
            'time_minutes'  => $timeMinutes,
            'points_per_q'  => $pointsPerQ,
            'total'         => count($safeQuestions),
            'exam_type'     => $examType,
            'started_at'    => $_SESSION['exam_sim']['started_at']
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'answer':
        if (empty($_SESSION['exam_sim']) || $_SESSION['exam_sim']['finished']) {
            echo json_encode(['error' => 'Aktif prova yok']);
            exit;
        }

        $questionId = (int) ($_POST['question_id'] ?? 0);
        $answer = strtoupper(trim($_POST['answer'] ?? ''));

        if (!$answer || !$questionId) {
            echo json_encode(['error' => 'Soru ID ve cevap gerekli']);
            exit;
        }

        // Süre kontrolü
        $elapsed = time() - $_SESSION['exam_sim']['started_at'];
        $timeLimit = $_SESSION['exam_sim']['total_seconds'] ?? $_SESSION['exam_sim']['time_minutes'] * 60;

        if ($elapsed > $timeLimit) {
            // Süre dolmuş, otomatik bitir
            $_SESSION['exam_sim']['finished'] = true;
            echo json_encode(['error' => 'Süre doldu!', 'time_up' => true]);
            exit;
        }

        // Cevabı kaydet
        $_SESSION['exam_sim']['answers'][$questionId] = [
            'answer'     => $answer,
            'time_spent' => $elapsed
        ];

        // Kalan soru
        $answered = count($_SESSION['exam_sim']['answers']);
        $total = $_SESSION['exam_sim']['num_questions'];
        $remaining = $total - $answered;
        $timeLeft = $timeLimit - $elapsed;

        // Stres uyarısı
        $warning = null;
        if ($timeLeft < $timeLimit * 0.5 && $remaining > $total * 0.5) {
            $warning = "Sürenin yarısı bitti ama {$remaining} soru kaldı! Hızlan 🏃";
        } elseif ($timeLeft < 120 && $remaining > 0) {
            $warning = "Son 2 dakika! {$remaining} soru kaldı ⏰";
        }

        echo json_encode([
            'success'    => true,
            'answered'   => $answered,
            'remaining'  => $remaining,
            'time_left'  => $timeLeft,
            'warning'    => $warning
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'finish':
        if (empty($_SESSION['exam_sim'])) {
            echo json_encode(['error' => 'Aktif prova yok']);
            exit;
        }

        $sim = $_SESSION['exam_sim'];
        $sim['finished'] = true;
        $_SESSION['exam_sim'] = $sim;

        $questions = $sim['questions'];
        $answers = $sim['answers'];
        $elapsed = time() - $sim['started_at'];

        // Sonuçları hesapla
        $correct = 0;
        $wrong = 0;
        $empty = 0;
        $details = [];

        foreach ($questions as $q) {
            $qId = $q['id'];
            $studentAnswer = $answers[$qId]['answer'] ?? null;

            if (!$studentAnswer) {
                $empty++;
                $details[] = [
                    'id' => $qId, 'topic' => $q['topic'],
                    'correct_answer' => $q['correct'], 'student_answer' => null,
                    'result' => 'empty', 'explanation' => $q['explanation']
                ];
            } elseif ($studentAnswer === $q['correct']) {
                $correct++;
                $details[] = [
                    'id' => $qId, 'topic' => $q['topic'],
                    'correct_answer' => $q['correct'], 'student_answer' => $studentAnswer,
                    'result' => 'correct', 'explanation' => $q['explanation']
                ];
            } else {
                $wrong++;
                $details[] = [
                    'id' => $qId, 'topic' => $q['topic'],
                    'correct_answer' => $q['correct'], 'student_answer' => $studentAnswer,
                    'result' => 'wrong', 'explanation' => $q['explanation']
                ];
            }
        }

        // Puan hesapla (zorluk bazlı)
        $pointsPerQ = $sim['points_per_q'] ?? 1;
        $penalty = $examType === 'lgs' ? 3 : 4;
        $net = $correct - ($wrong / $penalty);
        $score = round($correct * $pointsPerQ, 1);
        $maxScore = round(count($questions) * $pointsPerQ, 1);
        $tempo = count($questions) > 0 ? round($elapsed / 60 / count($questions), 1) : 0;

        // Hata türü analizi (Beyin ile — tek toplu çağrı)
        $wrongDetails = array_filter($details, fn($d) => $d['result'] === 'wrong');
        $errorAnalysis = [];

        if (!empty($wrongDetails)) {
            $beyin = new Beyin();

            // Toplu analiz için veri hazırla (max 5 soru)
            $batchItems = [];
            foreach (array_slice($wrongDetails, 0, 5) as $wd) {
                foreach ($questions as $quest) {
                    if ($quest['id'] === $wd['id']) {
                        $batchItems[] = [
                            'id' => $wd['id'],
                            'topic' => $wd['topic'],
                            'text' => $quest['text'],
                            'student_answer' => $wd['student_answer'],
                            'correct' => $quest['correct']
                        ];
                        break;
                    }
                }
            }

            // Tek API çağrısında tüm yanlışları analiz et
            $batchResults = $beyin->analyzeErrorsBatch($batchItems);

            foreach ($batchResults as $br) {
                $qId = $br['id'] ?? null;
                if (!$qId) continue;
                // Konu bilgisini bul
                $topic = '';
                foreach ($batchItems as $bi) {
                    if ($bi['id'] == $qId) { $topic = $bi['topic']; break; }
                }
                $errorAnalysis[] = [
                    'question_id' => $qId,
                    'topic' => $topic,
                    'error_type' => strtoupper($br['error_type'] ?? 'BILINMIYOR'),
                    'reason' => $br['reason'] ?? ''
                ];
            }
        }

        // Hata türlerini aibuke_student_progress'e kaydet
        if (!empty($errorAnalysis)) {
            $colMap = [
                'DIKKATSIZLIK' => 'error_type_careless',
                'KONU_EKSIKLIGI' => 'error_type_knowledge',
                'ZAMAN' => 'error_type_time',
                'KAVRAM_KARISIKLIGI' => 'error_type_confusion'
            ];
            foreach ($errorAnalysis as $ea) {
                $col = $colMap[$ea['error_type']] ?? null;
                if ($col) {
                    $subject = $ea['topic'] ?: $sim['subject'];
                    $stmt = $pdo->prepare("
                        INSERT INTO aibuke_student_progress (student_id, subject, {$col})
                        VALUES (?, ?, 1)
                        ON DUPLICATE KEY UPDATE {$col} = {$col} + 1
                    ");
                    $stmt->execute([$studentId, $subject]);
                }
            }
        }

        // Günlük prova sayacını artır
        $today = date('Y-m-d');
        $pdo->prepare("
            INSERT INTO aibuke_daily_usage (student_id, usage_date, exam_sim_count)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE exam_sim_count = exam_sim_count + 1
        ")->execute([$studentId, $today]);

        // Puan ver
        $pm = new PointsManager($pdo);
        $pm->addPoints($studentId, 'question_solved', 'Sınav provası tamamlandı');
        for ($i = 0; $i < $correct; $i++) {
            $pm->addPoints($studentId, 'correct_answer', 'Prova doğru cevap');
        }

        // Sonuç
        $diffLabel = ['easy' => 'Kolay', 'medium' => 'Orta', 'hard' => 'Zor'][$sim['difficulty'] ?? 'easy'] ?? 'Kolay';
        $report = [
            'subject'      => $sim['subject'],
            'exam_type'    => strtoupper($examType),
            'difficulty'   => $diffLabel,
            'points_per_q' => $pointsPerQ,
            'score'        => $score,
            'max_score'    => $maxScore,
            'time_minutes' => round($elapsed / 60, 1),
            'time_limit'   => $sim['time_minutes'],
            'correct'      => $correct,
            'wrong'        => $wrong,
            'empty'        => $empty,
            'total'        => count($questions),
            'net'          => round($net, 2),
            'tempo'        => $tempo . ' dk/soru',
            'details'      => $details,
            'error_analysis' => $errorAnalysis
        ];

        // Session temizle
        unset($_SESSION['exam_sim']);

        echo json_encode([
            'success' => true,
            'report'  => $report
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'status':
        if (empty($_SESSION['exam_sim']) || $_SESSION['exam_sim']['finished']) {
            echo json_encode(['active' => false]);
            exit;
        }

        $sim = $_SESSION['exam_sim'];
        $elapsed = time() - $sim['started_at'];
        $timeLeft = ($sim['time_minutes'] * 60) - $elapsed;

        echo json_encode([
            'active'     => true,
            'subject'    => $sim['subject'],
            'answered'   => count($sim['answers']),
            'total'      => $sim['num_questions'],
            'time_left'  => max(0, $timeLeft),
            'time_up'    => $timeLeft <= 0
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'topics':
        $subject = trim($_GET['subject'] ?? '');
        if (!$subject) {
            echo json_encode(['topics' => []]);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT topic, COUNT(*) as cnt
            FROM aibuke_question_pool
            WHERE subject = ? AND topic IS NOT NULL AND topic != '' AND is_active = 1
            GROUP BY topic ORDER BY cnt DESC LIMIT 50
        ");
        $stmt->execute([$subject]);
        echo json_encode(['topics' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        break;

    case 'history':
        // Geçmiş provalar (exam_analyses tablosundan simülasyon kayıtları)
        $stmt = $pdo->prepare("
            SELECT exam_type, exam_date, total_correct, total_wrong, total_empty, net_score, created_at
            FROM aibuke_exam_analyses
            WHERE student_id = ? AND exam_type LIKE '%prova%'
            ORDER BY created_at DESC LIMIT 20
        ");
        $stmt->execute([$studentId]);
        echo json_encode(['history' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Geçersiz action. Kullanım: start, answer, finish, status, history']);
}
