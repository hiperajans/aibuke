<?php
/**
 * Aibuke Ogretmen - Mesaj Yonlendirici (Router)
 * Hangi AI modelinin kullanilacagini belirler
 */

require_once __DIR__ . '/GeminiAPI.php';
require_once __DIR__ . '/MinimaxAPI.php';

class Router {

    private $gemini;
    private $minimax;

    public function __construct() {
        $this->gemini = new GeminiAPI();
        $this->minimax = new MinimaxAPI();
    }

    /**
     * Mesaji hangi AI'a yonlendirecegini belirle
     * @return string 'gemini' | 'm2-her'
     */
    public function route($message) {
        $lower = mb_strtolower($message, 'UTF-8');

        // M2-her tetikleyicileri (motivasyon, duygu)
        $m2herTriggers = [
            'yapamiyorum', 'yapamıyorum', 'moralim', 'moral', 'motivasyon',
            'stres', 'panik', 'sikildim', 'biktim', 'bunaldim', 'bezdim',
            'yorgunum', 'yorgun', 'korkuyorum', 'korku', 'endişe',
            'basarisiz', 'başarısız', 'basaramadim', 'başaramadım',
            'yardim', 'yardım', 'destek', 'konusmak', 'sohbet',
            'nasilsin', 'nasılsın', 'ne haber', 'nbr', 'selam', 'hi',
            'ugra', 'usandim', 'bırakmak', 'bırakıyorum', 'vazgeçtim',
            // Duygu ifadeleri
            'ağlamak', 'aglamak', 'ağladım', 'agladım', 'kızgın', 'kizgin',
            'sinirli', 'sinir', 'off', 'of', 'hayat', 'yaşam'
        ];

        // Gemini tetikleyicileri (soru, ders, bilgi)
        $geminiTriggers = [
            'coz', 'çöz', 'cozdur', 'çözdür', 'soru', 'sorular',
            'matematik', 'fizik', 'kimya', 'biyoloji', 'turkce', 'türkçe',
            'tarih', 'cografya', 'coğrafya', 'felsefe', 'mantik', 'mantık',
            'ingilizce', 'ingilizce', 'fen', 'sosyal',
            'anlat', 'açıkla', 'acikla', 'nedir', 'nasil', 'nasıl',
            'formul', 'formül', 'denklem', 'denklemin', 'islem', 'işlem',
            'turev', 'türev', 'integral', 'limit', 'parabol', 'fonksiyon',
            'konu', 'anlatim', 'anlatım', 'ders', 'ogren', 'öğren',
            // Okul ile ilgili
            'sinav', 'sınav', 'deneme', 'yazili', 'yazılı',
            'ogretmen', 'öğretmen', 'okul', 'sinif', 'sınıf'
        ];

        // Fotograf kontrolu
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            return 'gemini'; // Fotograf varsa Gemini (gorsel analiz)
        }

        // Test/quiz üretme → M2.5 (açık pattern match, skordan önce)
        $testTriggers = [
            // test (yalın ve ekli haller)
            'test üret', 'test sor', 'test yap', 'test ver',
            'testi sor', 'testi yap', 'testi ver', 'testi üret',
            'bana test', 'bir test',
            // soru üretme
            'soru üret', 'soru ver', 'soru sor', 'soru sorabilir',
            'bana bir soru', 'bana soru',
            'ile ilgili soru', 'hakkında soru', 'konudan soru', 'konusundan soru',
            // quiz / çoktan seçmeli
            'quiz', 'çoktan seçmeli', 'sınav sorusu'
        ];
        foreach ($testTriggers as $t) {
            if (mb_strpos($lower, $t) !== false) {
                return 'm2-her';
            }
        }

        // Anahtar kelime eslesmeleri say
        $m2herScore = 0;
        $geminiScore = 0;

        foreach ($m2herTriggers as $trigger) {
            if (mb_strpos($lower, $trigger) !== false) {
                $m2herScore++;
            }
        }

        foreach ($geminiTriggers as $trigger) {
            if (mb_strpos($lower, $trigger) !== false) {
                $geminiScore++;
            }
        }

        // Skor hesapla
        // Duygu/pattern yogunlugu varsa M2-her
        // Soru/bilgi yogunlugu varsa Gemini
        if ($m2herScore > $geminiScore && $m2herScore > 0) {
            return 'm2-her';
        }

        // Varsayilan: Gemini (soru cozme asil gorev)
        return 'gemini';
    }

    /**
     * Mesaji ilgili AI'a gonder ve yaniti dondur
     */
    public function process($message, $context, $imageData = null, $sentiment = 0) {
        // Akıllı Kamera 2.0: Fotoğraf → Gemini OCR + konu tespiti + öneriler
        if ($imageData) {
            $smartPrompt = $message ?: 'Bu soruyu çöz ve adım adım açıkla.';
            $smartPrompt .= "\n\nEk olarak yanıtının SONUNA şu bilgileri ekle:
---
📌 KONU: [Bu sorunun ait olduğu konu/alt konu]
💡 ÖNERİ: Bu tipten 5 soru daha çözmek ister misin? 'Evet, devam' yaz!";

            $result = $this->gemini->analyzeImage($imageData, $smartPrompt, $context);
            $result['routed_to'] = 'gemini-vision';

            // Konu tespitini ayıkla ve kaydet (arka planda)
            if ($result['success'] && isset($result['text'])) {
                $detectedTopic = $this->extractTopic($result['text']);
                if ($detectedTopic) {
                    $result['detected_topic'] = $detectedTopic;
                }
            }

            return $result;
        }

        // Tüm metin mesajları Gemini'ye — tek model, tutarlı bağlam
        $result = $this->gemini->generateResponse($message, $context);
        $result['routed_to'] = 'gemini';
        return $result;
    }

    /**
     * Detayli route bilgisi (debug icin)
     */
    public function getRouteInfo($message) {
        $route = $this->route($message);

        return [
            'route' => $route,
            'reason' => $this->getRouteReason($message, $route)
        ];
    }

    /**
     * Akıllı Kamera 2.0: AI yanıtından konu tespit et
     */
    private function extractTopic($text) {
        // "📌 KONU:" satırından konu bilgisini çıkar
        if (preg_match('/📌\s*KONU:\s*(.+)/u', $text, $m)) {
            return trim($m[1]);
        }
        // Alternatif pattern
        if (preg_match('/Konu:\s*(.+)/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function getRouteReason($message, $route) {
        $lower = mb_strtolower($message, 'UTF-8');

        if ($route === 'm2-her') {
            return "Duygu/motivasyon odaklı içerik tespit edildi";
        }

        if ($route === 'gemini') {
            return "Soru/ders/bilgi odaklı içerik tespit edildi";
        }

        return "Varsayılan model seçildi";
    }
}
