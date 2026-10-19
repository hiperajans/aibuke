<?php
/**
 * Aibuke - Soru Havuzu API (SuperAdmin)
 *
 * POST: action = upload_pdf | update | delete | toggle_active
 * GET:  action = list | stats
 */
// CLI modunda çağrılırsa (process_pdf.php) sadece fonksiyonları yükle
if (php_sapi_name() === 'cli' && defined('QUESTION_POOL_FUNCTIONS_ONLY')) {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../includes/Beyin.php';
    return; // switch/case'e girme, sadece fonksiyonlar tanımlansın
}

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/Beyin.php';

// SuperAdmin auth kontrolu
if (empty($_SESSION['super_admin_logged_in']) || empty($_SESSION['super_admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Yetkisiz — SuperAdmin girisi gerekli']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ═══════════════════════════════════════
    // PDF YUKLE VE ISLE
    // ═══════════════════════════════════════
    case 'upload_pdf':
        // 1. PDF al (dosya veya URL)
        $pdfPath = null;

        if (!empty($_FILES['pdf']['tmp_name']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
            $pdfPath = dirname(__DIR__, 2) . '/uploads/pdfs/' . time() . '_' . basename($_FILES['pdf']['name']);
            move_uploaded_file($_FILES['pdf']['tmp_name'], $pdfPath);
        } elseif (!empty($_POST['pdf_url'])) {
            $url = filter_var($_POST['pdf_url'], FILTER_VALIDATE_URL);
            if (!$url) {
                echo json_encode(['error' => 'Gecersiz URL']);
                exit;
            }
            $pdfPath = dirname(__DIR__, 2) . '/uploads/pdfs/' . time() . '_download.pdf';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 60
            ]);
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode !== 200 || empty($content)) {
                echo json_encode(['error' => 'PDF indirilemedi (HTTP ' . $httpCode . ')']);
                exit;
            }
            file_put_contents($pdfPath, $content);
        } else {
            echo json_encode(['error' => 'PDF dosyasi veya URL gerekli']);
            exit;
        }

        // 2. PDF alındı — arka planda işle
        // Path traversal koruması
        $realPath = realpath(dirname($pdfPath));
        if ($realPath === false || strpos($pdfPath, '..') !== false) {
            echo json_encode(['error' => 'Gecersiz dosya yolu']);
            exit;
        }
        $cmd = "php " . escapeshellarg(dirname(__DIR__, 2) . '/cron/process_pdf.php') . " " . escapeshellarg($pdfPath) . " > " . escapeshellarg(dirname(__DIR__, 2) . '/uploads/pdfs/process.log') . " 2>&1 &";
        exec($cmd);

        echo json_encode([
            'success' => true,
            'message' => 'PDF alindi, arka planda isleniyor. Durum icin sayfayi yenileyin.'
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ═══════════════════════════════════════
    // SORU LISTELE
    // ═══════════════════════════════════════
    case 'list':
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $where = "1=1";
        $params = [];

        // LIKE sorgularında % ve _ karakterlerini escape et
        $escapeLike = function($val) { return str_replace(['%', '_'], ['\\%', '\\_'], $val); };

        if (!empty($_GET['subject'])) { $where .= " AND subject = ?"; $params[] = $_GET['subject']; }
        if (!empty($_GET['topic'])) { $where .= " AND topic LIKE ?"; $params[] = '%' . $escapeLike($_GET['topic']) . '%'; }
        if (!empty($_GET['difficulty'])) { $where .= " AND difficulty = ?"; $params[] = $_GET['difficulty']; }
        if (!empty($_GET['exam_type'])) { $where .= " AND exam_type = ?"; $params[] = $_GET['exam_type']; }
        if (!empty($_GET['source'])) { $where .= " AND source LIKE ?"; $params[] = '%' . $escapeLike($_GET['source']) . '%'; }
        if (isset($_GET['is_active']) && $_GET['is_active'] !== '') { $where .= " AND is_active = ?"; $params[] = (int)$_GET['is_active']; }
        if (!empty($_GET['search'])) { $where .= " AND question_text LIKE ?"; $params[] = '%' . $escapeLike($_GET['search']) . '%'; }

        // Toplam sayi
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_question_pool WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Sayfa verileri
        $dataParams = array_merge($params, [$perPage, $offset]);
        $dataStmt = $pdo->prepare("SELECT * FROM aibuke_question_pool WHERE $where ORDER BY id DESC LIMIT ? OFFSET ?");
        $dataStmt->execute($dataParams);
        $questions = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'questions' => $questions,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ═══════════════════════════════════════
    // ISTATISTIKLER
    // ═══════════════════════════════════════
    case 'stats':
        $stats = [];

        // Toplam
        $stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM aibuke_question_pool WHERE is_active = 1")->fetchColumn();

        // Sinav tipine gore
        $stmt = $pdo->query("SELECT exam_type, COUNT(*) as cnt FROM aibuke_question_pool WHERE is_active = 1 GROUP BY exam_type");
        $stats['by_exam_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Derse gore
        $stmt = $pdo->query("SELECT subject, COUNT(*) as cnt FROM aibuke_question_pool WHERE is_active = 1 GROUP BY subject ORDER BY cnt DESC");
        $stats['by_subject'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Kaynaga gore
        $stmt = $pdo->query("SELECT COALESCE(source,'Bilinmiyor') as src, COUNT(*) as cnt FROM aibuke_question_pool WHERE is_active = 1 GROUP BY source ORDER BY cnt DESC LIMIT 20");
        $stats['by_source'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Konuya gore (top 30)
        $stmt = $pdo->query("SELECT CONCAT(subject, ' > ', COALESCE(topic,'?')) as full_topic, COUNT(*) as cnt FROM aibuke_question_pool WHERE is_active = 1 GROUP BY subject, topic ORDER BY cnt DESC LIMIT 30");
        $stats['by_topic'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        echo json_encode($stats, JSON_UNESCAPED_UNICODE);
        break;

    // ═══════════════════════════════════════
    // SORU GUNCELLE
    // ═══════════════════════════════════════
    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID gerekli']); exit; }

        $fields = [];
        $params = [];
        $allowedFields = ['subject','topic','difficulty','exam_type','question_text','option_a','option_b','option_c','option_d','option_e','correct_answer','explanation','source','has_image','is_active'];
        foreach ($allowedFields as $f) {
            if (isset($_POST[$f])) {
                $fields[] = "$f = ?";
                $params[] = htmlspecialchars($_POST[$f], ENT_QUOTES, 'UTF-8');
            }
        }
        if (empty($fields)) { echo json_encode(['error' => 'Guncellenecek alan yok']); exit; }

        $params[] = $id;
        $pdo->prepare("UPDATE aibuke_question_pool SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        echo json_encode(['success' => true]);
        break;

    // ═══════════════════════════════════════
    // SORU SIL
    // ═══════════════════════════════════════
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID gerekli']); exit; }
        $pdo->prepare("DELETE FROM aibuke_question_pool WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    // ═══════════════════════════════════════
    // AKTIF/PASIF TOGGLE
    // ═══════════════════════════════════════
    case 'toggle_active':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID gerekli']); exit; }
        $pdo->prepare("UPDATE aibuke_question_pool SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    // ═══════════════════════════════════════
    // PDF İŞLEME DURUMU
    // ═══════════════════════════════════════
    case 'process_status':
        $statusFile = dirname(__DIR__, 2) . '/uploads/pdfs/status.json';
        if (file_exists($statusFile)) {
            $data = json_decode(file_get_contents($statusFile), true);
            echo json_encode($data ?: ['status' => 'idle']);
        } else {
            echo json_encode(['status' => 'idle']);
        }
        break;

    default:
        echo json_encode(['error' => 'Gecersiz action']);
}


// ═══════════════════════════════════════════════════════════
// YARDIMCI FONKSIYONLAR
// ═══════════════════════════════════════════════════════════

/**
 * Sinav tipini otomatik tespit et
 */
function detectExamType($text) {
    $upper = mb_strtoupper($text, 'UTF-8');
    if (preg_match('/\bTYT\b|TEMEL\s*YETERL[İI]L[İI]K/u', $upper)) return 'tyt';
    if (preg_match('/\bAYT\b|ALAN\s*YETERL[İI]L[İI]K/u', $upper)) return 'ayt';
    if (preg_match('/\bLGS\b|L[İI]SELERE\s*GE[CÇ][İI][SŞ]/u', $upper)) return 'lgs';
    return 'tyt'; // varsayilan
}

/**
 * Kaynak otomatik tespit et
 */
function detectSource($text) {
    // Yil tespit
    $year = '';
    if (preg_match('/\b(20\d{2})\b/', $text, $m)) $year = $m[1];

    // OSYM
    if (stripos($text, 'ÖSYM') !== false || stripos($text, 'Ölçme, Seçme ve Yerleştirme') !== false) {
        $type = detectExamType($text);
        return $year . ' ' . strtoupper($type) . ' ÖSYM';
    }

    // Yayincilar
    $publishers = [
        'Ankara Yayıncılık' => 'Ankara Yayincilik',
        'ankara yayıncılık' => 'Ankara Yayincilik',
        'Limit Yayınları' => 'Limit Yayinlari',
        'Palme Yayınevi' => 'Palme',
        'Tonguç' => 'Tonguc',
        '3D Yayınları' => '3D Yayinlari',
        'Okyanus' => 'Okyanus',
        'Karekök' => 'Karekok',
        'Hız ve Renk' => 'Hiz ve Renk',
        'Bilgi Sarmal' => 'Bilgi Sarmal',
        'Esen Yayınları' => 'Esen',
        'Apotemi' => 'Apotemi',
    ];
    foreach ($publishers as $search => $name) {
        if (mb_stripos($text, $search) !== false) {
            // Deneme numarasi
            $deneme = '';
            if (preg_match('/(\d+)\.\s*(?:Deneme|DENEME|sayı|SAYI)/u', $text, $dm)) {
                $deneme = ' ' . $dm[1] . '. Deneme';
            }
            $type = detectExamType($text);
            return $name . ' ' . strtoupper($type) . $deneme;
        }
    }

    $type = detectExamType($text);
    return $year . ' ' . strtoupper($type) . ' Deneme';
}

/**
 * Cevap anahtarini parse et
 * Sonuc: ['turkce' => ['1'=>'B', '2'=>'C', ...], 'matematik' => [...]]
 */
function parseAnswerKey($text) {
    $keys = [];

    // Cevap anahtari bolumunu bul
    $patterns = [
        '/CEVAP\s*ANAHTARI/iu',
        '/YANIT\s*ANAHTARI/iu',
        '/CEVAP\s*ANAHTAR/iu',
    ];

    $answerSection = '';
    foreach ($patterns as $pat) {
        if (preg_match($pat, $text, $m, PREG_OFFSET_CAPTURE)) {
            $answerSection = substr($text, $m[0][1]);
            break;
        }
    }

    // Son sayfalarda cevap anahtari olmayabilir — son 10000 karaktere bak (ÖSYM filigranı çok yer kaplıyor)
    if (empty($answerSection)) {
        $answerSection = substr($text, -10000);
    }

    if (empty($answerSection)) return [];

    // Bolum basliklarini bul ve cevaplari grupla
    $sectionMap = [
        'turkce' => '/T[ÜU]RK[CÇ]E\s*TEST[İI]/iu',
        'sosyal' => '/SOSYAL\s*B[İI]L[İI]MLER/iu',
        'matematik' => '/(?:TEMEL\s*)?MATEMAT[İI]K\s*TEST[İI]/iu',
        'fen' => '/FEN\s*B[İI]L[İI]MLER[İI]/iu',
        'fizik' => '/F[İI]Z[İI]K\s*TEST[İI]/iu',
        'kimya' => '/K[İI]MYA\s*TEST[İI]/iu',
        'biyoloji' => '/B[İI]YOLOJ[İI]\s*TEST[İI]/iu',
        'edebiyat' => '/EDEB[İI]YAT\s*TEST[İI]/iu',
        'tarih' => '/TAR[İI]H\s*TEST[İI]/iu',
        'cografya' => '/CO[ĞG]RAFYA\s*TEST[İI]/iu',
    ];

    $lines = explode("\n", $answerSection);

    // Başlık satırını bul — birden fazla ders adı aynı satırda olabilir (ÖSYM formatı)
    $columns = []; // [{key, pos}, ...] sütun pozisyonları
    foreach ($lines as $idx => $line) {
        $found = [];
        foreach ($sectionMap as $key => $pattern) {
            if (preg_match($pattern, $line, $m, PREG_OFFSET_CAPTURE)) {
                $found[] = ['key' => $key, 'pos' => $m[0][1]];
            }
        }
        if (count($found) >= 2) {
            // Çoklu sütun başlığı bulundu (ÖSYM formatı)
            usort($found, fn($a, $b) => $a['pos'] <=> $b['pos']);
            $columns = $found;
            // Başlıktan sonraki satırlarda cevapları pozisyona göre ata
            for ($i = $idx + 1; $i < count($lines); $i++) {
                $cline = $lines[$i];
                if (preg_match_all('/(\d+)\.\s*([A-E])\b/', $cline, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                    foreach ($matches as $match) {
                        $num = (int)$match[1][0];
                        $answer = strtoupper($match[2][0]);
                        $charPos = $match[0][1];
                        // Hangi sütuna ait?
                        $belongsTo = $columns[0]['key'];
                        for ($c = count($columns) - 1; $c >= 0; $c--) {
                            if ($charPos >= $columns[$c]['pos'] - 5) {
                                $belongsTo = $columns[$c]['key'];
                                break;
                            }
                        }
                        $keys[$belongsTo][$num] = $answer;
                    }
                }
            }
            return $keys;
        }
    }

    // Tek sütun formatı (yayıncı formatı) — ders başlıkları alt alta
    $currentSection = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        foreach ($sectionMap as $key => $pattern) {
            if (preg_match($pattern, $line)) {
                $currentSection = $key;
                break;
            }
        }

        if ($currentSection && preg_match_all('/(\d+)\.\s*([A-E])\b/', $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $keys[$currentSection][(int)$match[1]] = strtoupper($match[2]);
            }
        }
    }

    return $keys;
}

/**
 * Konu dagilimi tablosunu parse et (Ankara Yayincilik gibi)
 * Sonuc: ['turkce' => ['1'=>'Sozcukte Anlam', ...], ...]
 */
function parseTopicDistribution($text) {
    $topics = [];

    // "KONU DAĞILIMI" veya "KONU DAGILIM" basligi var mi?
    if (!preg_match('/KONU\s*DA[ĞG]ILIM/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
        return [];
    }

    $distStart = $m[0][1];
    // Konu dagilimi tablosu genelde 1-2 sayfa surer (ilk soru basligina kadar)
    $distEnd = $distStart + 5000;
    if (preg_match('/\bBu testte\b/u', $text, $endMatch, PREG_OFFSET_CAPTURE, $distStart + 100)) {
        $distEnd = $endMatch[0][1];
    }

    $distSection = substr($text, $distStart, $distEnd - $distStart);

    // Bolum basliklarini tespit et
    $sectionMap = [
        'turkce' => '/T[ÜU]RK[CÇ]E/iu',
        'sosyal' => '/SOSYAL/iu',
        'matematik' => '/MATEMAT[İI]K/iu',
        'fen' => '/FEN/iu',
    ];

    $currentSection = null;
    $lines = explode("\n", $distSection);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Bolum basligi
        foreach ($sectionMap as $key => $pattern) {
            if (preg_match($pattern, $line) && strlen($line) < 40) {
                $currentSection = $key;
                break;
            }
        }

        // "1  Sozcukte Anlam" veya "1     Sozcukte Anlam   15    Yazim Kurallari"
        if ($currentSection && preg_match_all('/(\d+)\s{2,}([A-Za-zÀ-ÿçÇğĞıİöÖşŞüÜ\s\-\.\(\)\/\']+?)(?=\d{1,2}\s{2,}|$)/u', $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $num = (int)$match[1];
                $topic = trim($match[2]);
                if ($num > 0 && $num <= 40 && mb_strlen($topic) > 2) {
                    $topics[$currentSection][$num] = $topic;
                }
            }
        }
    }

    return $topics;
}

/**
 * Metni ders bolumlerine ayir
 */
function splitIntoSections($text) {
    $sections = [];

    // Bolum baslik pattern'leri ve baslangic pozisyonlari
    $headers = [];
    $sectionPatterns = [
        'turkce' => '/(?:2025-TYT\/T[ÜU]R|TYT\/T[üu]rk[çc]e|T[ÜU]RK[CÇ]E\s*TEST[İI])/iu',
        'sosyal' => '/(?:2025-TYT\/SOS|TYT\/Sosyal|SOSYAL\s*B[İI]L[İI]MLER\s*TEST[İI])/iu',
        'matematik' => '/(?:2025-TYT\/MAT|TYT\/Matematik|(?:TEMEL\s*)?MATEMAT[İI]K\s*TEST[İI])/iu',
        'fen' => '/(?:2025-TYT\/FEN|TYT\/Fen|FEN\s*B[İI]L[İI]MLER[İI]\s*TEST[İI])/iu',
        'fizik' => '/(?:F[İI]Z[İI]K\s*TEST[İI])/iu',
        'kimya' => '/(?:K[İI]MYA\s*TEST[İI])/iu',
        'biyoloji' => '/(?:B[İI]YOLOJ[İI]\s*TEST[İI])/iu',
        'edebiyat' => '/(?:T[ÜU]RK\s*D[İI]L[İI]\s*VE\s*EDEB[İI]YAT|EDEB[İI]YAT\s*TEST[İI])/iu',
        'tarih' => '/(?:TAR[İI]H\s*TEST[İI])/iu',
        'cografya' => '/(?:CO[ĞG]RAFYA\s*TEST[İI])/iu',
    ];

    foreach ($sectionPatterns as $key => $pattern) {
        if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
            $headers[$key] = $m[0][1];
        }
    }

    if (empty($headers)) return [];

    // Pozisyona gore sirala
    asort($headers);
    $keys = array_keys($headers);

    for ($i = 0; $i < count($keys); $i++) {
        $key = $keys[$i];
        $start = $headers[$key];
        $end = ($i + 1 < count($keys)) ? $headers[$keys[$i + 1]] : strlen($text);

        // Cevap anahtari bolumunu kes
        $sectionText = substr($text, $start, $end - $start);
        if (preg_match('/CEVAP\s*ANAHTARI|SINAV(DA|A)\s*UYULACAK/iu', $sectionText, $cutMatch, PREG_OFFSET_CAPTURE)) {
            $sectionText = substr($sectionText, 0, $cutMatch[0][1]);
        }

        $sections[$key] = $sectionText;
    }

    return $sections;
}

/**
 * Bolum key'ini ders adina cevir
 */
function mapSubjectName($key) {
    $map = [
        'turkce' => 'Türkçe',
        'sosyal' => 'Sosyal Bilimler',
        'matematik' => 'Matematik',
        'fen' => 'Fen Bilimleri',
        'fizik' => 'Fizik',
        'kimya' => 'Kimya',
        'biyoloji' => 'Biyoloji',
        'edebiyat' => 'Edebiyat',
        'tarih' => 'Tarih',
        'cografya' => 'Coğrafya',
    ];
    return $map[$key] ?? $key;
}

/**
 * MiniMax ile soru metinlerini + siklari ayikla
 */
function extractQuestionsWithAI($beyin, $sectionText, $subject, $expectedCount, $detectTopics = false, $examType = 'tyt') {
    // Metni temizle — filigran izlerini azalt
    $cleanText = preg_replace('/\b(Bu\s+so\s*ru\s*la\s*r|ÖSYM|T\.C\.\s*Ö|ku\s*ru\s*lu\s*ş|ya\s*zı\s*lı\s*i\s*zn\s*i|ol\s*ma\s*k\s*sı\s*zın)\b/u', '', $sectionText);

    // MiniMax M2.7: 204K context — tek seferde gönder
    return callAIForExtraction($beyin, $cleanText, $subject, $detectTopics, $examType) ?? [];
}

/**
 * Metni soru numaralarindan parcala
 */
function splitTextIntoChunks($text, $maxChars) {
    $chunks = [];
    $currentChunk = '';
    $lines = explode("\n", $text);

    foreach ($lines as $line) {
        if (mb_strlen($currentChunk) + mb_strlen($line) > $maxChars) {
            // Yeni soru basinda mi?
            if (preg_match('/^\s*\d{1,2}\.\s/', $line)) {
                $chunks[] = $currentChunk;
                $currentChunk = $line . "\n";
            } else {
                $currentChunk .= $line . "\n";
            }
        } else {
            $currentChunk .= $line . "\n";
        }
    }
    if (!empty(trim($currentChunk))) $chunks[] = $currentChunk;

    return $chunks;
}

/**
 * Tek bir metin parcasi icin AI cagir
 */
function callAIForExtraction($beyin, $text, $subject, $detectTopics, $examType = 'tyt') {
    $topicInstruction = $detectTopics
        ? 'Her soru icin konuyu tespit et ve "topic" alanina yaz (ornegin: "Sozcukte Anlam", "Turev", "Kuvvet-Hareket" vb.).'
        : '"topic" alanini bos birak.';

    $optionCount = ($examType === 'lgs') ? 4 : 5;
    $optionFields = ($optionCount === 4)
        ? '"A": "A sikki", "B": "B sikki", "C": "C sikki", "D": "D sikki"'
        : '"A": "A sikki", "B": "B sikki", "C": "C sikki", "D": "D sikki", "E": "E sikki"';
    $optionNote = ($optionCount === 4)
        ? 'Bu LGS sinavi — her soruda 4 sik var (A, B, C, D). E sikki YOK.'
        : 'Her soruda 5 sik var (A, B, C, D, E).';

    $systemPrompt = "Sen bir sinav PDF\'inden soru ayiklayan uzmansin.

GOREV: Asagidaki metin bir sinav kitapciginin {$subject} bolumudur. Her soruyu ayikla.

KURALLAR:
- Metinde OSYM filigran izleri (rastgele harfler, 'T.C.' tekrarlari) olabilir — bunlari YOKSAY
- Sorularda resim/grafik/harita referansi varsa has_image: true yap
- Soru numarasini dogru tespit et
- Tum siklari tam olarak ver
- {$optionNote}
- {$topicInstruction}

SADECE JSON dondur, baska bir sey yazma:
{\"questions\": [
  {\"number\": 1, \"text\": \"Tam soru metni\", {$optionFields}, \"has_image\": false, \"topic\": \"\"}
]}";

    $result = $beyin->callPublic($systemPrompt, $text, 0.2, 16000);

    if (!$result['success']) {
        error_log("QuestionPool AI extraction error: " . ($result['error'] ?? 'unknown'));
        return null;
    }

    $parsed = $beyin->parseJSON($result['text']);

    if (isset($parsed['questions'])) return $parsed['questions'];
    if (is_array($parsed) && isset($parsed[0]['text'])) return $parsed;

    return null;
}
