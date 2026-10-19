<?php
/**
 * Aibuke Ogretmen - Chat API
 * Mesaj gonder ve AI yanit al (streaming destekli)
 */

require_once __DIR__ . '/../config.php';
session_start();

// Giris kontrolu
if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit;
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/StudentContext.php';
require_once __DIR__ . '/../includes/Guardrails.php';
require_once __DIR__ . '/../includes/SentimentAnalyzer.php';
require_once __DIR__ . '/../includes/Router.php';
require_once __DIR__ . '/../includes/PointsManager.php';

// Rate limiting kontrolu
$limit = checkDailyLimit($pdo, $_SESSION['student_id']);
if (!$limit['can_send']) {
    http_response_code(429);
    echo json_encode(['error' => 'Günlük mesaj limitin doldu. Yarın tekrar dene!']);
    exit;
}

// POST veya GET ile gelen mesaj
$message = '';
$imageData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    // Fotograf kontrolu
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageData = base64_encode(file_get_contents($_FILES['image']['tmp_name']));
    }
} else {
    $message = trim($_GET['msg'] ?? '');
}

if (empty($message) && empty($imageData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Mesaj boş']);
    exit;
}

// Mesaj uzunluk kontrolu
$maxLen = defined('MAX_MESSAGE_LENGTH') ? MAX_MESSAGE_LENGTH : 2000;
if (mb_strlen($message) > $maxLen) {
    http_response_code(400);
    echo json_encode(['error' => 'Mesaj çok uzun. Max ' . $maxLen . ' karakter.']);
    exit;
}

// ─── Çalışma programı tespiti ───
$scheduleTriggers = [
    'ders programı', 'ders programi', 'çalışma programı', 'calisma programi',
    'haftalık program', 'haftalik program', 'program hazırla', 'program hazirla',
    'program yap', 'program oluştur', 'program olustur',
    'çalışma planı hazırla', 'haftalık plan hazırla', 'haftalık plan yap',
    'bana program', 'bana bir program', 'ders planı yap', 'ders plani yap',
    'çalışma planı oluştur', 'calisma plani olustur',
    'çalışma takvimi', 'ders çalışma programı', 'ders calisma programi',
    'programımı yeniden oluştur', 'programımı yenile'
];
$isScheduleRequest = false;
$msgLower = mb_strtolower($message, 'UTF-8');
foreach ($scheduleTriggers as $trigger) {
    if (mb_strpos($msgLower, $trigger) !== false) {
        $isScheduleRequest = true;
        break;
    }
}

if ($isScheduleRequest) {
    // Mesajı kaydet, modal açtır
    saveMessage($pdo, $_SESSION['student_id'], $message, 'student', 0, 0, null);

    $aiMsg = 'Hemen senin için bir program oluşturalım! Önce birkaç bilgiye ihtiyacım var — açılan panelden okul saatlerini ve tercihlerini ayarla.';
    saveMessage($pdo, $_SESSION['student_id'], $aiMsg, 'aibuke', 0, 0, 'system');
    incrementMessageCount($pdo, $_SESSION['student_id']);

    echo json_encode([
        'response' => $aiMsg,
        'response_type' => 'schedule_setup',
        'sentiment' => 0,
        'routed_to' => 'system',
        'remaining' => $limit['remaining'] - 1
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Akıllı Kamera 2.0: "Evet, devam" → tespit edilen konudan 5 soru üret ───
$continueTriggers = ['evet devam', 'evet, devam', 'devam edelim', 'evet çözelim', 'evet cozelim', '5 soru'];
$isContinueRequest = false;
foreach ($continueTriggers as $t) {
    if (mb_strpos($msgLower, $t) !== false) { $isContinueRequest = true; break; }
}
if ($isContinueRequest && !empty($_SESSION['detected_topic'])) {
    $topic = $_SESSION['detected_topic'];
    unset($_SESSION['detected_topic']);
    saveMessage($pdo, $_SESSION['student_id'], $message, 'student', 0, 0, null);

    $aiMsg = "Harika! {$topic} konusundan 5 soru hazırlıyorum. Sınav provası modunda çözelim!";
    saveMessage($pdo, $_SESSION['student_id'], $aiMsg, 'aibuke', 0, 0, 'system');
    incrementMessageCount($pdo, $_SESSION['student_id']);

    echo json_encode([
        'response' => $aiMsg,
        'response_type' => 'exam_setup',
        'auto_exam' => ['subject' => $topic, 'num_questions' => 5],
        'sentiment' => 0,
        'routed_to' => 'system',
        'remaining' => $limit['remaining'] - 1
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Sınav provası tespiti (birden fazla soru istiyorsa) ───
$isExamRequest = false;

// 1) Sayı + soru pattern: "5 soru", "20 tane soru", "on soru" vb.
if (preg_match('/(\d+)\s*(?:tane\s+)?soru/u', $msgLower, $m) && (int)$m[1] > 1) {
    $isExamRequest = true;
}
// Yazıyla sayı: "beş soru", "on soru", "yirmi soru" vb.
$wordNums = ['iki','üç','uc','dört','dort','beş','bes','altı','alti','yedi','sekiz','dokuz','on','onbeş','onbes','yirmi'];
if (!$isExamRequest) {
    foreach ($wordNums as $w) {
        if (preg_match('/\b' . $w . '\b\s*(?:tane\s+)?soru/u', $msgLower)) {
            $isExamRequest = true;
            break;
        }
    }
}

// 2) Doğrudan modal tetikleyiciler (her zaman modal)
$examDirectTriggers = [
    'sınav provası', 'sinav provasi', 'prova yap', 'deneme yap',
    'mini sınav', 'mini sinav', 'quiz yap', 'quiz hazırla',
    'beni test et', 'kendimi test et', 'test çöz', 'test coz'
];
if (!$isExamRequest) {
    foreach ($examDirectTriggers as $trigger) {
        if (mb_strpos($msgLower, $trigger) !== false) {
            $isExamRequest = true;
            break;
        }
    }
}
// 3) Kök bazlı soru/test istekleri (Türkçe ek yapısına uygun)
if (!$isExamRequest) {
    // "soru sor", "soru ver", "soru hazırla", "bana soru", "test sor", "soru çöz" vb.
    if (preg_match('/\b(soru|test)\s*(sor|ver|hazırla|hazirla|çöz|coz|çözelim|cozelim|istiyorum|ister\s*mi)/u', $msgLower)) {
        $isExamRequest = true;
    }
    // "bana soru", "bana test", "bir soru", "1 soru"
    if (!$isExamRequest && preg_match('/\b(bana|bir|1)\s*(soru|test)\b/u', $msgLower)) {
        $isExamRequest = true;
    }
    // "soru çözmek istiyorum", "soru çözeyim", "soruları çözelim" — kök: "soru" + çöz/çözelim/istiyorum
    if (!$isExamRequest && preg_match('/soru\w*\s+(çöz|coz|ist|ver|sor|hazırla|hazirla)/u', $msgLower)) {
        $isExamRequest = true;
    }
}

if ($isExamRequest) {
    saveMessage($pdo, $_SESSION['student_id'], $message, 'student', 0, 0, null);

    $aiMsg = 'Hemen seni test edelim! Sınav provası ekranından ders, soru sayısı ve süreyi ayarlayıp başlayabilirsin.';
    saveMessage($pdo, $_SESSION['student_id'], $aiMsg, 'aibuke', 0, 0, 'system');
    incrementMessageCount($pdo, $_SESSION['student_id']);

    echo json_encode([
        'response' => $aiMsg,
        'response_type' => 'exam_setup',
        'sentiment' => 0,
        'routed_to' => 'system',
        'remaining' => $limit['remaining'] - 1
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Guardrails kontrolu
$guardrails = new Guardrails($pdo);
$check = $guardrails->check($message);

// Güvenlik uyarısı var ama bloklanmadı — AI'a iletilecek
$safetyAlert = $check['safety_alert'] ?? null;

if ($check['blocked']) {
    // Guardrail tuttu - mesaj engellendi veya yonlendirildi
    if (isset($check['is_critical']) && $check['is_critical']) {
        // Kritik durum - 183 ALO yonlendirmesi
        $response = $check['redirect'];

        // Kritik güvenlik uyarısı oluştur
        if (isset($check['safety_alert'])) {
            $studentInfo = getStudentInfo($pdo, $_SESSION['student_id']);
            Guardrails::createSafetyAlert(
                $pdo, $_SESSION['student_id'], $studentInfo['school_id'],
                $check['safety_alert']['type'], $check['safety_alert']['severity'],
                $message, $response
            );
        }
    } else {
        // Normal engelleme - yonlendirme mesaji
        $response = $check['redirect'];
    }

    // Mesaji kaydet (engellendi olarak)
    saveMessage($pdo, $_SESSION['student_id'], $message, 'blocked', 0, 0);

    // AI yaniti olarak don
    echo json_encode([
        'response' => $response,
        'blocked' => true,
        'routed_to' => 'guardrails'
    ]);
    exit;
}

// Ogrenci baglamini al
$context = getStudentContext($pdo, $_SESSION['student_id']);

// Aktif konuşmayı al (özet + cache için)
$convStmt = $pdo->prepare("SELECT id, summary FROM aibuke_conversations WHERE student_id = ? AND is_active = 1 LIMIT 1");
$convStmt->execute([$_SESSION['student_id']]);
$activeConv = $convStmt->fetch(PDO::FETCH_ASSOC);

// Özet varsa context'e ekle (cache'e gidecek)
$histRows = [];
if ($activeConv && !empty($activeConv['summary'])) {
    $histRows[] = ['role' => 'aibuke', 'content' => '[Önceki konuşma özeti: ' . $activeConv['summary'] . ']', 'created_at' => null];
}

// Son mesaj zamanını al (conversation_fresh kontrolü için)
$lastMsgStmt = $pdo->prepare("
    SELECT m.created_at FROM aibuke_messages m
    JOIN aibuke_conversations c ON m.conversation_id = c.id
    WHERE c.student_id = ? AND c.is_active = 1
    ORDER BY m.created_at DESC LIMIT 1
");
$lastMsgStmt->execute([$_SESSION['student_id']]);
$lastMsgTime = $lastMsgStmt->fetchColumn() ?: null;
$lastMsgTime = $lastMsgTime ? strtotime($lastMsgTime) : null;

// History: sadece özet (cache'te), yeni mesaj generateResponse'da ekleniyor
$context['history'] = $histRows;
$context['conversation_fresh'] = $lastMsgTime && (time() - $lastMsgTime) < 3600;

// Son AI mesajını sakla (hata analizi için)
$lastAiQuestion = '';
foreach (array_reverse($histRows) as $h) {
    if ($h['role'] === 'aibuke') { $lastAiQuestion = $h['content']; break; }
}

// Sentiment analizi yap
$sentimentAnalyzer = new SentimentAnalyzer();
$sentimentScore = $sentimentAnalyzer->analyze($message);

// Red Flag kontrolu
$redFlag = $sentimentAnalyzer->checkRedFlag($pdo, $_SESSION['student_id']);
if ($redFlag) {
    // Red Flag tetiklendi - AI'a bilgi gonder
    $context['red_flag'] = true;
}

// Güvenlik durumunda AI'a özel prompt ekle
if ($safetyAlert) {
    $safetyPrompt = Guardrails::getSafetyPromptAddition($safetyAlert['type']);
    $context['safety_prompt'] = $safetyPrompt;
    $context['safety_alert_type'] = $safetyAlert['type'];
}

// Router ile AI secimi yap
$router = new Router();

// AI yaniti al
$result = $router->process($message, $context, $imageData, $sentimentScore);

if (!$result['success']) {
    // AI hatasi
    $fallbackMessages = [
        'Ay pardon, bir anlık dalginlik yasadim! Bir daha yazar misin? 🙈',
        'Hmm kafam biraz karisik oldu, tekrar sorabilir misin? 😅',
        'Bi saniye, toparlaniyorum... Simdi tekrar yazabilir misin? 💫',
        'Off ya, tam cevapliyordum ki bir seyler karisti. Tekrar dener misin? 🤔',
        'Su an biraz yogunum, 1-2 dakikaya tekrar yaz bana olur mu? 😊'
    ];
    $response = $fallbackMessages[array_rand($fallbackMessages)];
} else {
    $response = $result['text'];

    // Akıllı Kamera 2.0: Tespit edilen konuyu session'a kaydet
    if (!empty($result['detected_topic'])) {
        $_SESSION['detected_topic'] = $result['detected_topic'];
    }
}

// Güvenlik uyarısı varsa — alert oluştur (AI yanıtıyla birlikte)
if ($safetyAlert) {
    $studentInfo = getStudentInfo($pdo, $_SESSION['student_id']);
    Guardrails::createSafetyAlert(
        $pdo, $_SESSION['student_id'], $studentInfo['school_id'],
        $safetyAlert['type'], $safetyAlert['severity'],
        $message, $response
    );
}

// Mesajlari DB'ye kaydet — marker'ları temizle
$aiModel = $result['model'] ?? 'unknown';
$cleanResponse = preg_replace('/^\[(DOĞRU|YANLIŞ:[A-E])\]\s*/', '', $response);

// [EXAM_SETUP] etiketi: AI soru çözme isteği algıladıysa havuza yönlendir
$responseType = 'chat';
if (strpos($response, '[EXAM_SETUP]') !== false) {
    $responseType = 'exam_setup';
    $response = str_replace('[EXAM_SETUP]', '', $response);
    $response = rtrim($response);
    $cleanResponse = str_replace('[EXAM_SETUP]', '', $cleanResponse);
    $cleanResponse = rtrim($cleanResponse);
}

// Kullanim sayacini artir
incrementMessageCount($pdo, $_SESSION['student_id']);

// === Faz 2: Puan sistemi entegrasyonu ===
$pm = new PointsManager($pdo);
$pointsEarned = 0;

// Soru çözme puanı
$pm->addPoints($_SESSION['student_id'], 'question_solved', 'Soru çözme');

// Fotoğrafla soru puanı
if (!empty($imageData)) {
    $pm->addPoints($_SESSION['student_id'], 'photo_question', 'Fotoğrafla soru');
}

// Doğru/yanlış cevap tespiti (AI yanıtında [DOĞRU] veya [YANLIŞ] varsa)
if (preg_match('/^\[DOĞRU\]/', $response)) {
    $pointsEarned = $pm->addPoints($_SESSION['student_id'], 'correct_answer', 'Doğru cevap');
} elseif (preg_match('/^\[YANLIŞ:([A-E])\]/', $response, $wrongMatch)) {
    // Hata türü analizi — arka planda yap (kullanıcıyı bekletme)
    try {
        $beyin = new Beyin($pdo, $_SESSION['student_id']);
        $errorResult = $beyin->analyzeErrorType(
            $lastAiQuestion ?? '',
            $wrongMatch[1],
            '',
            ''
        );
        if ($errorResult['success'] && !empty($errorResult['analysis']['error_type'])) {
            $errorType = $errorResult['analysis']['error_type'];
            $colMap = [
                'DIKKATSIZLIK' => 'error_type_careless',
                'KONU_EKSIKLIGI' => 'error_type_knowledge',
                'ZAMAN' => 'error_type_time',
                'KAVRAM_KARISIKLIGI' => 'error_type_confusion'
            ];
            $col = $colMap[$errorType] ?? null;
            if ($col) {
                $stmt = $pdo->prepare("
                    INSERT INTO aibuke_student_progress (student_id, subject, {$col})
                    VALUES (?, 'genel', 1)
                    ON DUPLICATE KEY UPDATE {$col} = {$col} + 1
                ");
                $stmt->execute([$_SESSION['student_id']]);
            }
        }
    } catch (\Throwable $e) { /* sessizce geç */ }
}

// Günlük giriş + streak kontrolü
$streak = $pm->recordDailyLogin($_SESSION['student_id']);

// Toplam puan bilgisi
$totalPoints = $pm->getTotalPoints($_SESSION['student_id']);

// === Konuşma özetleme: 20 mesajda bir özet oluştur, token tasarrufu ===
$msgCount = countConversationMessages($pdo, $_SESSION['student_id']);
if ($msgCount > 0 && $msgCount % 20 === 0 && $activeConv) {
    try {
        // Son 20 mesajı al
        $sumStmt = $pdo->prepare("
            SELECT role, content FROM aibuke_messages
            WHERE conversation_id = ? AND role IN ('student','aibuke')
            ORDER BY created_at DESC LIMIT 20
        ");
        $sumStmt->execute([$activeConv['id']]);
        $sumMsgs = array_reverse($sumStmt->fetchAll(PDO::FETCH_ASSOC));

        $sumText = '';
        foreach ($sumMsgs as $sm) {
            $r = $sm['role'] === 'student' ? 'Öğrenci' : 'Aibüke';
            $sumText .= "{$r}: {$sm['content']}\n";
        }

        $oldSummary = $activeConv['summary'] ?? '';
        $sumPrompt = ($oldSummary ? "Önceki özet: {$oldSummary}\n\nYeni mesajlar:\n" : '') . $sumText;

        $sumUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;
        $sumData = [
            'contents' => [['role' => 'user', 'parts' => [['text' => "Bu konuşmayı 2-3 cümleyle özetle. Öğrencinin adını, konuşulan konuları ve önemli detayları koru. Sadece özeti yaz, başka bir şey ekleme.\n\n{$sumPrompt}"]]]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 256]
        ];
        $sumCh = curl_init($sumUrl);
        curl_setopt_array($sumCh, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($sumData), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $sumResp = curl_exec($sumCh);
        curl_close($sumCh);

        $sumResult = json_decode($sumResp, true);
        $newSummary = $sumResult['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($newSummary) {
            $pdo->prepare("UPDATE aibuke_conversations SET summary = ? WHERE id = ?")->execute([trim($newSummary), $activeConv['id']]);
        }
    } catch (\Throwable $e) { error_log('AI response error: ' . $e->getMessage()); }
}

// YouTube sorgusu — öğrenci konu anlatımı istediyse
$youtubeQuery = null;
$topicKeywords = ['nedir', 'ne demek', 'anlat', 'açıkla', 'acikla', 'anlatır mısın', 'öğret', 'ogret', 'nasıl çözülür', 'nasil cozulur', 'konu anlatımı', 'konu anlatimi', 'ne oluyor', 'neden', 'nasıl', 'nasil', 'formül', 'formul', 'teorem', 'tanım', 'tanim'];
$isTopicRequest = false;
foreach ($topicKeywords as $kw) {
    if (mb_strpos($msgLower, $kw) !== false) {
        $isTopicRequest = true;
        break;
    }
}
if ($isTopicRequest && mb_strlen($message) < 150) {
    // Öğrencinin mesajını YouTube sorgusu olarak kullan (kısa temizlenmiş hali)
    $youtubeQuery = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $message);
    $youtubeQuery = preg_replace('/\s+/', ' ', trim($youtubeQuery));
}

// Mesajlari kaydet (youtube_query belirlendikten sonra)
saveMessage($pdo, $_SESSION['student_id'], $message, 'student', $sentimentScore, 0, null);
saveMessage($pdo, $_SESSION['student_id'], $cleanResponse, 'aibuke', 0, 0, $aiModel, $youtubeQuery);

// Yaniti dondur
echo json_encode([
    'response' => $response,
    'response_type' => $responseType,
    'sentiment' => $sentimentScore,
    'routed_to' => $result['routed_to'] ?? 'unknown',
    'remaining' => $limit['remaining'] - 1,
    'red_flag' => $redFlag,
    'truncated' => $result['truncated'] ?? false,
    'points_earned' => $pointsEarned ?: null,
    'total_points' => $totalPoints,
    'streak' => $streak,
    'youtube_query' => $youtubeQuery
]);

/**
 * Aktif konuşmadaki mesaj sayısını al
 */
function countConversationMessages($pdo, $studentId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM aibuke_messages m
        JOIN aibuke_conversations c ON m.conversation_id = c.id
        WHERE c.student_id = ? AND c.is_active = 1
    ");
    $stmt->execute([$studentId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Mesaji DB'ye kaydet
 */
function saveMessage($pdo, $studentId, $content, $role, $sentiment = 0, $tokens = 0, $aiModel = null, $youtubeQuery = null) {
    // Aktif konusmayi bul veya olustur
    $stmt = $pdo->prepare("
        SELECT id FROM aibuke_conversations
        WHERE student_id = ? AND is_active = 1
        ORDER BY updated_at DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conversation) {
        // Yeni konusma olustur
        $stmt = $pdo->prepare("
            INSERT INTO aibuke_conversations (student_id, school_id)
            VALUES (?, (SELECT school_id FROM students WHERE id = ?))
        ");
        $stmt->execute([$studentId, $studentId]);
        $conversationId = $pdo->lastInsertId();
    } else {
        $conversationId = $conversation['id'];
    }

    // Mesaji kaydet
    $stmt = $pdo->prepare("
        INSERT INTO aibuke_messages
        (conversation_id, student_id, role, content, ai_model, sentiment_score, tokens_used, youtube_query)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $conversationId,
        $studentId,
        $role,
        $content,
        $aiModel,
        $sentiment,
        $tokens,
        $youtubeQuery
    ]);

    // Konusma guncellend
    $stmt = $pdo->prepare("
        UPDATE aibuke_conversations
        SET updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$conversationId]);
}
