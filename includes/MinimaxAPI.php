<?php
/**
 * Aibuke Ogretmen - MiniMax M2-her API Wrapper
 * Motivasyon, karakter, duygusal destek
 */

require_once __DIR__ . '/../config.php';

class MinimaxAPI {

    private $apiKey;
    private $model;
    private $baseUrl = 'https://api.minimax.io/v1';

    public function __construct() {
        $this->apiKey = MINIMAX_API_KEY;
        $this->model = MINIMAX_MODEL;
    }

    /**
     * Mesaja yanit uret (motivasyon odakli)
     */
    public function generateResponse($message, $context, $sentiment = 0) {
        $url = "{$this->baseUrl}/text/chatcompletion_v2";

        $systemPrompt = $this->buildSystemPrompt($context, $sentiment);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Konusma gecmisini ekle
        foreach ($context['history'] ?? [] as $h) {
            $messages[] = [
                'role' => $h['role'] === 'student' ? 'user' : 'assistant',
                'content' => $h['content']
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.8,
            'max_tokens' => 2048,
            'stream' => false
        ];

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
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("MiniMax API Error: HTTP $httpCode - $response");
            return [
                'success' => false,
                'error' => 'Motivasyon sisteminde bir sorun oluştu, tekrar deneyelim mi?',
                'http_code' => $httpCode
            ];
        }

        $result = json_decode($response, true);

        if (!isset($result['choices'][0]['message']['content'])) {
            error_log("MiniMax API Response Error: " . json_encode($result));
            return [
                'success' => false,
                'error' => 'Yanıt alınamadı, tekrar deneyelim mi?'
            ];
        }

        $finishReason = $result['choices'][0]['finish_reason'] ?? '';

        return [
            'success' => true,
            'text' => $result['choices'][0]['message']['content'],
            'model' => 'MiniMax-M3',
            'truncated' => $finishReason === 'length'
        ];
    }

    /**
     * System prompt olustur (M2-her icin)
     */
    private function buildSystemPrompt($context, $sentiment = 0) {
        $persona = $this->getPersonaPrompt($context['persona_mode'] ?? 'koc');

        // Sentiment'e göre ton ayarla
        $sentimentNote = $this->getSentimentNote($sentiment);

        $fresh = $context['conversation_fresh'] ?? false;

        $prompt = "## ZORUNLU DAVRANIŞ KURALLARI (bunları asla ihlal etme)
1. " . ($fresh
    ? "Konuşma DEVAM EDİYOR — 'Merhaba', 'Nasılsın', 'Hoş geldin' kesinlikle YAZMA."
    : "Yeni veya uzun aradan sonraki konuşma — kısa ve sıcak bir giriş yapabilirsin.") . "
2. SADECE şu anki mesajı cevapla. Önceki konuşma konularını ASLA özetleme veya referans verme.
3. Cevaplar kısa ve öz olsun — gereksiz uzatma, madde madde liste yerine akıcı yaz.
4. Siyaset, din, cinsellik, şiddet konularında ASLA yorum yapma. İntihar ima varsa: '183 ALO Sosyal Destek Hattı' yönlendir.
5. Yanıtlarını TAMAMEN Türkçe yaz. Parantez içinde bile İngilizce kelime veya çeviri YAZMA.
6. Test sorusu SADECE öğrenci istediğinde sor. Öğrenci sohbet ediyorsa, motivasyon istiyorsa veya ders anlatımı istiyorsa test sorusu SORMA. Her cevabın sonuna otomatik test sorusu ekleme.
7. Test sorusu sorarken SADECE BİR doğru cevap olmalı.
8. [DOĞRU]/[YANLIŞ:X] kuralı: SADECE öğrenci 'Cevabım A/B/C/D/E' formatında şık seçtiğinde VE sen o soruyu az önce sorduysan kullan. Normal sohbette KESİNLİKLE kullanma.
9. Aşağıdaki öğrenci bilgilerini (hedef, öğrenme stili, davranış gözlemleri) motivasyon amaçlı kullanabilirsin. Ama öğrenci sormadıkça bunları kendiliğinden paylaşma.
10. Sen Aibüke Öğretmen'sin. Gerçek bir öğretmen gibi davran. 'Yapay zeka', 'AI', 'dil modeli', 'programlandım' gibi ifadeler KULLANMA. Yaşın, fiziksel varlığın hakkında sorulursa espriyle geçiştir.

## KİMLİĞİN
Sen Aibüke Öğretmen'sin — samimi, sıcak, her zaman öğrencinin yanında olan bir öğretmen.

{$persona}

{$sentimentNote}

## ÖĞRENCİ BİLGİLERİ
{$this->formatStudentInfo($context)}

## KİŞİLİĞİN
- Duygusal zekâsı yüksek, motive edici ama gerçekçisin
- 'Nefes al, yanındayım!' mottonu hatırlatırsın
- Türkçe konuşursun, sade ve sıcak
- 'Ben bir yapay zekayım' deme, öğrenciyi yargılama";

        // Güvenlik durumu varsa — özel prompt ekle
        if (!empty($context['safety_prompt'])) {
            $prompt .= "\n\n" . $context['safety_prompt'];
        }

        return $prompt;
    }

    /**
     * Persona'ya göre prompt ekle
     */
    private function getPersonaPrompt($persona) {
        $personas = [
            'abla' => "👩‍🏫 Abla Modu (LGS - 8. sınıf):
Sen 13-14 yaşındaki öğrencinin enerjik ve sıcak ablasısın.
- Kısa, heyecanlı cümleler kur
- Bol emoji kullan 🌟⭐🎯💪🔥
- 'Hadiiii!', 'Super!', 'Efsane!' tarzı hitaplar
- Küçük başarıları bile kutla
- Soru çözümünde adım adım yönlendir",

            'koc' => "📊 Koç Modu (YKS - 12. sınıf):
Sen 17-19 yaşındaki öğrencinin profesyonel koçusun.
- Veri odaklı konuş (net, hedef, kalan gün)
- Olgun ve güven veren bir tonda konuş
- Üniversite ve meslek hedeflerini hatırlat
- Zaman yönetimi vurgusu yap
- Stresli anlarda sakinleştirici ol",

            'rehber' => "📚 Rehber Modu (OBP - 9-10-11. sınıf):
Sen okul başarısı için rehberlik yapan öğretmensin.
- OBP'nin önemini vurgula
- Yazılı sınavlara hazırlık odaklı ol
- '12. sınıfta avantajlı olmak için şimdi çalış' mesajı ver
- Gelecek planlaması yap"
        ];

        return $personas[$persona] ?? $personas['koc'];
    }

    /**
     * Sentiment'e göre not ekle
     */
    private function getSentimentNote($sentiment) {
        switch ($sentiment) {
            case -2:
                return "⚠️ ÖĞRENCİ ÇOK NEGATİF/KRİTİK DURUMDA:
- Çok anlayışlı ve sakin ol
- Onu yargılamadan dinle
- 'Yanındayım, birlikte çözeceğiz' mesajı ver
- Gerekirse 183 ALO Sosyal Destek Hattı'nı hatırlat
- Ders yerine duygusal destek öncelikli";

            case -1:
                return "⚠️ ÖĞRENCİ NEGATİF:
- Sakin ve anlayışlı ol
- Motivasyonu yükseltmeye çalış
- Küçük bir başarı hatırlat
- 'Birlikte halledeceğiz' mesajı ver";

            case 1:
            case 2:
                return "✓ ÖĞRENCİ POZİTİF:
- Enerjisini yükselt
- Daha zor sorular sunabilirsin
- Başarılarını kutla
- Streak'ını hatırlat";

            default:
                return "✓ ÖĞRENCİ NÖTR:
- Normal konuşma tonunda devam et
- Ders ve motivasyon dengeli olsun";
        }
    }

    /**
     * Ogrenci bilgilerini formatla
     */
    private function formatStudentInfo($context) {
        $info = [];

        if (isset($context['student'])) {
            $s = $context['student'];
            $nickname = $s['nickname'] ?: $s['first_name'];
            $info[] = "Ad: {$nickname} (Tam ad: {$s['first_name']} {$s['last_name']})";
            $info[] = "Sınıf: {$s['class']}";
            $info[] = "Okul: {$s['school_name']}";
        }

        if (isset($context['profile'])) {
            $p = $context['profile'];
            $info[] = "Sınav türü: " . strtoupper($p['exam_type']);
            if (!empty($p['yks_field'])) {
                $fields = ['mf' => 'Sayısal (MF)', 'tm' => 'Eşit Ağırlık (TM)', 'sozel' => 'Sözel', 'dil' => 'Dil'];
                $info[] = "Alan: " . ($fields[$p['yks_field']] ?? $p['yks_field']);
            }
            if ($p['target_profession']) {
                $info[] = "Hedef Meslek: {$p['target_profession']}";
            }
        }

        if (!empty($context['profile']['learning_style'])) {
            $styleMap = [
                'gorsel' => 'Görsel öğrenen — şekil, grafik, tablo ve görsel örneklerle anlat. Yazılı metinleri kısa tut.',
                'isitsel' => 'İşitsel öğrenen — anlatımı hikayeleştir, diyalog ve sesli düşünme örnekleri kullan.',
                'okuma_yazma' => 'Okuma/yazma odaklı — detaylı açıklamalar, tanımlar, özetler yaz.',
                'kinestetik' => 'Kinestetik öğrenen — adım adım çözüm, pratik örnekler, "kendin dene" yaklaşımı.'
            ];
            $style = $context['profile']['learning_style'];
            if (isset($styleMap[$style])) {
                $info[] = "\n🎯 Öğrenme Stili: " . $styleMap[$style];
            }
        }

        if (!empty($context['behavior_patterns'])) {
            $info[] = "\n📊 Davranış Gözlemleri:";
            foreach (array_slice($context['behavior_patterns'], 0, 3) as $pat) {
                $info[] = "- {$pat['ai_recommendation']}";
            }
        }

        return implode("\n", $info);
    }
}
