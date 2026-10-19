<?php
/**
 * Aibuke Ogretmen - Gemini Flash API Wrapper
 * Soru cozumu, OCR, konu anlatimi
 */

require_once __DIR__ . '/../config.php';

class GeminiAPI {

    private $apiKey;
    private $model;
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct() {
        $this->apiKey = GEMINI_API_KEY;
        $this->model = GEMINI_MODEL;
    }

    /**
     * Context cache oluştur veya mevcut olanı kullan
     * System prompt + özet cache'lenir, her istekte tekrar gönderilmez
     */
    private function getOrCreateCache($context) {
        $systemPrompt = $this->buildSystemPrompt($context);
        $summary = '';
        foreach ($context['history'] ?? [] as $h) {
            if (str_starts_with($h['content'] ?? '', '[Önceki konuşma özeti:')) {
                $summary = $h['content'];
                break;
            }
        }

        // Cache key: system prompt + özet hash'i
        $cacheHash = md5($systemPrompt . $summary);
        $sessionKey = 'gemini_cache_' . ($_SESSION['student_id'] ?? 0);

        // Session'da cache varsa ve hash eşleşiyorsa kullan
        if (!empty($_SESSION[$sessionKey]) && ($_SESSION[$sessionKey]['hash'] ?? '') === $cacheHash) {
            // Cache'in hala geçerli olup olmadığını kontrol et
            $cacheName = $_SESSION[$sessionKey]['name'];
            $checkUrl = "{$this->baseUrl}/{$cacheName}?key={$this->apiKey}";
            $ch = curl_init($checkUrl);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200) {
                return $cacheName;
            }
            // Cache expired, yeniden oluştur
            unset($_SESSION[$sessionKey]);
        }

        // Cache içeriğini hazırla: system prompt + varsa özet
        $cacheContents = [];
        if ($summary) {
            $cacheContents[] = [
                'role' => 'user',
                'parts' => [['text' => $summary]]
            ];
            $cacheContents[] = [
                'role' => 'model',
                'parts' => [['text' => 'Anladım, önceki konuşma bağlamını hatırlıyorum.']]
            ];
        }

        $cacheData = [
            'model' => "models/{$this->model}",
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'ttl' => '1800s' // 30 dakika
        ];
        if (!empty($cacheContents)) {
            $cacheData['contents'] = $cacheContents;
        }

        $url = "{$this->baseUrl}/cachedContents?key={$this->apiKey}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($cacheData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $result = json_decode($resp, true);
            $cacheName = $result['name'] ?? null;
            if ($cacheName) {
                $_SESSION[$sessionKey] = ['name' => $cacheName, 'hash' => $cacheHash];
                return $cacheName;
            }
        }

        // Cache oluşturulamadı — null dön, fallback ile devam edilecek
        error_log("Gemini Cache Error: HTTP {$code} - {$resp}");
        return null;
    }

    /**
     * Mesaja yanit uret
     */
    public function generateResponse($message, $context, $imageData = null) {
        // Cache'i dene
        $cacheName = $this->getOrCreateCache($context);

        // History'den özet mesajını çıkar (cache'te zaten var)
        $contents = [];
        foreach ($context['history'] ?? [] as $h) {
            if (str_starts_with($h['content'] ?? '', '[Önceki konuşma özeti:')) continue;
            $contents[] = [
                'role' => $h['role'] === 'student' ? 'user' : 'model',
                'parts' => [['text' => $h['content']]]
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $data = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2048,
                'topP' => 0.95,
                'topK' => 40
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE']
            ]
        ];

        if ($cacheName) {
            // Cache var — system prompt + özet cache'ten gelecek
            $data['cachedContent'] = $cacheName;
        } else {
            // Cache yok — fallback: system prompt'u normal gönder
            $data['system_instruction'] = ['parts' => [['text' => $this->buildSystemPrompt($context)]]];
        }

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => AI_TIMEOUT
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            // Cache hatası olabilir — cache'siz tekrar dene
            if ($cacheName) {
                $sessionKey = 'gemini_cache_' . ($_SESSION['student_id'] ?? 0);
                unset($_SESSION[$sessionKey]);
                unset($data['cachedContent']);
                $data['system_instruction'] = ['parts' => [['text' => $this->buildSystemPrompt($context)]]];

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => AI_TIMEOUT
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            }

            if ($httpCode !== 200) {
                error_log("Gemini API Error: HTTP $httpCode - $response");
                return [
                    'success' => false,
                    'error' => 'AI servisinde bir hata oluştu, tekrar deneyelim mi?',
                    'http_code' => $httpCode
                ];
            }
        }

        $result = json_decode($response, true);

        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            error_log("Gemini API Response Error: " . json_encode($result));
            return [
                'success' => false,
                'error' => 'Yanıt alınamadı, tekrar deneyelim mi?'
            ];
        }

        $finishReason = $result['candidates'][0]['finishReason'] ?? '';

        return [
            'success' => true,
            'text' => $result['candidates'][0]['content']['parts'][0]['text'],
            'model' => 'gemini',
            'truncated' => $finishReason === 'MAX_TOKENS',
            'cached' => $cacheName ? true : false
        ];
    }

    /**
     * Streaming yanit uret (SSE)
     */
    public function generateStreamResponse($message, $context) {
        // Streaming icin ayni API ama stream parametresi yok
        // Normal cevap uret, frontend'de kelime kelime gösterilir
        return $this->generateResponse($message, $context);
    }

    /**
     * Gorsel (fotograf) analizi
     */
    public function analyzeImage($imageBase64, $prompt, $context) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $systemPrompt = $this->buildSystemPrompt($context);

        $data = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\nBu soruyu çöz ve adım adım açıkla: " . $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => 'image/jpeg',
                                'data' => $imageBase64
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 4096,
                'topP' => 0.95
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, AI_TIMEOUT);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Gemini Vision API Error: HTTP $httpCode");
            return [
                'success' => false,
                'error' => 'Fotoğraf analiz edilemedi, tekrar deneyelim mi?'
            ];
        }

        $result = json_decode($response, true);

        return [
            'success' => true,
            'text' => $result['candidates'][0]['content']['parts'][0]['text'],
            'model' => 'gemini-vision'
        ];
    }

    /**
     * System prompt olustur
     */
    private function buildSystemPrompt($context) {
        $persona = $this->getPersonaPrompt($context['persona_mode'] ?? 'koc');

        $fresh = $context['conversation_fresh'] ?? false;

        $prompt = "## ZORUNLU DAVRANIŞ KURALLARI (asla ihlal etme)
1. " . ($fresh
    ? "Konuşma DEVAM EDİYOR — 'Merhaba', 'Nasılsın', 'Hoş geldin' kesinlikle YAZMA."
    : "Yeni veya uzun aradan sonraki konuşma — kısa ve sıcak bir giriş yapabilirsin.") . "
2. SADECE şu anki mesajı cevapla. Önceki konuşma konularını ASLA özetleme veya referans verme.
3. Cevaplar kısa ve öz olsun — gereksiz uzatma.
4. Siyaset, din, cinsellik, şiddet konularında ASLA yorum yapma. İntihar ima varsa: '183 ALO Sosyal Destek Hattı' yönlendir.
5. Yanıtlarını TAMAMEN Türkçe yaz. Parantez içinde bile İngilizce kelime veya çeviri YAZMA.
6. Test sorusu, sınav sorusu, çoktan seçmeli soru ASLA ÜRETME. Öğrenci soru çözmek, test yapmak, pratik yapmak isterse şu mesajı ver: 'Hemen seni test edelim! Aşağıdaki sınav provası butonuna tıklayarak başlayabilirsin. 📝' ve cevabının sonuna [EXAM_SETUP] etiketi ekle. Soru havuzumuzda gerçek sınav soruları var, oradan çözeceğiz.
7. [DOĞRU]/[YANLIŞ:X] kuralı: SADECE öğrenci 'Cevabım A/B/C/D/E' formatında şık seçtiğinde VE sen o soruyu az önce sorduysan kullan. Normal sohbette KESİNLİKLE kullanma.
9. Aşağıdaki öğrenci bilgilerini (hedef, öğrenme stili, davranış gözlemleri) motivasyon amaçlı kullanabilirsin. Ama öğrenci sormadıkça bunları kendiliğinden paylaşma.
10. Sen Aibüke Öğretmen'sin. Gerçek bir öğretmen gibi davran. 'Yapay zeka', 'AI', 'dil modeli', 'programlandım' gibi ifadeler KULLANMA. Yaşın, fiziksel varlığın hakkında sorulursa espriyle geçiştir.

## KİMLİĞİN
Sen Aibüke Öğretmen'sin — dijital eğitim mentoru, sınav koçu. Samimi, sıcak, sabırlı bir öğretmen.

{$persona}

## ÖĞRENCİ BİLGİLERİ
{$this->formatStudentInfo($context)}
";

        return $prompt;
    }

    /**
     * Persona'ya göre prompt ekle
     */
    private function getPersonaPrompt($persona) {
        $personas = [
            'abla' => "LGS MODU (8. sınıf):
- 13-14 yaşında bir çocukla konuşuyorsun
- Cümleler kısa ve enerjik tut
- Bol emoji kullan (🌟⭐🎯💪🔥)
- 'Hadiiii!', 'Super!', 'Efsane!' gibi hitaplar
- Soru formatı: 4 seçenek (A-B-C-D)",

            'koc' => "YKS MODU (12. sınıf):
- 17-19 yaşında bir üniversite adayıyla konuşuyorsun
- Veri odaklı konuş: 'Net analiz', 'Hedef puan', 'Kalan gün'
- Daha olgun hitaplar: 'Başarabilirsin', 'Hedefe yaklaşıyorsun'
- Üniversite ve meslek odaklı motivasyon
- Soru formatı: 5 seçenek (A-B-C-D-E)",

            'rehber' => "OBP MODU (9-10-11. sınıf):
- Okul başarısı için rehberlik yapıyorsun
- 'Şimdi notlarını yüksek tutarsın, YKS'de çok avantajlı olursun'
- Yazılı sınavlara hazırlık odaklı
- OBP'nin önemine vurgu yap"
        ];

        return $personas[$persona] ?? $personas['koc'];
    }

    /**
     * Ogrenci bilgilerini formatla
     */
    private function formatStudentInfo($context) {
        $info = [];

        if (isset($context['student'])) {
            $s = $context['student'];
            $info[] = "Ad: {$s['first_name']} {$s['last_name']}";
            $info[] = "Sınıf: {$s['class']}";
            $info[] = "Okul: {$s['school_name']}";
        }

        if (isset($context['profile'])) {
            $p = $context['profile'];
            $info[] = "Sınav türü: " . strtoupper($p['exam_type']);
            if ($p['target_profession']) {
                $info[] = "Hedef: {$p['target_profession']}";
            }
        }

        if (!empty($context['profile']['learning_style'])) {
            $styleMap = [
                'gorsel' => 'Görsel öğrenen — şekil, grafik, tablo ve görsel örneklerle anlat. Yazılı metinleri kısa tut, mümkünse madde madde listeler kullan.',
                'isitsel' => 'İşitsel öğrenen — anlatımı akıcı ve hikayeleştirerek yap. Diyaloglar, sesli düşünme örnekleri kullan. "Şöyle düşün..." tarzı ifadeler etkili.',
                'okuma_yazma' => 'Okuma/yazma odaklı — detaylı açıklamalar, tanımlar, formüller yaz. Özetler ve not çıkarmaya uygun yapıda cevap ver.',
                'kinestetik' => 'Kinestetik öğrenen — adım adım çözüm, pratik örnekler, "kendin dene" yaklaşımı. Soyut anlatımdan kaçın, somut örneklerle ilerle.'
            ];
            $style = $context['profile']['learning_style'];
            if (isset($styleMap[$style])) {
                $info[] = "\nÖğrenme Stili: " . $styleMap[$style];
            }
        }

        if (!empty($context['behavior_patterns'])) {
            $info[] = "\nDavranış Gözlemleri:";
            foreach (array_slice($context['behavior_patterns'], 0, 3) as $pat) {
                $info[] = "- {$pat['ai_recommendation']}";
            }
        }

        return implode("\n", $info);
    }
}
