<?php
/**
 * Aibuke - PDF Arka Plan İşleyici
 * Kullanım: php process_pdf.php /path/to/file.pdf
 */
set_time_limit(600);

define('QUESTION_POOL_FUNCTIONS_ONLY', true);
require_once __DIR__ . '/../admin/api/question_pool.php';

$statusFile = __DIR__ . '/../uploads/pdfs/status.json';

function updateStatus($data) {
    global $statusFile;
    $data['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents($statusFile, json_encode($data, JSON_UNESCAPED_UNICODE));
}

$pdfPath = $argv[1] ?? null;
if (!$pdfPath || !file_exists($pdfPath)) {
    updateStatus(['status' => 'error', 'message' => 'PDF bulunamadı']);
    exit(1);
}

updateStatus(['status' => 'processing', 'step' => 'PDF okunuyor...']);

// 1. PDF → Text (realpath ile dosya yolu doğrulaması)
$realPdfPath = realpath($pdfPath);
if ($realPdfPath === false || !file_exists($realPdfPath)) {
    updateStatus(['status' => 'error', 'message' => 'PDF bulunamadı veya geçersiz yol']);
    exit(1);
}
$text = shell_exec("pdftotext -layout " . escapeshellarg($realPdfPath) . " - 2>/dev/null");
if (empty(trim($text))) {
    updateStatus(['status' => 'error', 'message' => 'PDF okunamadı']);
    exit(1);
}

// 2. Sinav tipi ve kaynak
$examType = detectExamType($text);
$source = detectSource($text);

updateStatus(['status' => 'processing', 'step' => 'Cevap anahtarı ayrıştırılıyor...', 'source' => $source, 'exam_type' => $examType]);

// 3. Cevap anahtari
$answerKeys = parseAnswerKey($text);
if (empty($answerKeys)) {
    updateStatus(['status' => 'error', 'message' => 'Cevap anahtarı bulunamadı']);
    exit(1);
}

// 4. Konu dagilimi
$topicMap = parseTopicDistribution($text);

// 5. Bolumlere ayir
$sections = splitIntoSections($text);
if (empty($sections)) {
    updateStatus(['status' => 'error', 'message' => 'Ders bölümleri tespit edilemedi']);
    exit(1);
}

// Ders listesini hazırla
$subjects = [];
foreach ($sections as $key => $txt) {
    $subjects[$key] = ['name' => mapSubjectName($key), 'total' => count($answerKeys[$key] ?? []), 'done' => 0, 'status' => 'bekliyor'];
}

updateStatus([
    'status' => 'processing',
    'step' => 'Sorular ayrıştırılıyor...',
    'source' => $source,
    'exam_type' => $examType,
    'subjects' => $subjects
]);

// 6. Her bolumu MiniMax ile isle
$beyin = new Beyin();
$totalInserted = 0;

foreach ($sections as $subjectKey => $sectionText) {
    $subjectName = mapSubjectName($subjectKey);
    $answers = $answerKeys[$subjectKey] ?? [];
    if (empty($answers)) continue;

    $topics = $topicMap[$subjectKey] ?? [];
    $hasTopics = !empty($topics);
    $questionCount = count($answers);

    $subjects[$subjectKey]['status'] = 'isleniyor';
    updateStatus([
        'status' => 'processing',
        'step' => "$subjectName işleniyor ($questionCount soru)...",
        'source' => $source,
        'exam_type' => $examType,
        'subjects' => $subjects,
        'total_inserted' => $totalInserted
    ]);

    $extracted = extractQuestionsWithAI($beyin, $sectionText, $subjectName, $questionCount, !$hasTopics, $examType);

    if (empty($extracted)) {
        $subjects[$subjectKey]['status'] = 'hata';
        continue;
    }

    $inserted = 0;
    $stmt = $pdo->prepare("INSERT INTO aibuke_question_pool
        (subject, topic, difficulty, exam_type, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer, explanation, source, has_image)
        VALUES (?, ?, 'medium', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($extracted as $q) {
        $qNum = (int)($q['number'] ?? 0);
        $correctAnswer = $answers[$qNum] ?? ($q['correct'] ?? '');
        if (empty($correctAnswer)) continue;

        $topic = $hasTopics ? ($topics[$qNum] ?? null) : ($q['topic'] ?? null);
        $hasImage = !empty($q['has_image']) ? 1 : 0;

        try {
            $stmt->execute([
                $subjectName, $topic, $examType,
                $q['text'] ?? '', $q['A'] ?? '', $q['B'] ?? '', $q['C'] ?? '', $q['D'] ?? '', $q['E'] ?? null,
                strtoupper($correctAnswer), $q['explanation'] ?? null, $source, $hasImage
            ]);
            $inserted++;
        } catch (Exception $e) {
            error_log("process_pdf.php: DB insert error for question $qNum: " . $e->getMessage());
        }
    }

    $totalInserted += $inserted;
    $subjects[$subjectKey]['done'] = $inserted;
    $subjects[$subjectKey]['status'] = 'tamam';

    updateStatus([
        'status' => 'processing',
        'step' => "$subjectName tamamlandı ($inserted/$questionCount)",
        'source' => $source,
        'exam_type' => $examType,
        'subjects' => $subjects,
        'total_inserted' => $totalInserted
    ]);
}

updateStatus([
    'status' => 'done',
    'step' => "Tamamlandı! $totalInserted soru eklendi.",
    'source' => $source,
    'exam_type' => $examType,
    'subjects' => $subjects,
    'total_inserted' => $totalInserted
]);
