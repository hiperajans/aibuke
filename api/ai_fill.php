<?php
/**
 * Aibuke Öğretmen - AI Evrak Doldurma Endpoint
 * POST /api/ai_fill.php
 * MiniMax M2.7 ile evrak içeriğini otomatik üretir
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ogretmen/auth.php';

session_start();
requireTeacherLogin();

header('Content-Type: application/json; charset=utf-8');

$ogretmen = getOgretmenInfo();
$type      = $_POST['type']   ?? '';
$tarih     = $_POST['tarih']  ?? date('d.m.Y');
$sinif     = $_POST['sinif']  ?? '';
$ders      = $_POST['ders']   ?? $ogretmen['ders'];
$extra     = $_POST['extra']  ?? '';
$kullanici = trim($_POST['prompt'] ?? ''); // öğretmenin yön verdiği bağlam

if (!$type) {
    http_response_code(400);
    echo json_encode(['error' => 'Evrak türü belirtilmedi']);
    exit;
}

$okul   = $ogretmen['school'];
$mudur  = $ogretmen['mudur_adi'] ?: 'Okul Müdürü';
$ogr    = $ogretmen['name'];

$prompt = buildPrompt($type, $tarih, $sinif, $ders, $okul, $mudur, $ogr, $extra, $kullanici);

if (!$prompt) {
    http_response_code(400);
    echo json_encode(['error' => 'Desteklenmeyen evrak türü: ' . $type]);
    exit;
}

$result = callMinimax($prompt);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(['error' => $result['error']]);
    exit;
}

// JSON parse et
$text = $result['text'];
// Markdown kod bloklarını temizle
$text = preg_replace('/^```json\s*/m', '', $text);
$text = preg_replace('/^```\s*$/m', '', $text);
$text = trim($text);

$data = json_decode($text, true);

if (!$data) {
    // JSON parse edilemezse ham metni dön
    echo json_encode(['success' => true, 'raw' => $text]);
    exit;
}

echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// -----------------------------------------------------------------------

function buildPrompt($type, $tarih, $sinif, $ders, $okul, $mudur, $ogr, $extra, $kullanici = '') {
    $base = "Tarih: $tarih | Okul: $okul | Müdür: $mudur | Öğretmen: $ogr | Ders: $ders | Sınıf: $sinif";
    if ($extra) $base .= " | Konu/Öğrenci: $extra";
    if ($kullanici) $base .= "\n\nÖğretmenin yönlendirmesi: \"$kullanici\"\nBu yönlendirmeyi içeriğe yansıt — gündem, kararlar ve açıklamalar bu konuyla ilgili olsun.";

    // Dönem ve akademik yılı tarihten çıkar
    $dateContext = "Tarihe göre dönem, akademik yıl ve toplantı türünü kendin belirle. Türkiye eğitim sisteminde Eylül-Ocak 1. dönem, Şubat-Haziran 2. dönemdir.";

    switch ($type) {

        case 'zumre':
            return "Sen resmi Türk eğitim belgesi uzmanısın. Aşağıdaki bilgilerle MEB formatında zümre toplantı tutanağı içeriği üret.
$base
$dateContext

JSON formatında dön (başka hiçbir metin yazma):
{
  \"akademik_yil\": \"2025-2026\",
  \"donem\": \"2. Dönem\",
  \"toplanti_turu\": \"Yıl Sonu Değerlendirme\",
  \"katilimcilar\": [\"Öğretmen adları listesi — en az 3 kişi\"],
  \"gundem\": [\"5 madde\"],
  \"kararlar\": [\"Her gündem için bir karar — somut ve ölçülebilir\"],
  \"uygunluk\": \"Yukarıda alınan kararlar zümre öğretmenlerince uygun bulunmuştur.\",
  \"imza_blogu\": \"$tarih tarihi itibarıyla imzalanmıştır.\\nOkul Müdürü: $mudur\"
}";

        case 'veli_toplanti':
            return "Sen resmi Türk eğitim belgesi uzmanısın. MEB formatında veli toplantı tutanağı üret.
$base
$dateContext

JSON formatında dön:
{
  \"akademik_yil\": \"2025-2026\",
  \"donem\": \"...\",
  \"toplanti_turu\": \"...\",
  \"katilan_veli_sayisi\": 28,
  \"gundem\": [\"5 madde\"],
  \"gorusmeler\": [\"Her gündem için velilerin görüşleri ve kararlar\"],
  \"alinan_kararlar\": [\"Somut kararlar\"],
  \"kapani\": \"Toplantı saat ... itibarıyla sona ermiştir.\",
  \"imza_blogu\": \"Sınıf Öğretmeni: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'bep':
            $ogrenci = $extra ?: 'Öğrenci Adı Soyadı';
            return "Sen özel eğitim uzmanısın. MEB BEP formatında bireyselleştirilmiş eğitim planı içeriği üret.
$base | Öğrenci: $ogrenci
$dateContext

JSON formatında dön:
{
  \"ogrenci\": \"$ogrenci\",
  \"sinif\": \"$sinif\",
  \"ogretmen\": \"$ogr\",
  \"akademik_yil\": \"2025-2026\",
  \"mevcut_performans\": \"Öğrencinin mevcut akademik ve sosyal gelişim düzeyi açıklaması\",
  \"uzun_donemli_hedefler\": [\"3 hedef\"],
  \"kisa_donemli_hedefler\": [\"Her uzun dönemli hedef için 2 kısa dönemli\"],
  \"etkinlikler\": [\"Hedeflere ulaşmak için planlanan etkinlikler\"],
  \"degerlendirme_yontemi\": \"Değerlendirme sıklığı ve yöntemi\",
  \"aile_katilimi\": \"Aile katılım planı\",
  \"baslangic_tarihi\": \"$tarih\",
  \"bitis_tarihi\": \"...\",
  \"imza_blogu\": \"Hazırlayan: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'iyep_matematik':
        case 'iyep_turkce':
            $dersAdi = ($type === 'iyep_matematik') ? 'Matematik' : 'Türkçe';
            $ogrenci = $extra ?: 'Öğrenci Adı Soyadı';
            return "Sen özel eğitim uzmanısın. MEB İYEP formatında $dersAdi destek planı üret.
$base | Ders: $dersAdi | Öğrenci: $ogrenci

JSON formatında dön:
{
  \"ogrenci\": \"$ogrenci\",
  \"ders\": \"$dersAdi\",
  \"sinif\": \"$sinif\",
  \"akademik_yil\": \"2025-2026\",
  \"mevcut_seviye\": \"Öğrencinin mevcut $dersAdi seviyesi\",
  \"hedef_kazanimlar\": [\"MEB müfredatına uygun 4-5 kazanım\"],
  \"etkinlikler\": [\"Her kazanım için öğretim etkinlikleri\"],
  \"materyal\": [\"Kullanılacak materyal ve araçlar\"],
  \"degerlendirme\": \"Değerlendirme yöntemi ve sıklığı\",
  \"sure\": \"8 hafta\",
  \"baslangic_tarihi\": \"$tarih\",
  \"imza_blogu\": \"Öğretmen: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'maarif_rapor':
            return "Sen okul yöneticisisin. MEB formatında aylık maarif raporu içeriği üret.
$base
Tarihten ay ve yılı belirle.

JSON formatında dön:
{
  \"ay\": \"...\",
  \"yil\": \"...\",
  \"akademik_yil\": \"2025-2026\",
  \"ogrenci_sayisi\": {\"kiz\": 0, \"erkek\": 0, \"toplam\": 0},
  \"devamsizlik\": {\"kiz\": 0, \"erkek\": 0, \"toplam\": 0},
  \"egitim_etkinlikleri\": [\"Bu ay gerçekleştirilen 3-4 etkinlik\"],
  \"sosyal_etkinlikler\": [\"2-3 etkinlik\"],
  \"veli_gorusmeleri\": \"Gerçekleştirilen veli görüşme sayısı ve konular\",
  \"ozel_durumlar\": \"Ayın öne çıkan konuları ve alınan önlemler\",
  \"genel_degerlendirme\": \"2-3 cümlelik genel değerlendirme\",
  \"imza_blogu\": \"Okul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'kulup':
            $kulupAdi = $extra ?: 'Okuma Kulübü';
            return "Sen okul kulübü danışman öğretmenisin. $kulupAdi için resmi kulüp kurulum/toplantı evrakı üret.
$base | Kulüp: $kulupAdi
$dateContext

JSON formatında dön:
{
  \"kulup_adi\": \"$kulupAdi\",
  \"danisman_ogretmen\": \"$ogr\",
  \"akademik_yil\": \"2025-2026\",
  \"amac\": \"Kulübün amacı ve hedefleri\",
  \"uyeler\": [\"Örnek 8-10 öğrenci adı\"],
  \"yillik_plan\": [\"Her ay için planlanan 1 etkinlik (Eylül-Haziran)\"],
  \"ic_tuzuk\": [\"5-6 madde kulüp iç tüzüğü\"],
  \"toplanti_gunu\": \"Her ayın ilk Cuma günü\",
  \"toplanti_kararlari\": [\"İlk toplantıda alınan 4-5 karar\"],
  \"imza_blogu\": \"Danışman Öğretmen: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'gunluk_plan':
            return "Sen deneyimli bir öğretmensin. MEB formatında günlük ders planı üret.
$base
$dateContext

JSON formatında dön:
{
  \"tarih\": \"$tarih\",
  \"ders\": \"$ders\",
  \"sinif\": \"$sinif\",
  \"sure\": \"40 dakika\",
  \"konu\": \"Derse uygun güncel konu\",
  \"kazanimlar\": [\"2-3 MEB kazanımı\"],
  \"on_hazirlik\": \"Ders öncesi hazırlık\",
  \"giris\": \"İlk 5 dakika giriş etkinliği\",
  \"gelistirme\": \"25 dakika ana etkinlik ve yöntem\",
  \"sonuc\": \"10 dakika özet ve değerlendirme\",
  \"materyal\": [\"Kullanılacak araç-gereç\"],
  \"odev\": \"Verilen ödev\",
  \"degerlendirme\": \"Ölçme-değerlendirme yöntemi\",
  \"imza_blogu\": \"Öğretmen: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'rehberlik':
            $ogrenci = $extra ?: 'Öğrenci Adı Soyadı';
            return "Sen okul psikolojik danışmanısın. Rehberlik görüşme formu üret.
$base | Öğrenci: $ogrenci

JSON formatında dön:
{
  \"ogrenci\": \"$ogrenci\",
  \"gorussme_tarihi\": \"$tarih\",
  \"gorussme_turu\": \"Bireysel Rehberlik\",
  \"gorusme_konusu\": \"Akademik ve sosyal gelişim değerlendirmesi\",
  \"ogrenci_ifadeleri\": \"Öğrencinin görüşme sırasındaki ifadeleri özeti\",
  \"gozlemler\": \"Rehber öğretmenin gözlemleri\",
  \"oneriler\": [\"3-4 somut öneri\"],
  \"takip_tarihi\": \"...\",
  \"aile_bilgilendirme\": \"Aile ile paylaşılacak bilgiler\",
  \"imza_blogu\": \"Rehber Öğretmen: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'performans':
            $ogrenci = $extra ?: 'Öğrenci Adı Soyadı';
            return "Sen öğretmensin. MEB formatında performans görevi değerlendirme formu üret.
$base | Öğrenci: $ogrenci

JSON formatında dön:
{
  \"ogrenci\": \"$ogrenci\",
  \"ders\": \"$ders\",
  \"sinif\": \"$sinif\",
  \"gorev_adi\": \"Derse uygun performans görevi adı\",
  \"kazanim\": \"İlgili MEB kazanımı\",
  \"gorev_aciklamasi\": \"Performans görevi detaylı açıklama\",
  \"sure\": \"2 hafta\",
  \"kriterler\": [
    {\"kriter\": \"İçerik\", \"puan\": 40},
    {\"kriter\": \"Sunum\", \"puan\": 30},
    {\"kriter\": \"Yaratıcılık\", \"puan\": 20},
    {\"kriter\": \"Zamanlama\", \"puan\": 10}
  ],
  \"alinan_puan\": 0,
  \"degerlendirme\": \"Genel değerlendirme notu\",
  \"imza_blogu\": \"Öğretmen: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'surec':
            $ogrenci = $extra ?: 'Öğrenci Adı Soyadı';
            return "Sen öğretmensin. MEB formatında süreç değerlendirme formu üret.
$base | Öğrenci: $ogrenci

JSON formatında dön:
{
  \"ogrenci\": \"$ogrenci\",
  \"ders\": \"$ders\",
  \"sinif\": \"$sinif\",
  \"surec_adi\": \"Proje/Performans/Ürün çalışması adı\",
  \"sure\": \"$tarih tarihinden itibaren 3 hafta\",
  \"amac\": \"Süreç değerlendirmesinin amacı\",
  \"gozlemler\": [\"Hafta hafta gözlem notları\"],
  \"guclü_yonler\": [\"Öğrencinin güçlü yönleri\"],
  \"gelistirilecek_yonler\": [\"Geliştirilmesi gereken yönler\"],
  \"puan\": 0,
  \"degerlendirme\": \"Genel süreç değerlendirmesi\",
  \"imza_blogu\": \"Öğretmen: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'aday_ogretmen':
            return "Sen okul yöneticisisin. Aday öğretmen değerlendirme dosyası içeriği üret.
$base
Aday Öğretmen: {$extra}

JSON formatında dön:
{
  \"aday_ogretmen\": \"$extra\",
  \"danisan_ogretmen\": \"$ogr\",
  \"okul\": \"$okul\",
  \"ders\": \"$ders\",
  \"donem\": \"...\",
  \"akademik_yil\": \"2025-2026\",
  \"gozlem_tarihleri\": [\"3 farklı gözlem tarihi\"],
  \"mesleki_yeterlilikler\": {
    \"alan_bilgisi\": \"Değerlendirme\",
    \"ogretim_teknikleri\": \"Değerlendirme\",
    \"sinif_yonetimi\": \"Değerlendirme\",
    \"ogrenci_iliskisi\": \"Değerlendirme\",
    \"mesleki_gelisim\": \"Değerlendirme\"
  },
  \"guclu_yonler\": [\"3 güçlü yön\"],
  \"gelistirilecek_yonler\": [\"2-3 geliştirilecek alan\"],
  \"genel_degerlendirme\": \"Aday öğretmenin genel değerlendirmesi\",
  \"sonuc\": \"Başarılı/Geliştirilmeli\",
  \"imza_blogu\": \"Danışman Öğretmen: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'okul_temelli':
            return "Sen MEB müfredatı uzmanı bir öğretmensin. Okul temelli planlama kaydı üret.
$base
$dateContext

JSON formatında dön:
{
  \"sinif\": \"$sinif\",
  \"donem\": \"2\",
  \"plan_turu\": \"lise_10\",
  \"konu\": \"Derse uygun konu başlığı\",
  \"konu_detay\": \"Konunun ayrıntılı açıklaması\",
  \"kazanimlar\": \"MEB müfredatından ilgili kazanımlar\",
  \"etkinlikler\": \"Uygulanan etkinlikler ve yöntemler\",
  \"sure_dk\": 40,
  \"degerlendirme\": \"Etkinlik sonrası değerlendirme\",
  \"imza_blogu\": \"Öğretmen: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'bep_kaba':
            $ogrenci = $extra ?: 'Öğrenci Adı Soyadı';
            return "Sen özel eğitim uzmanısın. MEB formatında BEP Kaba Değerlendirme Formu üret.
$base | Öğrenci: $ogrenci

JSON formatında dön:
{
  \"ogrenci\": \"$ogrenci\",
  \"sinif\": \"$sinif\",
  \"ogretmen\": \"$ogr\",
  \"degerlendirme_tarihi\": \"$tarih\",
  \"form_turu\": \"Kaba Değerlendirme\",
  \"gelistirme_alanlari\": [\"Akademik\", \"Sosyal-Duygusal\", \"Öz Bakım\", \"Motor Gelişim\"],
  \"mevcut_durum\": \"Öğrencinin mevcut performans düzeyi açıklaması\",
  \"guclü_yonler\": [\"3-4 güçlü yön\"],
  \"destek_gereken_alanlar\": [\"3-4 alan\"],
  \"oncelikli_hedefler\": [\"2-3 öncelikli hedef\"],
  \"form_icerik\": \"Kaba değerlendirme özet raporu\",
  \"genel_notlar\": \"Genel gözlem ve notlar\",
  \"imza_blogu\": \"Öğretmen: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'ders_ici':
            $ogrenci = $extra ?: 'Öğrenci Adı Soyadı';
            return "Sen öğretmensin. Ders içi değerlendirme formu üret.
$base | Öğrenci: $ogrenci

JSON formatında dön:
{
  \"ogrenci\": \"$ogrenci\",
  \"ders\": \"$ders\",
  \"konu\": \"Değerlendirilen konu\",
  \"tarih\": \"$tarih\",
  \"tur\": \"soru_cevap\",
  \"aciklama\": \"Öğrencinin ders içi performansının değerlendirilmesi — katılım, doğru yanıt oranı, sınıf içi davranış\",
  \"puan\": 85,
  \"imza_blogu\": \"Öğretmen: $ogr\\nTarih: $tarih\"
}";

        case 'egzersiz':
            return "Sen beden eğitimi veya sınıf öğretmenisin. Egzersiz planı üret.
$base

JSON formatında dön:
{
  \"egzersiz_adi\": \"Egzersiz planı adı\",
  \"kategori\": \"kuvvet\",
  \"sinif\": \"$sinif\",
  \"sure_dk\": 40,
  \"hedefler\": \"Egzersizin hedefleri ve beklenen çıktılar\",
  \"materyaller\": \"Gerekli materyal ve araçlar\",
  \"aciklama\": \"Egzersizin adım adım uygulanması\",
  \"imza_blogu\": \"Öğretmen: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'kazanim':
            $ogrenci = $extra ?: 'Öğrenci Adı Soyadı';
            return "Sen öğretmensin. MEB kazanım değerlendirme formu üret.
$base | Öğrenci: $ogrenci

JSON formatında dön:
{
  \"ogrenci\": \"$ogrenci\",
  \"ders\": \"$ders\",
  \"kazanim_kodu\": \"Derse uygun MEB kazanım kodu (örn: M.5.1.2)\",
  \"kazanim_aciklama\": \"Kazanımın tam açıklaması\",
  \"degerlendirme_tarihi\": \"$tarih\",
  \"puan\": 80,
  \"gozlem\": \"Öğrencinin kazanımla ilgili gözlemlenen davranışları\",
  \"notlar\": \"Ek notlar ve öneriler\",
  \"imza_blogu\": \"Öğretmen: $ogr\\nTarih: $tarih\"
}";

        case 'nobet':
            return "Sen okul yöneticisisin. Haftalık nöbet listesi üret.
$base
Hafta başlangıcı: $tarih

JSON formatında dön:
{
  \"hafta_baslangic\": \"$tarih\",
  \"hafta_bitis\": \"Tarihten 4 gün sonrası\",
  \"nobetci_liste\": \"Pazartesi: Öğretmen 1\\nSalı: Öğretmen 2\\nÇarşamba: $ogr\\nPerşembe: Öğretmen 3\\nCuma: Öğretmen 4\",
  \"genel_not\": \"Nöbet yeri: Ana giriş ve koridorlar. Nöbet saatleri: 08:00-17:00.\",
  \"imza_blogu\": \"Okul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'proje':
            $ogrenci = $extra ?: 'Öğrenci Adı Soyadı';
            return "Sen öğretmensin. Öğrenci proje değerlendirme formu üret.
$base | Öğrenci: $ogrenci

JSON formatında dön:
{
  \"ogrenci\": \"$ogrenci\",
  \"ders\": \"$ders\",
  \"proje_adi\": \"Derse uygun proje adı\",
  \"durum\": \"devam_ediyor\",
  \"baslama_tarihi\": \"$tarih\",
  \"bitis_tarihi\": \"3 hafta sonrası\",
  \"aciklama\": \"Projenin amacı, kapsamı ve beklenen çıktılar\",
  \"degerlendirme_kriterleri\": [\"İçerik kalitesi\", \"Sunum becerisi\", \"Özgünlük\", \"Zamanında teslim\"],
  \"puan\": 0,
  \"imza_blogu\": \"Öğretmen: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        case 'urun_dosyasi':
            $ogrenci = $extra ?: 'Öğrenci Adı Soyadı';
            return "Sen öğretmensin. Öğrenci ürün dosyası değerlendirme kaydı üret.
$base | Öğrenci: $ogrenci

JSON formatında dön:
{
  \"ogrenci\": \"$ogrenci\",
  \"ders\": \"$ders\",
  \"dosya_adi\": \"Derse uygun ürün/çalışma adı\",
  \"kategori\": \"yazili_calısma\",
  \"tarih\": \"$tarih\",
  \"puan\": 85,
  \"aciklama\": \"Ürünün değerlendirilmesi — içerik, sunum, özgünlük, eksiksizlik açısından\",
  \"imza_blogu\": \"Öğretmen: $ogr\\nTarih: $tarih\"
}";

        case 'yillik_plan':
            return "Sen MEB müfredatı uzmanısın. $ders dersi için yıllık plan özeti üret.
$base
$dateContext

JSON formatında dön:
{
  \"ders\": \"$ders\",
  \"sinif\": \"$sinif\",
  \"akademik_yil\": \"2025-2026\",
  \"haftalik_saat\": 4,
  \"donem1_konular\": [\"Eylül-Ocak arası 8-10 konu başlığı\"],
  \"donem2_konular\": [\"Şubat-Haziran arası 8-10 konu başlığı\"],
  \"yontemler\": [\"Kullanılacak öğretim yöntemleri\"],
  \"olcme_degerlendirme\": \"Kullanılacak ölçme araçları\",
  \"kaynaklar\": [\"Ders kitabı ve yardımcı kaynaklar\"],
  \"imza_blogu\": \"Hazırlayan: $ogr\\nOkul Müdürü: $mudur\\nTarih: $tarih\"
}";

        default:
            return null;
    }
}

function callMinimax($prompt) {
    $apiKey = MINIMAX_API_KEY;
    $url    = 'https://api.minimax.io/v1/text/chatcompletion_v2';

    $data = [
        'model'       => MINIMAX_MODEL,
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.3,
        'max_tokens'  => 8192,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => json_encode($data),
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT       => 60,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "MiniMax HTTP $httpCode"];
    }

    $result = json_decode($response, true);
    $text   = $result['choices'][0]['message']['content'] ?? null;

    if (!$text) {
        return ['success' => false, 'error' => 'MiniMax boş yanıt döndü'];
    }

    return ['success' => true, 'text' => $text];
}
