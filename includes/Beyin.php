<?php
/**
 * Aibuke Öğretmen - Beyin (LOGIC Katmanı)
 *
 * Arka plan analiz ve plan üretim motoru.
 * Şu an MiniMax M2.5 kullanıyor — model değişikliği için
 * sadece callAPI() ve parseResponse() metodlarını güncelle.
 *
 * Görevleri:
 * - Günlük çalışma planı üretimi
 * - Konu analizi / gelişim karnesi
 * - Bildirim içeriği üretimi
 * - Haftalık davranış pattern özeti
 * - Öğrenme stili tespiti
 * - Hata türü analizi
 * - Konuşma özetleme
 * - Geri çağırma mesajı üretimi
 */

require_once __DIR__ . '/../config.php';

class Beyin {

    private $apiKey;
    private $model;
    private $baseUrl;

    public function __construct() {
        $this->apiKey  = MINIMAX_API_KEY;
        $this->model   = MINIMAX_MODEL;
        $this->baseUrl = 'https://api.minimax.io/v1';
    }

    // =========================================================
    // ANA API KATMANI — Model değişikliği burayı etkiler
    // =========================================================

    /**
     * API'ye istek gönder
     * Model değiştirirken sadece bu metodu güncelle
     */
    private function callAPI($systemPrompt, $userPrompt, $temperature = 0.7, $maxTokens = 2048) {
        $url = "{$this->baseUrl}/text/chatcompletion_v2";

        // MiniMax M2.5: system prompt'a "düşünme, direkt yaz" ekle
        $systemPrompt .= "\n\nÖNEMLİ: Düşünce sürecini YAZMA. Sadece nihai çıktıyı döndür.";

        $data = [
            'model'       => $this->model,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt]
            ],
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
            'stream'      => false
        ];

        // Max 2 deneme (content boş dönerse tekrar dene)
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, AI_TIMEOUT);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("Beyin API cURL Error: $curlError");
                return ['success' => false, 'error' => $curlError];
            }

            if ($httpCode !== 200) {
                error_log("Beyin API HTTP $httpCode: $response");
                return ['success' => false, 'error' => "HTTP $httpCode", 'raw' => $response];
            }

            $parsed = $this->parseResponse($response);
            if ($parsed['success']) return $parsed;

            // Content boş — 1 saniye bekle, tekrar dene
            if ($attempt < 2) sleep(1);
        }

        return $parsed;
    }

    /**
     * API yanıtını ayrıştır
     * Model değiştirirken sadece bu metodu güncelle
     */
    private function parseResponse($raw) {
        $result = json_decode($raw, true);

        // M2.5 reasoning modeli bazen content yerine reasoning_content'e yazar
        $content = $result['choices'][0]['message']['content'] ?? '';

        if (empty(trim($content))) {
            // reasoning_content'ten al (M2.5 reasoning davranışı)
            $reasoning = $result['choices'][0]['message']['reasoning_content'] ?? '';
            if (!empty(trim($reasoning))) {
                $content = $reasoning;
                error_log("Beyin: content boş, reasoning_content kullanıldı");
            }
        }

        if (empty(trim($content))) {
            error_log("Beyin API Parse Error (empty content): " . $raw);
            return ['success' => false, 'error' => 'Yanıt ayrıştırılamadı'];
        }

        return [
            'success' => true,
            'text'    => trim($content),
            'model'   => $this->model,
            'tokens'  => $result['usage'] ?? null
        ];
    }

    // =========================================================
    // GÖREV 1: GÜNLÜK ÇALIŞMA PLANI
    // Cron: Her sabah 07:00
    // =========================================================

    /**
     * Öğrenci için günlük çalışma planı üret
     *
     * @param array $student    Öğrenci bilgileri (ad, sınıf, sınav türü)
     * @param array $profile    aibuke_student_profiles kaydı
     * @param array $progress   Son konu performans verileri
     * @param array $patterns   Davranış pattern'leri
     * @return array
     */
    public function generateDailyPlan($student, $profile, $progress = [], $patterns = [], $islenenKonular = []) {
        $examType = strtoupper($profile['exam_type'] ?? 'lgs');
        $name = $student['nickname'] ?: $student['first_name'];

        $systemPrompt = "Sen bir eğitim planlama uzmanısın. Öğrenci için bugünkü çalışma planını oluştur.
Kurallar:
- Plan kısa ve net olsun (max 5 madde)
- Toplam 60-90 dakikalık çalışma hedefle
- Zayıf konulara öncelik ver
- Öğretmenin okulda işlediği güncel konular varsa plana dahil et (pekiştirme ve ödev)
- Motive edici ama gerçekçi ol
- Türkçe yaz
- JSON formatında döndür: {\"baslik\": \"...\", \"maddeler\": [{\"konu\": \"...\", \"sure_dk\": 15, \"aciklama\": \"...\"}], \"motivasyon\": \"...\"}";

        $userPrompt = "Öğrenci: {$name}
Sınav: {$examType}
Sınıf: {$student['class']}";

        if (!empty($profile['target_profession'])) {
            $userPrompt .= "\nHedef Meslek: {$profile['target_profession']}";
        }

        if (!empty($progress)) {
            $userPrompt .= "\n\nSon Performans:";
            foreach ($progress as $p) {
                $userPrompt .= "\n- {$p['subject']}: Doğru %{$p['correct_rate']}, Zayıf konular: {$p['weak_topics']}";
            }
        }

        if (!empty($patterns)) {
            $userPrompt .= "\n\nDavranış Notları:";
            foreach ($patterns as $pat) {
                $userPrompt .= "\n- {$pat['description']}";
            }
        }

        if (!empty($islenenKonular)) {
            $userPrompt .= "\n\nSon 3 Günde Okulda İşlenen Konular (öğretmen kaydı):";
            foreach ($islenenKonular as $ik) {
                $tarih = date('d.m', strtotime($ik['ders_tarihi']));
                $userPrompt .= "\n- [{$tarih}] {$ik['ders']}: {$ik['konu_basligi']}";
                if (!empty($ik['kazanimlar'])) {
                    $userPrompt .= " → Kazanım: {$ik['kazanimlar']}";
                }
            }
            $userPrompt .= "\nBu konuları pekiştirme veya ev ödevi olarak plana ekle.";
        }

        $userPrompt .= "\n\nBugün için çalışma planı oluştur.";

        $result = $this->callAPI($systemPrompt, $userPrompt, 0.7, 1024);

        if ($result['success']) {
            $result['plan'] = $this->tryParseJSON($result['text']);
        }

        return $result;
    }

    // =========================================================
    // GÖREV 1B: HAFTALIK ÇALIŞMA PROGRAMI
    // Chat'ten tetiklenir (öğrenci "program hazırla" dediğinde)
    // =========================================================

    /**
     * Öğrenci için haftalık çalışma programı üret
     */
    public function generateWeeklySchedule($student, $profile, $progress = [], $extraPrefs = '') {
        $examType = strtoupper($profile['exam_type'] ?? 'yks');
        $name = $student['nickname'] ?: $student['first_name'];
        $studyHours = $profile['study_hours_per_day'] ?? 4;

        $systemPrompt = "Sen bir eğitim planlama uzmanısın. Öğrenci için haftalık çalışma programı oluştur.

ÖNEMLİ KURALLAR:
- Öğrencinin okul saatleri ve çalışma tercihleri aşağıda verilecek — bunlara MUTLAKA uy
- Okul saatleri içinde ASLA ders koyma, sadece okul sonrası ve akşam saatlerini kullan
- Oturumlar arası 15 dk mola bırak
- Her oturum 45-90 dk arası olsun
- Konu anlatım, soru çözümü ve tekrar dengeli olsun
- Haftada en az 1 deneme sınavı oturumu koy
- Zayıf konulara öncelik ver
- Türkçe yaz
- Kesinlikle aşağıdaki JSON formatında döndür, başka metin ekleme:

{
  \"title\": \"Haftalık Çalışma Programı\",
  \"daily_hours\": SAYI,
  \"days\": [
    {
      \"day\": \"Pazartesi\",
      \"sessions\": [
        {\"time\": \"16:00-17:30\", \"subject\": \"DERS\", \"topic\": \"KONU\", \"type\": \"konu|soru|tekrar|deneme\"},
        {\"time\": \"17:45-19:00\", \"subject\": \"DERS\", \"topic\": \"KONU\", \"type\": \"konu|soru|tekrar|deneme\"}
      ]
    }
  ],
  \"notes\": \"Genel not\",
  \"motivation\": \"Motivasyon mesajı\"
}";

        $userPrompt = "Öğrenci: {$name}
Sınav: {$examType}
Sınıf: {$student['class']}
Günlük çalışma hedefi: {$studyHours} saat";

        if (!empty($profile['yks_field'])) {
            $fields = ['mf' => 'Sayısal (MF)', 'tm' => 'Eşit Ağırlık (TM)', 'sozel' => 'Sözel', 'dil' => 'Dil'];
            $userPrompt .= "\nAlan: " . ($fields[$profile['yks_field']] ?? $profile['yks_field']);
        }

        if (!empty($profile['target_profession'])) {
            $userPrompt .= "\nHedef Meslek: {$profile['target_profession']}";
        }

        if (!empty($progress)) {
            $userPrompt .= "\n\nSon Performans:";
            foreach (array_slice($progress, 0, 8) as $p) {
                $rate = $p['questions_solved'] > 0
                    ? round(($p['correct_answers'] / $p['questions_solved']) * 100)
                    : 0;
                $userPrompt .= "\n- {$p['subject']}: %{$rate} başarı ({$p['questions_solved']} soru)";
            }
        }

        if (!empty($extraPrefs)) {
            $userPrompt .= "\n\nÖğrencinin ek tercihleri: {$extraPrefs}";
        }

        $userPrompt .= "\n\nHaftalık çalışma programı oluştur.";

        $result = $this->callAPI($systemPrompt, $userPrompt, 0.7, 2048);

        if ($result['success']) {
            $result['schedule'] = $this->tryParseJSON($result['text']);
        }

        return $result;
    }

    // =========================================================
    // GÖREV 2: KONU ANALİZİ / GELİŞİM KARNESİ
    // Cron: Pazar 20:00
    // =========================================================

    /**
     * Öğrencinin haftalık gelişim karnesini oluştur
     */
    public function generateProgressReport($student, $profile, $weeklyStats) {
        $name = $student['nickname'] ?: $student['first_name'];

        $systemPrompt = "Sen bir eğitim analisti. Öğrencinin haftalık performansını değerlendir.
Kurallar:
- Güçlü ve zayıf yönleri belirle
- Geçen haftayla karşılaştır (veri varsa)
- Somut öneriler sun
- Motive edici tonda yaz
- Türkçe yaz
- JSON formatında döndür: {\"ozet\": \"...\", \"guclu_konular\": [...], \"zayif_konular\": [...], \"oneriler\": [...], \"genel_puan\": 0-100, \"motivasyon\": \"...\"}";

        $userPrompt = "Öğrenci: {$name} ({$profile['exam_type']})

Haftalık İstatistikler:
- Toplam mesaj: {$weeklyStats['total_messages']}
- Çözülen soru: {$weeklyStats['questions_solved']}
- Doğru cevap oranı: %{$weeklyStats['correct_rate']}
- Aktif gün: {$weeklyStats['active_days']}/7
- Ortalama oturum: {$weeklyStats['avg_session_min']} dk";

        if (!empty($weeklyStats['subjects'])) {
            $userPrompt .= "\n\nKonu Bazlı:";
            foreach ($weeklyStats['subjects'] as $subj => $data) {
                $userPrompt .= "\n- {$subj}: {$data['correct']}/{$data['total']} doğru";
            }
        }

        if (!empty($weeklyStats['prev_week'])) {
            $userPrompt .= "\n\nGeçen Hafta: Mesaj {$weeklyStats['prev_week']['total_messages']}, Doğru %{$weeklyStats['prev_week']['correct_rate']}";
        }

        $userPrompt .= "\n\nHaftalık gelişim karnesi oluştur.";

        $result = $this->callAPI($systemPrompt, $userPrompt, 0.6, 1024);

        if ($result['success']) {
            $result['report'] = $this->tryParseJSON($result['text']);
        }

        return $result;
    }

    // =========================================================
    // GÖREV 3: BİLDİRİM İÇERİĞİ ÜRETİMİ
    // Cron: Günde 2-3 kez
    // =========================================================

    /**
     * Kişisel bildirim mesajı üret
     */
    public function generateNotification($student, $profile, $type, $extraContext = []) {
        $name = $student['nickname'] ?: $student['first_name'];

        $daysAbsent = $extraContext['days_absent'] ?? '?';
        $achievement = $extraContext['achievement'] ?? '';
        $streakDays = $extraContext['streak_days'] ?? 0;

        $types = [
            'morning'    => "Sabah motivasyon bildirimi. Bugünkü çalışma planını hatırlat. Enerjik ve kısa ol.",
            'reminder'   => "Öğle/akşam hatırlatma. Dün yaptıklarına referans ver, bugün devam etmesini teşvik et.",
            'night'      => "Gece bildirimi. Yarın için küçük bir hedef koy. Dinlenmesini de hatırlat.",
            'comeback'   => "Geri çağırma bildirimi. {$daysAbsent} gündür gelmemiş. Özlem ve merak tonu. Suçlama yapma.",
            'achievement' => "Başarı kutlama bildirimi. Kazandığı: {$achievement}. Coşkulu kutla.",
            'streak'     => "Streak bildirimi. {$streakDays} gün üst üste! Devam etmesini motive et."
        ];

        $systemPrompt = "Sen Aibuke Öğretmen'sin. Push bildirim mesajı üret.
Kurallar:
- MAX 120 karakter (bildirim sığmalı)
- Öğrencinin adını kullan
- Samimi ve motive edici
- Türkçe
- Sadece mesaj metnini döndür, başka bir şey yazma
- Her mesaj FARKLI olmalı: farklı açılış, farklı ton, farklı içerik
- Öğrencinin gerçek verilerinden bahset (konu, streak, puan vs.)";

        $typeInstruction = $types[$type] ?? $types['reminder'];

        $userPrompt = "Öğrenci: {$name} ({$profile['exam_type']})
Bildirim türü: {$typeInstruction}";

        if (!empty($profile['target_profession'])) {
            $userPrompt .= "\nHedef: {$profile['target_profession']}";
        }

        // Bağlamsal zenginleştirme
        $dayOfWeek = $extraContext['day_of_week'] ?? '';
        if ($dayOfWeek) {
            $userPrompt .= "\nBugün: {$dayOfWeek}";
        }

        if (!empty($extraContext['streak_days']) && $extraContext['streak_days'] > 1) {
            $userPrompt .= "\nMevcut streak: {$extraContext['streak_days']} gün üst üste";
        }

        if (!empty($extraContext['total_points'])) {
            $userPrompt .= "\nToplam puan: {$extraContext['total_points']}";
        }

        $yesterdayMsgs = $extraContext['yesterday_messages'] ?? 0;
        if ($yesterdayMsgs > 0) {
            $userPrompt .= "\nDün {$yesterdayMsgs} mesaj yazdı";
        } else {
            $userPrompt .= "\nDün gelmedi, bugün tekrar motive et";
        }

        if (!empty($extraContext['recent_subjects'])) {
            $subjects = implode(', ', $extraContext['recent_subjects']);
            $userPrompt .= "\nSon çalıştığı dersler: {$subjects}";
        }

        if (!empty($extraContext['weak_topics'])) {
            $weakParts = [];
            foreach ($extraContext['weak_topics'] as $wt) {
                $weakParts[] = $wt['subject'] . '/' . $wt['topic'];
            }
            $userPrompt .= "\nGüçlendirmesi gereken: " . implode(', ', $weakParts);
        }

        if (!empty($extraContext['last_badge'])) {
            $userPrompt .= "\nSon kazandığı rozet: {$extraContext['last_badge']}";
        }

        // Tekrar önleme: son mesajları göster
        if (!empty($extraContext['previous_messages'])) {
            $prevList = implode(' | ', array_slice($extraContext['previous_messages'], 0, 3));
            $userPrompt .= "\n\nBU MESAJLARI TEKRAR ETME (son gönderilen): {$prevList}";
        }

        return $this->callAPI($systemPrompt, $userPrompt, 0.9, 1024);
    }

    // =========================================================
    // GÖREV 4: DAVRANIŞ PATTERN ÖZETİ
    // Cron: Haftalık (Pazar 21:00)
    // =========================================================

    /**
     * Öğrencinin davranış pattern'ini analiz et
     */
    public function analyzeBehaviorPatterns($student, $profile, $behaviorData) {
        $name = $student['nickname'] ?: $student['first_name'];

        $systemPrompt = "Sen bir eğitim psikoloğusun. Öğrencinin çalışma davranışlarını analiz et.
Kurallar:
- Çalışma saatleri, sıklık, duygusal eğilimler, hata örüntüleri
- Her pattern için AI'a öneri üret (gelecek konuşmalarda nasıl yaklaşılmalı)
- Türkçe yaz
- JSON formatında döndür: {\"patterns\": [{\"type\": \"study_habit|error_pattern|emotional|engagement\", \"description\": \"...\", \"recommendation\": \"...\", \"confidence\": 0-100}]}";

        $userPrompt = "Öğrenci: {$name} ({$profile['exam_type']})

Son 7 Gün Verileri:
- Aktif saatler: {$behaviorData['active_hours']}
- Ortalama oturum süresi: {$behaviorData['avg_session_min']} dk
- Toplam mesaj: {$behaviorData['total_messages']}
- Sentiment ortalaması: {$behaviorData['avg_sentiment']} (-2 ile +2 arası)
- Fotoğraf yükleme: {$behaviorData['photo_count']}
- Ortalama mesaj uzunluğu: {$behaviorData['avg_msg_length']} karakter";

        if (!empty($behaviorData['emotion_trend'])) {
            $userPrompt .= "\nDuygu trendi: {$behaviorData['emotion_trend']}";
        }

        if (!empty($behaviorData['common_errors'])) {
            $userPrompt .= "\nSık yapılan hatalar: {$behaviorData['common_errors']}";
        }

        if (!empty($behaviorData['streak'])) {
            $userPrompt .= "\nMevcut streak: {$behaviorData['streak']} gün";
        }

        $userPrompt .= "\n\nDavranış pattern analizi yap.";

        $result = $this->callAPI($systemPrompt, $userPrompt, 0.5, 1024);

        if ($result['success']) {
            $result['patterns'] = $this->tryParseJSON($result['text']);
        }

        return $result;
    }

    // =========================================================
    // GÖREV 5: ÖĞRENME STİLİ TESPİTİ
    // Cron: Haftalık
    // =========================================================

    /**
     * Öğrencinin öğrenme stilini tespit et
     */
    public function detectLearningStyle($student, $styleData) {
        $systemPrompt = "Sen bir eğitim psikoloğusun. Öğrencinin öğrenme stilini tespit et.
4 stil: gorsel, isitsel, okuma_yazma, kinestetik

Kurallar:
- Verilere dayalı karar ver
- Güven skoru belirle (0-100)
- Yeterli veri yoksa düşük güven skoru ver
- JSON formatında döndür: {\"style\": \"gorsel|isitsel|okuma_yazma|kinestetik\", \"confidence\": 0-100, \"reason\": \"...\", \"teaching_tips\": [...]}";

        $userPrompt = "Öğrenci davranış verileri (son 30 gün):
- Ortalama mesaj uzunluğu: {$styleData['avg_msg_length']} karakter
- Fotoğraf yükleme sayısı: {$styleData['photo_uploads']}
- 'Göster/çiz/şekil' kelime sıklığı: {$styleData['visual_keywords']}
- 'Anlat/dinle' kelime sıklığı: {$styleData['auditory_keywords']}
- 'Adım adım/dene/yap' kelime sıklığı: {$styleData['kinesthetic_keywords']}
- TTS kullanım sayısı: {$styleData['tts_usage']}
- Toplam mesaj: {$styleData['total_messages']}

Öğrenme stilini tespit et.";

        $result = $this->callAPI($systemPrompt, $userPrompt, 0.3, 1024);

        if ($result['success']) {
            $result['style'] = $this->tryParseJSON($result['text']);
        }

        return $result;
    }

    // =========================================================
    // GÖREV 6: HATA TÜRÜ ANALİZİ
    // Chat sırasında veya cron
    // =========================================================

    /**
     * Yanlış cevabın hata türünü analiz et
     */
    public function analyzeErrorType($question, $studentAnswer, $correctAnswer, $studentContext = '') {
        $systemPrompt = "Sen bir eğitim uzmanısın. Öğrencinin yanlış cevabının nedenini analiz et.

4 hata türü:
- DIKKATSIZLIK: Konuyu biliyor ama işlem/okuma hatası yapmış
- KONU_EKSIKLIGI: Alt konuyu bilmiyor
- ZAMAN: Soruyu çözebilir ama yetiştirememiş
- KAVRAM_KARISIKLIGI: İki benzer konuyu karıştırmış

JSON formatında döndür: {\"error_type\": \"dikkatsizlik|konu_eksikligi|zaman|kavram_karisikligi\", \"reason\": \"...\", \"suggestion\": \"...\"}";

        $userPrompt = "Soru: {$question}
Öğrencinin cevabı: {$studentAnswer}
Doğru cevap: {$correctAnswer}";

        if ($studentContext) {
            $userPrompt .= "\nÖğrenci hakkında not: {$studentContext}";
        }

        $userPrompt .= "\n\nHata türünü analiz et.";

        $result = $this->callAPI($systemPrompt, $userPrompt, 0.3, 1024);

        if ($result['success']) {
            $result['analysis'] = $this->tryParseJSON($result['text']);
        }

        return $result;
    }

    /**
     * Toplu hata analizi — tek API çağrısında tüm yanlışları analiz et
     */
    public function analyzeErrorsBatch(array $wrongItems) {
        if (empty($wrongItems)) return [];

        $systemPrompt = "Sen bir eğitim uzmanısın. Öğrencinin yanlış cevaplarının nedenlerini toplu analiz et.

4 hata türü:
- DIKKATSIZLIK: Konuyu biliyor ama işlem/okuma hatası yapmış
- KONU_EKSIKLIGI: Alt konuyu bilmiyor
- ZAMAN: Soruyu çözebilir ama yetiştirememiş
- KAVRAM_KARISIKLIGI: İki benzer konuyu karıştırmış

JSON formatında döndür:
{\"analyses\": [{\"id\": 1, \"error_type\": \"DIKKATSIZLIK\", \"reason\": \"...\"}, ...]}

SADECE JSON döndür, başka bir şey yazma.";

        $userPrompt = "Aşağıdaki yanlış cevapları analiz et:\n\n";
        foreach ($wrongItems as $i => $item) {
            $userPrompt .= "Soru " . $item['id'] . " (Konu: " . $item['topic'] . "):\n";
            $userPrompt .= "Soru metni: " . mb_substr($item['text'], 0, 200) . "\n";
            $userPrompt .= "Öğrenci cevabı: " . $item['student_answer'] . " | Doğru cevap: " . $item['correct'] . "\n\n";
        }

        $maxTokens = min(count($wrongItems) * 200 + 200, 2048);
        $result = $this->callAPI($systemPrompt, $userPrompt, 0.3, $maxTokens);

        if (!$result['success']) return [];

        $parsed = $this->tryParseJSON($result['text']);
        return $parsed['analyses'] ?? [];
    }

    // =========================================================
    // GÖREV 7: KONUŞMA ÖZETLEME
    // Her 30 mesajda bir
    // =========================================================

    /**
     * Konuşma geçmişini özetle (context window yönetimi)
     */
    public function summarizeConversation($messages) {
        $systemPrompt = "Aşağıdaki öğrenci-AI konuşmasını özetle.
Kurallar:
- Hangi konular konuşuldu
- Öğrencinin anladığı ve anlamadığı noktalar
- Duygu durumu değişimleri
- Max 200 kelime
- Türkçe yaz
- Sadece özeti döndür";

        $formatted = '';
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'student' ? 'Öğrenci' : 'Aibuke';
            $formatted .= "{$role}: {$msg['content']}\n";
        }

        return $this->callAPI($systemPrompt, $formatted, 0.3, 1024);
    }

    // =========================================================
    // GÖREV 8: GERİ ÇAĞIRMA MESAJI
    // Cron: Yokluk tespitinde
    // =========================================================

    /**
     * Gelmemiş öğrenci için geri çağırma mesajı üret
     */
    public function generateComebackMessage($student, $profile, $daysAbsent, $lastTopics = []) {
        $name = $student['nickname'] ?: $student['first_name'];

        $systemPrompt = "Sen Aibuke Öğretmen'sin. Uzun süredir gelmeyen öğrenciye sıcak bir geri çağırma mesajı yaz.
Kurallar:
- Suçlama yapma, özlem tonu kullan
- Son çalıştığı konuya referans ver (varsa)
- Geri gelmesi için küçük bir hedef koy
- Samimi ve kısa ol (max 3 cümle)
- Sadece mesajı döndür";

        $userPrompt = "Öğrenci: {$name} ({$profile['exam_type']})
Yokluk süresi: {$daysAbsent} gün";

        if (!empty($profile['target_profession'])) {
            $userPrompt .= "\nHedef meslek: {$profile['target_profession']}";
        }

        if (!empty($lastTopics)) {
            $userPrompt .= "\nSon çalışılan konular: " . implode(', ', $lastTopics);
        }

        return $this->callAPI($systemPrompt, $userPrompt, 0.9, 1024);
    }

    // =========================================================
    // YARDIMCI METODLAR
    // =========================================================

    /**
     * AI yanıtından JSON parse etmeye çalış
     * AI bazen markdown code block içinde döndürüyor
     */
    private function tryParseJSON($text) {
        // Direkt parse dene
        $decoded = json_decode($text, true);
        if ($decoded !== null) return $decoded;

        // ```json ... ``` içinden çıkar
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if ($decoded !== null) return $decoded;
        }

        // { ... } bloğunu bul
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded !== null) return $decoded;
        }

        // [ ... ] dizi bloğunu bul
        if (preg_match('/\[[\s\S]*\]/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded !== null) return $decoded;
        }

        // Kesilmiş JSON kurtarma — sondaki eksik ] ve } ekle
        $trimmed = trim($text);
        // ```json bloğu varsa içini al
        if (preg_match('/```(?:json)?\s*([\s\S]*)$/', $trimmed, $m)) {
            $trimmed = trim($m[1]);
        }
        // Eksik kapanış braketlerini dene
        for ($fix = 0; $fix < 4; $fix++) {
            $tryJson = $trimmed . str_repeat('}', $fix) . ']' . '}';
            $decoded = json_decode($tryJson, true);
            if ($decoded !== null) return $decoded;
            $tryJson = $trimmed . str_repeat('"', 1) . str_repeat('}', $fix) . ']}';
            $decoded = json_decode($tryJson, true);
            if ($decoded !== null) return $decoded;
        }

        // Parse edilemedi, ham metin döndür
        return ['raw_text' => $text];
    }

    /**
     * callAPI'yi dışarıdan erişilebilir yap (exam_simulation vb. için)
     */
    public function callPublic($systemPrompt, $userPrompt, $temperature = 0.7, $maxTokens = 2048) {
        return $this->callAPI($systemPrompt, $userPrompt, $temperature, $maxTokens);
    }

    /**
     * tryParseJSON'ı dışarıdan erişilebilir yap
     */
    public function parseJSON($text) {
        return $this->tryParseJSON($text);
    }
}
