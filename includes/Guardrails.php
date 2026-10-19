<?php
/**
 * Aibuke Ogretmen - Guvenli Bolge Filtresi (Guardrails)
 * 4 katmanli icerik guvenligi + guvenlik uyari sistemi
 */

require_once __DIR__ . '/badwords.php';

class Guardrails {

    private $badwords;
    private $pdo;
    private $blockedTopics = [
        // Siyaset
        'parti', 'cumhurbaskani', 'basbakan', 'secim', 'milletvekili',
        'chp', 'akp', 'mhp', 'iyi parti', 'dem parti', 'siyaset',
        // Din
        'allah', 'tanri', 'kuran', 'ince', 'cemaat', 'tarikat',
        // Siddet (sadece açık tehlikeli niyet)
        'bomba yap', 'bomba nasıl', 'silah yap', 'silah nasıl yapılır', 'saldırı planla',
        // Kisisel bilgi
        'tc kimlik', 'tc no', 'tc numarasi', 'adres', 'telefon numara'
    ];

    // Güvenlik anahtar kelimeleri — kategori bazlı
    private $safetyKeywords = [
        'kendine_zarar' => [
            'intihar', 'kendime zarar', 'kendimi öldür', 'kendimi oldur',
            'kendimi kesiyor', 'kendimi kesece', 'kendimi kestim',
            'yaşamak istemiyor', 'yasamak istemiyor',
            'ölmek istiyor', 'olmek istiyor', 'ölmek isterdim', 'olmek isterdim',
            'canıma kıy', 'canima kiy', 'canımdan bezdi', 'canimdan bezdi',
            'hayatıma son', 'hayatima son', 'ölsem daha iyi', 'olsem daha iyi',
            'yaşamın anlamı yok', 'yasamin anlami yok',
            'kendime kıy', 'kendime kiy',
            'artık dayanamıyor', 'artik dayanamiy',
            'her şey bitsin', 'her sey bitsin',
            'kimse umursamıyor', 'kimse umursamiyor',
            'kendimi yaral', 'kendimi yakac', 'kendimi asac',
            'hayattan bıktım', 'hayattan biktim'
        ],
        'siddet' => [
            'öldürmek istiyor', 'oldurmek istiyor',
            'öldüreceğ', 'oldureceg', 'öldürcem', 'oldurcem',
            'birini öldür', 'birini oldur', 'onu öldür', 'onu oldur',
            'zarar vereceğ', 'zarar vereceg',
            'bıçaklayac', 'bicaklayac', 'bıçaklıyac', 'bicakliyac',
            'vuracağ', 'vuracag', 'vurmak istiyor',
            'intikam al', 'hepsini öldür', 'hepsini oldur',
            'ona zarar ver', 'cezasını ver', 'cezasini ver',
            'kafasını kır', 'kafasini kir'
        ],
        'zarar_gorme' => [
            // Kök kalıplar — tüm çekimleri yakalar
            'bana zarar', 'beni döv', 'beni dov',
            'beni tehdit', 'canımı acıt', 'canimi acit',
            'bana vur', 'bana şiddet', 'bana siddet',
            'beni öldür', 'beni oldur',
            'bana dokun', 'taciz ed', 'istismar',
            'korkuyorum ondan', 'beni korkut', 'tehdit al',
            'beni incit', 'hayatım tehlike', 'hayatim tehlike',
            'beni yaral', 'beni yarali',
            'evde döv', 'evde dov',
            'babam döv', 'babam dov', 'babam vur',
            'annem döv', 'annem dov', 'annem vur',
            'abim döv', 'abim dov', 'abim vur',
            'dayak yi', 'dayak ye', 'dayak at', 'dayak je',
            'beni dövecek', 'beni dovecek', 'dövecekler', 'dovecekler',
            'beni öldürecek', 'beni oldurecek',
            'canıma kas', 'bana saldır', 'bana saldir',
            // Günlük öğrenci dili
            'kavga', 'kavga edecek', 'kavga çıkac', 'kavga cikac',
            'okul çıkışı bekli', 'okul cikisi bekli',
            'beni bekliyor', 'kapıda bekliyor', 'kapida bekliyor',
            'dövüş var', 'dovus var', 'dövüş olac', 'dovus olac',
            'bıçak çekti', 'bicak cekti', 'bıçak getir', 'bicak getir',
            'silah getir', 'tabanca', 'bıçakla gelec', 'bicakla gelec',
            'çete', 'cete', 'çeteye', 'ceteye',
            'mahalleli gelecek', 'abilerini çağır', 'abilerini cagir',
            'seni döveceğiz', 'seni dovecegiz', 'seni öldüreceğiz',
            'peşimde', 'pesimde', 'peşimi bırakmıyor', 'pesimi birakmiyor',
            'okulda güvende değil', 'okulda guvende degil',
            'öldürür', 'oldurur', 'öldürecek', 'oldurecek'
        ],
        'zorbalik' => [
            'zorbalık', 'zorbalik', 'zorba',
            'alay ediyor', 'alay ettiler',
            'dalga geçiyor', 'dalga geciyor', 'dalga geçtiler',
            'dışlıyor', 'disliyor', 'dışladılar',
            'beni itiyor', 'beni itiyorlar', 'beni ittiler',
            'beni döv', 'beni dov',
            'lakap takıyor', 'lakap takiyor', 'lakap taktılar',
            'küfür ediyor', 'kufur ediyor', 'küfür ettiler',
            'bana kötü davranıyor', 'bana kotu davraniyor',
            'kimse benimle konuşmuyor', 'kimse benimle konusmuyor',
            'arkadaşım yok', 'arkadasim yok', 'beni sevmiyor',
            'herkes benden nefret', 'yalnızım', 'yalnizim',
            'dışlanıyorum', 'dislaniyorum', 'tek başıma', 'tek basima',
            'sosyal medyada paylaş', 'ifşa', 'ifsa',
            'gruba almıyor', 'gruba almiyor',
            'eşyalarıma zarar', 'çantamı saklıyor', 'cantami sakliyor',
            'benimle dalga', 'bana gülüyorlar', 'bana guluyorlar',
            // Günlük öğrenci dili
            'beni dışlıyor', 'beni disliyor',
            'tehdit mesaj', 'tehdit ediyor', 'tehdit etti',
            'para isti', 'haraç', 'harac',
            'fotoğrafımı paylaş', 'fotografimi paylas',
            'videoma çek', 'videoma cek',
            'soyunma odasında', 'soyunma odasinda',
            'tuvalette sıkıştır', 'tuvalette sikistir',
            'topluca saldır', 'hep birlikte saldır',
            'sosyal medyada hedef', 'whatsapp grub'
        ]
    ];

    public function __construct($pdo = null) {
        $this->badwords = require __DIR__ . '/badwords.php';
        $this->pdo = $pdo;
    }

    /**
     * Ana filtreleme fonksiyonu
     * @return array ['blocked' => bool, 'reason' => string, 'redirect' => string, 'safety_alert' => array|null]
     */
    public function check($message) {
        // Katman 1: Keyword blacklist
        $result = $this->checkBadwords($message);
        if ($result['blocked']) return $result;

        // Katman 2: Konu filtresi
        $result = $this->checkTopics($message);
        if ($result['blocked']) return $result;

        // Katman 3: Kritik durum kontrolu (intihar/kendine zarar — acil)
        $result = $this->checkCritical($message);
        if ($result['blocked']) return $result;

        // Katman 4: Güvenlik uyarı taraması (bloklamaz, AI'a iletir)
        $safetyResult = $this->checkSafety($message);
        if ($safetyResult) {
            return [
                'blocked' => false,
                'reason' => '',
                'redirect' => '',
                'safety_alert' => $safetyResult
            ];
        }

        return ['blocked' => false, 'reason' => '', 'redirect' => '', 'safety_alert' => null];
    }

    /**
     * Katman 1: Kotu kelime kontrolu
     */
    private function checkBadwords($message) {
        $lower = mb_strtolower($message, 'UTF-8');

        foreach ($this->badwords as $word) {
            if (mb_strpos($lower, mb_strtolower($word, 'UTF-8')) !== false) {
                return [
                    'blocked' => true,
                    'reason' => 'Bu tarz ifadelere yanıt veremiyorum.',
                    'redirect' => 'Bu ifadeyle yardımcı olamıyorum, ama soruyu biraz farklı şekilde sorabilirsin. Örneğin tarih veya ders sorusuysa "... kimdir?", "... neden oldu?" gibi bir ifadeyle tekrar sorabilirsin. 😊'
                ];
            }
        }

        return ['blocked' => false];
    }

    /**
     * Katman 2: Yasakli konu kontrolu
     */
    private function checkTopics($message) {
        $lower = mb_strtolower($message, 'UTF-8');

        foreach ($this->blockedTopics as $topic) {
            if (mb_strpos($lower, $topic) !== false) {
                return [
                    'blocked' => true,
                    'reason' => 'Bu konuda yorum yapamıyorum.',
                    'redirect' => 'Bu konuda yardımcı olamıyorum ama gel ders sorularımıza bakalım! Hangi derste zorlanıyorsun?'
                ];
            }
        }

        return ['blocked' => false];
    }

    /**
     * Katman 3: Kritik durum kontrolu (intihar/kendine zarar — ACİL)
     * Bu mesajlar bloklanır ve doğrudan yönlendirme yapılır
     */
    private function checkCritical($message) {
        $lower = mb_strtolower($message, 'UTF-8');

        $critical = [
            'intihar', 'kendime zarar', 'kendimi öldüreyim', 'kendimi oldureyim',
            'yaşamak istemiyorum', 'yasamak istemiyorum',
            'bitirmek istiyorum hayatımı', 'canıma kıyayım', 'canima kiyayim',
            'canımdan bezdim', 'canimdan bezdim'
        ];

        foreach ($critical as $phrase) {
            if (mb_strpos($lower, $phrase) !== false) {
                return [
                    'blocked' => true,
                    'reason' => 'critical',
                    'redirect' => 'Kendini kötü hissediyorsan lütfen bir yetişkinle veya rehberlik öğretmeninle konuş. Acil durumlarda 183 ALO Sosyal Destek Hattı\'nı arayabilirsin. Ben her zaman yanındayım ama önce güvende olmalısın.',
                    'is_critical' => true,
                    'safety_alert' => [
                        'type' => 'kendine_zarar',
                        'severity' => 'kritik',
                        'matched' => $phrase
                    ]
                ];
            }
        }

        return ['blocked' => false];
    }

    /**
     * Katman 4: Güvenlik taraması (bloklamaz — AI'a iletilir)
     * Şiddet, zarar görme, zorbalık algılandığında AI dostça yaklaşır
     * ve aynı anda admin + veli bilgilendirilir
     */
    private function checkSafety($message) {
        $lower = mb_strtolower($message, 'UTF-8');
        // Türkçe karakter normalize
        $normalized = str_replace(
            ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'],
            ['i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'],
            $lower
        );

        foreach ($this->safetyKeywords as $type => $keywords) {
            foreach ($keywords as $keyword) {
                $keyNorm = str_replace(
                    ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'],
                    ['i', 'g', 'u', 's', 'o', 'c'],
                    mb_strtolower($keyword, 'UTF-8')
                );
                if (mb_strpos($normalized, $keyNorm) !== false) {
                    $severity = ($type === 'kendine_zarar' || $type === 'siddet') ? 'yuksek' : 'orta';
                    return [
                        'type' => $type,
                        'severity' => $severity,
                        'matched' => $keyword
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Güvenlik uyarısı oluştur ve DB'ye kaydet
     */
    public static function createSafetyAlert($pdo, $studentId, $schoolId, $alertType, $severity, $triggerMessage, $aiResponse = null, $conversationId = null) {
        $stmt = $pdo->prepare("
            INSERT INTO aibuke_safety_alerts
            (student_id, school_id, alert_type, severity, trigger_message, ai_response, conversation_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$studentId, $schoolId, $alertType, $severity, $triggerMessage, $aiResponse, $conversationId]);
        $alertId = $pdo->lastInsertId();

        // Admin bilgilendirme — sentiment_alerts tablosuna da ekle (admin panelinde görünsün)
        $typeLabels = [
            'kendine_zarar' => 'Kendine Zarar / İntihar İması',
            'siddet'        => 'Şiddet / Birini Öldürme İması',
            'zarar_gorme'   => 'Zarar Görme / Şiddete Maruz Kalma',
            'zorbalik'      => 'Zorbalık / Dışlanma',
            'kritik'        => 'Kritik Durum'
        ];
        $label = $typeLabels[$alertType] ?? $alertType;

        $stmt2 = $pdo->prepare("
            INSERT INTO aibuke_sentiment_alerts
            (student_id, school_id, alert_type, avg_score, trigger_messages, is_resolved, created_at)
            VALUES (?, ?, 'critical', -2.0, ?, 0, NOW())
        ");
        $triggerJson = json_encode([
            'safety_type' => $alertType,
            'label' => $label,
            'message' => mb_substr($triggerMessage, 0, 500),
            'safety_alert_id' => $alertId
        ], JSON_UNESCAPED_UNICODE);
        $stmt2->execute([$studentId, $schoolId, $triggerJson]);

        // Veli bilgilendirme — parent token varsa işaretle
        $stmt3 = $pdo->prepare("
            SELECT id FROM aibuke_parent_tokens
            WHERE student_id = ? AND is_active = 1 LIMIT 1
        ");
        $stmt3->execute([$studentId]);
        $parentToken = $stmt3->fetch(PDO::FETCH_ASSOC);

        if ($parentToken) {
            $pdo->prepare("UPDATE aibuke_safety_alerts SET is_notified_parent = 1 WHERE id = ?")
                ->execute([$alertId]);
        }

        // Admin bilgilendirildi olarak işaretle
        $pdo->prepare("UPDATE aibuke_safety_alerts SET is_notified_admin = 1 WHERE id = ?")
            ->execute([$alertId]);

        return $alertId;
    }

    /**
     * Güvenlik durumuna göre AI system prompt eki
     */
    public static function getSafetyPromptAddition($alertType) {
        $prompts = [
            'kendine_zarar' => "
⚠️ KRİTİK GÜVENLİK DURUMU — KENDİNE ZARAR
Öğrenci kendine zarar vermeyle ilgili bir şey söyledi. Şu şekilde davran:
- Sakin, sıcak ve şefkatli ol. Asla yargılama.
- 'Bunu benimle paylaştığın için teşekkür ederim, çok cesursun.' de.
- 'Şu an nasıl hissediyorsun? Ne oldu anlatmak ister misin?' diye sor.
- Rehberlik öğretmenine veya güvendiği bir yetişkine anlatmasını nazikçe öner.
- 183 ALO Sosyal Destek Hattı'nı hatırlat.
- 'Ben her zaman buradayım, yalnız değilsin' mesajı ver.
- Detayları öğrenmeye çalış: Ne zaman başladı? Sebebi ne? Kim var yanında?
- ASLA 'Boşver, geçer' gibi küçümseyen şeyler söyleme.",

            'siddet' => "
⚠️ GÜVENLİK DURUMU — ŞİDDET İMASI
Öğrenci birine zarar vermekten bahsetti. Şu şekilde davran:
- Sakin ol, panik yapma. Yargılama.
- 'Çok sinirli olduğunu anlıyorum. Neler oldu?' diye sor.
- Öfkenin nedenini anlamaya çalış: Kim bu kişi? Ne yaptı sana? Ne hissediyorsun?
- 'Sinirli olmak normal ama birini incitmek seni de çok üzer' mesajı ver.
- Öfkeyi yönetme yolları öner (nefes al, yürüyüşe çık, müzik dinle).
- 'Güvendiğin bir yetişkinle konuşmanı öneririm' de.
- Detayları topla: Kim? Neden? Ne oldu?",

            'zarar_gorme' => "
⚠️ KRİTİK GÜVENLİK DURUMU — ÇOCUĞA ZARAR VERİLİYOR
Öğrenci birisinin ona zarar verdiğini söylüyor. Bu çok ciddi. Şu şekilde davran:
- Çok sıcak ve güvenilir ol. 'Bunu söylediğin için çok cesursun, iyi ki anlattın.'
- 'Sana kim zarar veriyor?' — kimden bahsettiğini öğren.
- 'Bu ne zamandır oluyor?' — süresini öğren.
- 'Şu an güvende misin?' — acil durumu değerlendir.
- 'Bunu bir yetişkine söylemen çok önemli. Rehberlik öğretmenin, annen/baban veya güvendiğin biri olabilir.'
- 183 ALO Sosyal Destek Hattı'nı hatırlat.
- 'Senin hatanız değil, bunu bil' mesajını ver.
- ASLA 'Abartıyorsun', 'Normal bir şey' gibi küçümseyen şeyler söyleme.",

            'zorbalik' => "
⚠️ GÜVENLİK DURUMU — ZORBALIK
Öğrenci zorbalığa uğradığını söylüyor. Şu şekilde davran:
- Empati göster: 'Bu çok zor bir durum, seni anlıyorum.'
- 'Kimler yapıyor bunu? Neler oluyor tam olarak?' diye sor.
- 'Bu ne zamandır sürüyor?' — süresini öğren.
- 'Okulda kime söyledin?' — yetişkin desteği olup olmadığını öğren.
- 'Bunların hiçbiri senin hatan değil' mesajını ver.
- 'Rehberlik öğretmeninle konuşmanı çok öneririm, sana yardımcı olabilir' de.
- Arkadaşlık ve sosyal beceri konusunda cesaretlendir.
- 'Ben her zaman buradayım, istediğin zaman anlat' de."
        ];

        return $prompts[$alertType] ?? '';
    }

    /**
     * AI icin system prompt ekine guardrail kurallarini ekle
     */
    public function getSystemPromptAddition() {
        return "
GUVENLIK KURALLARI:
- Siyaset, din, cinsellik ve şiddet konularında ASLA yorum yapma
- Bu konularda soru gelirse nazikçe konuyu derse çevir
- Öğrenci kişisel bilgi paylaşıyorsa (TC, adres, telefon) uyar
- Asla tıbbi veya psikolojik tanı koyma
- İntihar/kendine zarar ima eden mesajlarda: 'Lütfen rehberlik öğretmeninle veya 183 ALO Sosyal Destek Hattı ile görüş' mesajı ver ve konuşmayı sakinleştir
- Öğrenci şiddet, zorbalık veya zarar görme konusunda açılırsa: SEVGİYLE DİNLE, bilgi topla (kim, ne zaman, nasıl), güvendiği yetişkine yönlendir
- Her zaman eğitim ve ders konularına yönlendir
";
    }
}
