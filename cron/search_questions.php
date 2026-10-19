<?php
/**
 * Aibuke - Otomatik Soru Arama (Cron)
 *
 * MiniMax M2.7 + Web Search ile internetten sınav soruları bulur,
 * çıkarır ve soru havuzuna ekler. Duplicate kontrolü yapar.
 *
 * Kullanım: php search_questions.php [exam_type] [subject]
 * Örnekler:
 *   php search_questions.php              → tüm sınav tipleri, rastgele ders
 *   php search_questions.php tyt matematik → sadece TYT Matematik
 *   php search_questions.php lgs           → LGS rastgele ders
 *
 * Cron: 0 3,7,11,15,19,23 * * * php /var/www/aibuke/cron/search_questions.php >> /var/log/aibuke_search.log 2>&1
 */

set_time_limit(3000);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Beyin.php';
require_once __DIR__ . '/../includes/WebSearcher.php';

// ═══════════════════════════════════════════════════
// YAPILANDIRMA
// ═══════════════════════════════════════════════════

$EXAM_SUBJECTS = [
    'lgs' => [
        'Türkçe', 'Matematik', 'Fen Bilimleri', 'İnkılap Tarihi',
        'Din Kültürü', 'İngilizce'
    ],
    'tyt' => [
        'Türkçe', 'Matematik', 'Fizik', 'Kimya', 'Biyoloji',
        'Tarih', 'Coğrafya', 'Felsefe'
    ],
    'ayt' => [
        'Matematik', 'Fizik', 'Kimya', 'Biyoloji',
        'Edebiyat', 'Tarih', 'Coğrafya', 'Felsefe'
    ],
];

// Arama sorgu şablonları — PDF ve çözümlü soru odaklı
$SEARCH_TEMPLATES = [
    '{exam} {subject} çıkmış sorular çözümlü pdf filetype:pdf',
    '{exam} {subject} deneme sınavı soruları pdf indir',
    '{exam} {subject} soru bankası pdf çözümlü {year}',
    '{exam} {subject} test soruları A B C D E cevaplı',
    '{exam} {subject} konu testi çözümlü sorular ve cevapları',
    'site:meb.gov.tr {exam} {subject} test soruları pdf',
    'site:meb.k12.tr {exam} {subject} soruları pdf',
    '{year} {exam} {subject} deneme çözümlü pdf',
    '{exam} {subject} yaprak test pdf',
    '{exam} {subject} örnek sorular ve cevapları {year}',
    '{subject} test soruları çoktan seçmeli pdf',
    '{exam} {subject} çalışma kağıdı soruları pdf',
    '{subject} deneme sınavı soruları ve cevapları pdf {year}',
    '{exam} {subject} kazanım testi pdf',
];

// ═══════════════════════════════════════════════════
// BAŞLANGIÇ
// ═══════════════════════════════════════════════════

// SearXNG çalışıyor mu kontrol et, değilse başlat
$searxCheck = @file_get_contents('http://127.0.0.1:8888/');
if ($searxCheck === false) {
    exec('SEARXNG_SETTINGS_PATH=/etc/searxng/settings.yml nohup python3 -c "from searx.webapp import app; app.run(host=\'127.0.0.1\', port=8888, debug=False)" > /tmp/searxng.log 2>&1 &');
    sleep(5);
    log_msg("SearXNG başlatıldı");
}

$beyin = new Beyin();
$searcher = new WebSearcher();

// Komut satırı parametreleri
$targetExam = $argv[1] ?? null;
$targetSubject = $argv[2] ?? null;

$totalInserted = 0;
$totalSkipped = 0;
$startTime = time();

log_msg("=== Soru Arama Başladı ===");

// Hangi sınav türlerini tarayacağız?
$examsToSearch = $targetExam ? [$targetExam] : array_keys($EXAM_SUBJECTS);

foreach ($examsToSearch as $examType) {
    if (!isset($EXAM_SUBJECTS[$examType])) {
        log_msg("HATA: Geçersiz sınav tipi: $examType");
        continue;
    }

    $subjects = $EXAM_SUBJECTS[$examType];

    // Belirli bir ders mi yoksa rastgele mi?
    if ($targetSubject) {
        $subjects = [$targetSubject];
    } else {
        // Her çalışmada rastgele 3 ders seç
        shuffle($subjects);
        $subjects = array_slice($subjects, 0, 3);
    }

    foreach ($subjects as $subject) {
        log_msg("--- $examType / $subject aranıyor ---");

        $result = searchAndExtract($beyin, $searcher, $examType, $subject, $pdo);

        $totalInserted += $result['inserted'];
        $totalSkipped += $result['skipped'];

        log_msg("  → {$result['inserted']} yeni soru eklendi, {$result['skipped']} duplicate atlandı");

        // Rate limiting — aramalar arası bekleme
        sleep(3);
    }
}

$elapsed = time() - $startTime;
log_msg("=== Tamamlandı: {$totalInserted} yeni soru, {$totalSkipped} duplicate, {$elapsed}s ===\n");


// ═══════════════════════════════════════════════════
// FONKSİYONLAR
// ═══════════════════════════════════════════════════

/**
 * Bir sınav türü + ders için web'de soru ara ve çıkar
 */
function searchAndExtract($beyin, $searcher, $examType, $subject, $pdo) {
    global $SEARCH_TEMPLATES;

    $inserted = 0;
    $skipped = 0;

    $examLabel = strtoupper($examType);

    // 3 farklı sorgu dene — daha fazla kaynak bul
    $usedTemplates = array_rand($SEARCH_TEMPLATES, min(3, count($SEARCH_TEMPLATES)));
    if (!is_array($usedTemplates)) $usedTemplates = [$usedTemplates];

    foreach ($usedTemplates as $ti) {
        $year = rand(2023, 2026);
        $query = str_replace(
            ['{exam}', '{subject}', '{year}'],
            [$examLabel, $subject, $year],
            $SEARCH_TEMPLATES[$ti]
        );

        log_msg("  Arama: \"$query\"");

        $searchResults = $searcher->search($query, 8);

        if (empty($searchResults)) {
            log_msg("  Sonuç bulunamadı");
            continue;
        }

        log_msg("  " . count($searchResults) . " sonuç bulundu");

        // En iyi 3 sonucu tara
        $tried = 0;
        foreach ($searchResults as $result) {
            if ($tried >= 3) break;

            $url = $result['url'];

            // Video/sosyal medya/çözüm dosyalarını atla
            if (preg_match('/(youtube|instagram|facebook|twitter|tiktok|pinterest|\.jpg|\.png|\.gif)/i', $url)) {
                continue;
            }
            // Çözüm/cevap PDF'lerini atla (soru içermez)
            if (preg_match('/(cozum|çözüm|cevap|answer|solution)/i', $url)) {
                log_msg("  → Çözüm dosyası, atlandı: $url");
                continue;
            }

            log_msg("  Sayfa: " . mb_substr($result['title'], 0, 60) . "...");
            log_msg("  URL: $url");

            // Sayfa içeriğini çek
            $pageContent = $searcher->fetchPage($url);
            $contentLen = $pageContent ? mb_strlen($pageContent) : 0;

            if ($contentLen < 500) {
                log_msg("  → İçerik yetersiz ({$contentLen} kar), atlandı");
                continue;
            }

            // Soru pattern'i kontrol — A) B) C) veya benzeri şık pattern'i olmalı
            $sikCount = preg_match_all('/[A-E]\)/', $pageContent);
            if ($sikCount < 4) {
                log_msg("  → Soru formatı bulunamadı (şık sayısı: $sikCount), atlandı");
                continue;
            }

            log_msg("  → İçerik: {$contentLen} kar, ~{$sikCount} şık tespit edildi");

            // İçerik çok uzunsa akıllıca kes
            $pageContent = smartTruncate($pageContent, $subject);

            // MiniMax ile soru çıkar
            $questions = extractQuestionsFromContent($beyin, $pageContent, $examType, $subject, $url);

            if (!empty($questions)) {
                log_msg("  → " . count($questions) . " soru çıkarıldı, kaydediliyor...");
                // Hemen DB'ye kaydet — timeout'a karşı koruma
                foreach ($questions as $qq) {
                    // MiniMax ders tespiti varsa onu kullan, yoksa arama dersini
                    $qSubject = !empty($qq['subject']) ? $qq['subject'] : $subject;
                    $saveResult = saveQuestion($pdo, $qq, $examType, $qSubject, $url);
                    if ($saveResult === 'inserted') $inserted++;
                    elseif ($saveResult === 'duplicate') $skipped++;
                }
                log_msg("  → Kayıt: +{$inserted} yeni, {$skipped} duplicate");
            } else {
                log_msg("  → Soru çıkarılamadı");
            }

            $tried++;
            sleep(2);
        }

        sleep(2); // Sorgular arası bekleme
    }

    return ['inserted' => $inserted, 'skipped' => $skipped];
}

/**
 * MiniMax ile sayfa içeriğinden soru çıkar
 * Doğrudan API çağrısı — reasoning bypass, sadece JSON content
 */
function extractQuestionsFromContent($beyin, $content, $examType, $subject, $sourceUrl) {
    $optionCount = ($examType === 'lgs') ? 4 : 5;
    $optionFields = ($optionCount === 4)
        ? '"A": "...", "B": "...", "C": "...", "D": "..."'
        : '"A": "...", "B": "...", "C": "...", "D": "...", "E": "..."';
    $optionNote = ($optionCount === 4)
        ? 'LGS soruları — 4 şık (A-D).'
        : '5 şık (A-E).';

    if ($examType === 'lgs') {
        $subjectList = 'Türkçe, Matematik, Fen Bilimleri, İnkılap Tarihi, Din Kültürü, İngilizce';
    } elseif ($examType === 'tyt') {
        $subjectList = 'Türkçe, Matematik, Fizik, Kimya, Biyoloji, Tarih, Coğrafya, Felsefe';
    } else {
        $subjectList = 'Matematik, Fizik, Kimya, Biyoloji, Edebiyat, Tarih, Coğrafya, Felsefe';
    }

    $systemPrompt = "Sen bir sınav sorusu çıkarma uzmanısın. Düşünme, analiz etme — SADECE JSON döndür.

GÖREV: Metinden çoktan seçmeli soruları çıkar. Öncelik: {$subject}. Ama başka derslerin soruları da varsa onları da al.

DERSLER: {$subjectList}

KURALLAR:
- Sadece çoktan seçmeli, tam metin+şıkları olan soruları al
- Doğru cevap varsa yaz, yoksa boş bırak
- {$optionNote}
- Resim/grafik gerektiren soruları ATLA
- Her soru için dersi, konuyu ve zorluğu tespit et
- \"subject\" alanına sorunun gerçek dersini yaz (yukarıdaki listeden)

SADECE şu JSON formatında döndür, başka hiçbir şey yazma:
{\"questions\": [{\"text\": \"Soru\", {$optionFields}, \"correct\": \"A\", \"subject\": \"Ders Adı\", \"topic\": \"Konu\", \"difficulty\": \"medium\"}]}

Soru bulamazsan: {\"questions\": []}";

    // Doğrudan MiniMax API çağrısı — reasoning_effort düşük tut
    $url = 'https://api.minimax.io/v1/text/chatcompletion_v2';
    $data = [
        'model' => MINIMAX_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $content]
        ],
        'temperature' => 0.1,
        'max_tokens' => 65536,
        'stream' => false,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MINIMAX_API_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 600,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) {
        error_log("SearchQuestions API error: HTTP $httpCode");
        return [];
    }

    $result = json_decode($response, true);

    // content veya reasoning_content'ten JSON çıkar
    $text = $result['choices'][0]['message']['content'] ?? '';
    if (empty(trim($text))) {
        $text = $result['choices'][0]['message']['reasoning_content'] ?? '';
    }

    if (empty(trim($text))) return [];

    // JSON çıkar — text içinde veya reasoning içinde olabilir
    $parsed = $beyin->parseJSON($text);

    // reasoning_content'te JSON gömülü olabilir — ikinci deneme
    if (!isset($parsed['questions']) && !empty($result['choices'][0]['message']['reasoning_content'])) {
        $reasoning = $result['choices'][0]['message']['reasoning_content'];
        // Reasoning içindeki JSON bloğunu bul
        if (preg_match('/\{[\s\S]*"questions"[\s\S]*\}/u', $reasoning, $jsonMatch)) {
            $parsed2 = json_decode($jsonMatch[0], true);
            if (isset($parsed2['questions'])) {
                $parsed = $parsed2;
            }
        }
    }

    if (isset($parsed['questions']) && is_array($parsed['questions'])) {
        return $parsed['questions'];
    }

    return [];
}

/**
 * Soruyu DB'ye kaydet (duplicate kontrolü ile)
 * @return string 'inserted' | 'duplicate' | 'error'
 */
function saveQuestion($pdo, $q, $examType, $subject, $sourceUrl = 'internet') {
    $text = trim($q['text'] ?? '');
    if (mb_strlen($text) < 20) return 'error'; // Çok kısa soru

    // Şıkları kontrol et
    $optA = trim($q['A'] ?? '');
    $optB = trim($q['B'] ?? '');
    if (empty($optA) || empty($optB)) return 'error'; // Eksik şık

    // Otomatik ders tespiti — yanlış derse kaydedilmesin
    $allText = $text . ' ' . $optA . ' ' . $optB;
    $topicText = $text . ' ' . ($q['topic'] ?? '');

    // İngilizce tespiti
    $englishWords = preg_match_all('/\b(the|is|are|was|were|which|what|who|how|would|should|could|below|following|according|because|about|their|they|have|has|with|from|that|this|your|does|want|like|going|will|said|friends|party|invite)\b/i', $allText);
    $totalWords = str_word_count($allText);
    if ($totalWords > 0 && $englishWords / $totalWords > 0.12) {
        $subject = 'İngilizce';
    }
    // LGS ders tespiti (sözel PDF'ler birden fazla ders içerir)
    elseif ($examType === 'lgs') {
        if ($subject !== 'İnkılap Tarihi' && preg_match('/(inkılap|atatürk|kurtuluş savaşı|osmanlı|cumhuriyet|lozan|sevr|mondros|sakarya|dumlupınar|tbmm|genelge|kongre|çanakkale|trablusgarp|cephe|milli mücadele|padişah|sultan|suikast|misak|amasya|erzurum|sivas)/iu', $topicText)) {
            $subject = 'İnkılap Tarihi';
        } elseif ($subject !== 'Fen Bilimleri' && preg_match('/(kalıtım|gen |dna|mendel|çaprazlama|fotosentez|ekosistem|besin zinciri|adaptasyon|hücre|atom|element|asit|baz|kuvvet|enerji|elektrik|basit makine|kaldıraç|makara|madde|mol |periyodik)/iu', $topicText)) {
            $subject = 'Fen Bilimleri';
        } elseif ($subject !== 'Din Kültürü' && preg_match('/(kader|kaza|namaz|oruç|hac|zekat|allah|kuran|kur.an|peygamber|ibadet|din kültürü|ayet|sure|hz\.|islami)/iu', $topicText)) {
            $subject = 'Din Kültürü';
        }
    }

    // Konu adına göre Türkçe tespiti (sözel PDF'lerde paragraf/anlam soruları yanlış derse gidebilir)
    $topicName = $q['topic'] ?? '';
    if ($subject !== 'Türkçe' && preg_match('/(Paragraf|Sözcük|Cümle|Deyim|Atasözü|Yazım|Noktalama|Fiil|Zamir|Zarf|Özne|Yüklem|Nesne|Tümleç|Gerundium|Ana Düşünce|Düşünce Akışı|Anlam|Ses Olay|Ünsüz|Ünlü|Hece|Yapım Eki|Çekim Eki|İsim Tamla|Sıfat Tamla|Söz Sanat|Edebi Sanat|Mecaz|Benzetme|Kişileştirme|Anlatım Bozuk|İmla|Ses Bilgisi|Metin Türleri)/iu', $topicName)) {
        if (!preg_match('/(hangi savaş|hangi cephe|hangi antlaşma|atatürk|mustafa kemal|osmanlı devleti|milli mücadele|kurtuluş savaşı)/iu', $text)) {
            $subject = 'Türkçe';
        }
    }

    // Doğru cevap
    $correct = strtoupper(trim($q['correct'] ?? ''));
    if (!in_array($correct, ['A', 'B', 'C', 'D', 'E'])) {
        $correct = ''; // Bilinmiyor
    }

    // Duplicate hash — soru metninin ilk 150 karakteri
    $hash = md5(mb_strtolower(preg_replace('/\s+/', '', mb_substr($text, 0, 150))));

    // Duplicate kontrolü
    $check = $pdo->prepare("SELECT id FROM aibuke_question_pool WHERE question_hash = ?");
    $check->execute([$hash]);
    if ($check->fetch()) {
        return 'duplicate';
    }

    // Kaydet
    $difficulty = in_array($q['difficulty'] ?? '', ['easy', 'medium', 'hard']) ? $q['difficulty'] : 'medium';

    try {
        $stmt = $pdo->prepare("INSERT INTO aibuke_question_pool
            (subject, topic, difficulty, exam_type, question_text, option_a, option_b, option_c, option_d, option_e,
             correct_answer, explanation, source, has_image, question_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");

        $stmt->execute([
            $subject,
            $q['topic'] ?? null,
            $difficulty,
            $examType,
            $text,
            $optA,
            $optB,
            trim($q['C'] ?? ''),
            trim($q['D'] ?? ''),
            $examType !== 'lgs' ? trim($q['E'] ?? '') : null,
            $correct,
            $q['explanation'] ?? null,
            'Web: ' . mb_substr($sourceUrl, 0, 200),
            $hash
        ]);

        return 'inserted';
    } catch (Exception $e) {
        error_log("SearchQuestions DB error: " . $e->getMessage());
        return 'error';
    }
}

/**
 * Duplicate hash hesapla
 */
function questionHash($text) {
    return md5(mb_strtolower(preg_replace('/\s+/', '', mb_substr(trim($text), 0, 150))));
}

/**
 * ÖSYM filigranlarını ve gereksiz metni temizle
 */
function cleanContent($content) {
    // ÖSYM filigran izleri
    $content = preg_replace('/\b(Bu\s+so\s*ru\s*la\s*r|T\.C\.\s*Ö|ku\s*ru\s*lu\s*ş|ya\s*zı\s*lı\s*i\s*zn\s*i|ol\s*ma\s*k\s*sı\s*zın)\b/u', '', $content);
    // Tekrarlanan boşluklar
    $content = preg_replace('/[ \t]{3,}/', '  ', $content);
    // Çok fazla boş satır
    $content = preg_replace('/\n{4,}/', "\n\n", $content);
    return $content;
}

/**
 * İçeriği akıllıca kes — ilgili ders bölümüne odaklan
 * MiniMax'e max 10000 karakter gönder
 */
function smartTruncate($content, $subject, $maxChars = 30000) {
    // Önce temizle
    $content = cleanContent($content);

    $len = mb_strlen($content);
    if ($len <= $maxChars) return $content;

    // Ders bölümünü bulmaya çalış (TYT/AYT sınav kitapçığı formatı)
    $subjectPatterns = [
        'Matematik' => '/(?:MATEMAT[İI]K\s*TEST[İI]|Temel Matematik|TEMEL MATEMAT[İI]K)/iu',
        'Türkçe' => '/(?:T[ÜU]RK[CÇ]E\s*TEST[İI])/iu',
        'Fen Bilimleri' => '/(?:FEN\s*B[İI]L[İI]MLER[İI]\s*TEST[İI])/iu',
        'Fizik' => '/(?:F[İI]Z[İI]K\s*TEST[İI])/iu',
        'Kimya' => '/(?:K[İI]MYA\s*TEST[İI])/iu',
        'Biyoloji' => '/(?:B[İI]YOLOJ[İI]\s*TEST[İI])/iu',
        'Tarih' => '/(?:TAR[İI]H\s*TEST[İI]|[İI]NK[İI]LAP)/iu',
        'Coğrafya' => '/(?:CO[ĞG]RAFYA\s*TEST[İİ])/iu',
        'Sosyal Bilimler' => '/(?:SOSYAL\s*B[İI]L[İI]MLER)/iu',
        'Edebiyat' => '/(?:EDEB[İI]YAT\s*TEST[İI])/iu',
    ];

    // Ders başlığını bul
    $pattern = $subjectPatterns[$subject] ?? null;
    $startPos = 0;

    if ($pattern && preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
        $startPos = $m[0][1];
        log_msg("    smartTruncate: '{$subject}' bölümü pos {$startPos}'te bulundu");
    }

    // Başlangıçtan itibaren maxChars al
    $chunk = mb_substr($content, $startPos, $maxChars);

    // Son tam soruya kadar kes
    if (preg_match_all('/\b(\d{1,2})\.\s/', $chunk, $nums, PREG_OFFSET_CAPTURE)) {
        $lastMatch = end($nums[0]);
        if ($lastMatch[1] > $maxChars * 0.7) {
            $chunk = mb_substr($chunk, 0, $lastMatch[1]);
        }
    }

    return $chunk;
}

function log_msg($msg) {
    $ts = date('Y-m-d H:i:s');
    echo "[$ts] $msg\n";
}
