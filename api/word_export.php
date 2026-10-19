<?php
/**
 * Aibuke Öğretmen - Word Belge İndirme Endpoint
 * POST /api/word_export.php
 * PhpWord ile .docx üretir ve indirir
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ogretmen/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

session_start();
requireTeacherLogin();

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

$ogretmen = getOgretmenInfo();
$type     = $_POST['type'] ?? '';
$jsonData = $_POST['data'] ?? '';

if (!$type || !$jsonData) {
    http_response_code(400);
    echo json_encode(['error' => 'Eksik parametre']);
    exit;
}

$data = json_decode($jsonData, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz veri']);
    exit;
}

$phpWord  = new PhpWord();
$phpWord->setDefaultFontName('Times New Roman');
$phpWord->setDefaultFontSize(12);

// Sayfa ayarları (A4)
$sectionStyle = [
    'marginTop'    => 720,
    'marginBottom' => 720,
    'marginLeft'   => 1080,
    'marginRight'  => 1080,
];
$section = $phpWord->addSection($sectionStyle);

// Stil tanımları
$phpWord->addTitleStyle(1, ['bold' => true, 'size' => 14, 'name' => 'Times New Roman'], ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);
$phpWord->addTitleStyle(2, ['bold' => true, 'size' => 12, 'name' => 'Times New Roman'], ['spaceAfter' => 120]);

$boldStyle     = ['bold' => true, 'name' => 'Times New Roman', 'size' => 12];
$normalStyle   = ['name' => 'Times New Roman', 'size' => 12];
$muted         = ['name' => 'Times New Roman', 'size' => 11, 'color' => '555555'];
$centerPara    = ['alignment' => Jc::CENTER, 'spaceAfter' => 120];
$normalPara    = ['spaceAfter' => 100];
$indentPara    = ['spaceAfter' => 80, 'indentation' => ['left' => 360]];

$okul  = $ogretmen['school'];
$mudur = $ogretmen['mudur_adi'] ?: 'Okul Müdürü';
$ogr   = $ogretmen['name'];

// -----------------------------------------------------------------------
// EVRAK TÜRÜNE GÖRE BELGE OLUŞTUR
// -----------------------------------------------------------------------

switch ($type) {

    case 'zumre':
        addZumre($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'zumre_tutanagi';
        break;

    case 'veli_toplanti':
        addVeliToplanti($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'veli_toplanti_tutanagi';
        break;

    case 'bep':
        addBep($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'bep_plani';
        break;

    case 'iyep_matematik':
    case 'iyep_turkce':
        addIyep($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'iyep_plani';
        break;

    case 'maarif_rapor':
        addMaarifRapor($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'maarif_aylik_rapor';
        break;

    case 'kulup':
        addKulup($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'kulup_evraklari';
        break;

    case 'gunluk_plan':
        addGunlukPlan($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'gunluk_plan';
        break;

    case 'rehberlik':
        addRehberlik($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'rehberlik_gorussme';
        break;

    case 'performans':
        addPerformans($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'performans_gorevi';
        break;

    case 'surec':
        addSurec($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'surec_degerlendirme';
        break;

    case 'aday_ogretmen':
        addAdayOgretmen($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'aday_ogretmen_dosyasi';
        break;

    case 'okul_temelli':
        addOkulTemelli($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'okul_temelli_planlama';
        break;

    case 'bep_kaba':
        addBepKaba($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'bep_kaba_degerlendirme';
        break;

    case 'ders_ici':
        addDersIci($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'ders_ici_degerlendirme';
        break;

    case 'egzersiz':
        addEgzersiz($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'egzersiz_plani';
        break;

    case 'kazanim':
        addKazanim($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'kazanim_degerlendirme';
        break;

    case 'nobet':
        addNobet($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'nobet_listesi';
        break;

    case 'proje':
        addProje($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'proje_degerlendirme';
        break;

    case 'urun_dosyasi':
        addUrunDosyasi($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'urun_dosyasi';
        break;

    case 'yillik_plan':
        addYillikPlan($section, $data, $okul, $mudur, $ogr, $boldStyle, $normalStyle, $muted, $centerPara, $normalPara, $indentPara);
        $filename = 'yillik_plan';
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Desteklenmeyen evrak türü']);
        exit;
}

// İndir
$tarihStr = date('Ymd_His');
$dlName   = $filename . '_' . $tarihStr . '.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $dlName . '"');
header('Cache-Control: max-age=0');

$writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;

// -----------------------------------------------------------------------
// YARDIMCI FONKSİYONLAR
// -----------------------------------------------------------------------

function addOkulBaslik($section, $okul, $baslik, $altBaslik, $boldStyle, $centerPara) {
    $p = $section->addTextRun($centerPara);
    $p->addText($okul, $boldStyle);
    $section->addText($baslik, ['bold' => true, 'size' => 14, 'name' => 'Times New Roman'], $centerPara);
    if ($altBaslik) {
        $section->addText($altBaslik, ['size' => 12, 'name' => 'Times New Roman'], $centerPara);
    }
    $section->addTextBreak(1);
}

function addAlan($section, $etiket, $deger, $boldStyle, $normalStyle, $normalPara) {
    $p = $section->addTextRun($normalPara);
    $p->addText($etiket . ': ', $boldStyle);
    $p->addText($deger, $normalStyle);
}

function addMaddeler($section, $baslik, $maddeler, $boldStyle, $normalStyle, $normalPara, $indentPara) {
    $section->addText($baslik, $boldStyle, $normalPara);
    foreach ($maddeler as $i => $madde) {
        $section->addText(($i + 1) . '. ' . $madde, $normalStyle, $indentPara);
    }
    $section->addTextBreak(1);
}

function addImzaBlogu($section, $imzaMetni, $boldStyle, $normalStyle) {
    $section->addTextBreak(2);
    $lines = explode("\n", $imzaMetni);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line) {
            $section->addText($line, $normalStyle, ['spaceAfter' => 80]);
        }
    }
}

// -----------------------------------------------------------------------
// EVRAK FONKSİYONLARI
// -----------------------------------------------------------------------

function addZumre($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    $tur   = $d['toplanti_turu'] ?? 'Zümre Toplantısı';
    $donem = $d['donem'] ?? '';
    $ay    = $d['akademik_yil'] ?? '';
    addOkulBaslik($s, $okul, 'ZÜMRE ÖĞRETMENLERİ TOPLANTI TUTANAĞI', "$ay — $donem — $tur", $bold, $center);
    addAlan($s, 'Hazırlayan Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Toplantı Tarihi', $d['tarih'] ?? date('d.m.Y'), $bold, $normal, $np);
    if (!empty($d['katilimcilar'])) addAlan($s, 'Katılımcılar', implode(', ', $d['katilimcilar']), $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['gundem'])) addMaddeler($s, 'GÜNDEM MADDELERİ', $d['gundem'], $bold, $normal, $np, $ip);
    if (!empty($d['kararlar'])) addMaddeler($s, 'ALINAN KARARLAR', $d['kararlar'], $bold, $normal, $np, $ip);
    if (!empty($d['uygunluk'])) $s->addText($d['uygunluk'], $normal, $np);
    addImzaBlogu($s, "Öğretmen: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addVeliToplanti($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    $donem = $d['donem'] ?? '';
    $ay    = $d['akademik_yil'] ?? '';
    addOkulBaslik($s, $okul, 'VELİ TOPLANTI TUTANAĞI', "$ay — $donem", $bold, $center);
    addAlan($s, 'Sınıf Öğretmeni', $ogr, $bold, $normal, $np);
    addAlan($s, 'Toplantı Tarihi', $d['toplanti_tarihi'] ?? date('d.m.Y'), $bold, $normal, $np);
    addAlan($s, 'Toplantı Türü', $d['toplanti_turu'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Katılan Veli Sayısı', (string)($d['katilan_veli_sayisi'] ?? ''), $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['gundem'])) addMaddeler($s, 'GÜNDEM', $d['gundem'], $bold, $normal, $np, $ip);
    if (!empty($d['gorusmeler'])) addMaddeler($s, 'VELİ GÖRÜŞLERİ', $d['gorusmeler'], $bold, $normal, $np, $ip);
    if (!empty($d['alinan_kararlar'])) addMaddeler($s, 'ALINAN KARARLAR', $d['alinan_kararlar'], $bold, $normal, $np, $ip);
    if (!empty($d['kapani'])) $s->addText($d['kapani'], $normal, $np);
    addImzaBlogu($s, "Sınıf Öğretmeni: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addBep($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'BİREYSELLEŞTİRİLMİŞ EĞİTİM PLANI (BEP)', $d['akademik_yil'] ?? '', $bold, $center);
    addAlan($s, 'Hazırlayan Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Öğrenci', $d['ogrenci'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Sınıf', $d['sinif'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Başlangıç Tarihi', $d['baslangic_tarihi'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Bitiş Tarihi', $d['bitis_tarihi'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['mevcut_performans'])) { $s->addText('MEVCUT PERFORMANS', $bold, $np); $s->addText($d['mevcut_performans'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['uzun_donemli_hedefler'])) addMaddeler($s, 'UZUN DÖNEMLİ HEDEFLER', $d['uzun_donemli_hedefler'], $bold, $normal, $np, $ip);
    if (!empty($d['kisa_donemli_hedefler'])) addMaddeler($s, 'KISA DÖNEMLİ HEDEFLER', $d['kisa_donemli_hedefler'], $bold, $normal, $np, $ip);
    if (!empty($d['etkinlikler'])) addMaddeler($s, 'ETKİNLİKLER', $d['etkinlikler'], $bold, $normal, $np, $ip);
    if (!empty($d['degerlendirme_yontemi'])) addAlan($s, 'Değerlendirme Yöntemi', $d['degerlendirme_yontemi'], $bold, $normal, $np);
    if (!empty($d['aile_katilimi'])) addAlan($s, 'Aile Katılımı', $d['aile_katilimi'], $bold, $normal, $np);
    addImzaBlogu($s, "Hazırlayan: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addIyep($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    $ders = $d['ders'] ?? 'İYEP';
    addOkulBaslik($s, $okul, "İYEP — $ders DESTEK PLANI", $d['akademik_yil'] ?? '', $bold, $center);
    addAlan($s, 'Hazırlayan Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Öğrenci', $d['ogrenci'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Sınıf', $d['sinif'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Süre', $d['sure'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Başlangıç Tarihi', $d['baslangic_tarihi'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['mevcut_seviye'])) { $s->addText('MEVCUT SEVİYE', $bold, $np); $s->addText($d['mevcut_seviye'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['hedef_kazanimlar'])) addMaddeler($s, 'HEDEF KAZANIMLAR', $d['hedef_kazanimlar'], $bold, $normal, $np, $ip);
    if (!empty($d['etkinlikler'])) addMaddeler($s, 'ETKİNLİKLER', $d['etkinlikler'], $bold, $normal, $np, $ip);
    if (!empty($d['materyal'])) addMaddeler($s, 'MATERYAL VE ARAÇLAR', $d['materyal'], $bold, $normal, $np, $ip);
    if (!empty($d['degerlendirme'])) addAlan($s, 'Değerlendirme', $d['degerlendirme'], $bold, $normal, $np);
    addImzaBlogu($s, "Hazırlayan: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addMaarifRapor($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'AYLIK MAARİF RAPORU', ($d['ay'] ?? '') . ' ' . ($d['yil'] ?? ''), $bold, $center);
    addAlan($s, 'Hazırlayan', $ogr, $bold, $normal, $np);
    $ogrSayisi = $d['ogrenci_sayisi'] ?? [];
    addAlan($s, 'Öğrenci Sayısı', 'Kız: ' . ($ogrSayisi['kiz'] ?? 0) . ' / Erkek: ' . ($ogrSayisi['erkek'] ?? 0) . ' / Toplam: ' . ($ogrSayisi['toplam'] ?? 0), $bold, $normal, $np);
    $devamsizlik = $d['devamsizlik'] ?? [];
    addAlan($s, 'Devamsızlık', 'Kız: ' . ($devamsizlik['kiz'] ?? 0) . ' / Erkek: ' . ($devamsizlik['erkek'] ?? 0), $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['egitim_etkinlikleri'])) addMaddeler($s, 'EĞİTİM ETKİNLİKLERİ', $d['egitim_etkinlikleri'], $bold, $normal, $np, $ip);
    if (!empty($d['sosyal_etkinlikler'])) addMaddeler($s, 'SOSYAL ETKİNLİKLER', $d['sosyal_etkinlikler'], $bold, $normal, $np, $ip);
    if (!empty($d['veli_gorusmeleri'])) addAlan($s, 'Veli Görüşmeleri', $d['veli_gorusmeleri'], $bold, $normal, $np);
    if (!empty($d['ozel_durumlar'])) { $s->addTextBreak(1); $s->addText('ÖZEL DURUMLAR', $bold, $np); $s->addText($d['ozel_durumlar'], $normal, $ip); }
    if (!empty($d['genel_degerlendirme'])) { $s->addTextBreak(1); $s->addText('GENEL DEĞERLENDİRME', $bold, $np); $s->addText($d['genel_degerlendirme'], $normal, $ip); }
    addImzaBlogu($s, "Hazırlayan: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addKulup($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, strtoupper($d['kulup_adi'] ?? 'KULÜP') . ' EVRAKLARI', $d['akademik_yil'] ?? '', $bold, $center);
    addAlan($s, 'Danışman Öğretmen', $ogr, $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['amac'])) { $s->addText('KULÜP AMACI', $bold, $np); $s->addText($d['amac'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['uyeler'])) addMaddeler($s, 'ÜYE LİSTESİ', $d['uyeler'], $bold, $normal, $np, $ip);
    if (!empty($d['ic_tuzuk'])) addMaddeler($s, 'İÇ TÜZÜK', $d['ic_tuzuk'], $bold, $normal, $np, $ip);
    if (!empty($d['yillik_plan'])) addMaddeler($s, 'YILLIK ETKİNLİK PLANI', $d['yillik_plan'], $bold, $normal, $np, $ip);
    if (!empty($d['toplanti_kararlari'])) addMaddeler($s, 'TOPLANTI KARARLARI', $d['toplanti_kararlari'], $bold, $normal, $np, $ip);
    addImzaBlogu($s, "Danışman Öğretmen: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addGunlukPlan($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'GÜNLÜK DERS PLANI', ($d['ders'] ?? '') . ' — ' . ($d['tarih'] ?? ''), $bold, $center);
    addAlan($s, 'Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Sınıf', $d['sinif'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Süre', $d['sure'] ?? '40 dakika', $bold, $normal, $np);
    addAlan($s, 'Konu', $d['konu'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['kazanimlar'])) addMaddeler($s, 'KAZANIMLAR', $d['kazanimlar'], $bold, $normal, $np, $ip);
    if (!empty($d['materyal'])) addMaddeler($s, 'MATERYAL VE ARAÇLAR', $d['materyal'], $bold, $normal, $np, $ip);
    foreach (['on_hazirlik' => 'ÖN HAZIRLIK', 'giris' => 'GİRİŞ', 'gelistirme' => 'GELİŞTİRME', 'sonuc' => 'SONUÇ'] as $key => $label) {
        if (!empty($d[$key])) { $s->addText($label, $bold, $np); $s->addText($d[$key], $normal, $ip); $s->addTextBreak(1); }
    }
    if (!empty($d['odev'])) addAlan($s, 'Ödev', $d['odev'], $bold, $normal, $np);
    if (!empty($d['degerlendirme'])) addAlan($s, 'Değerlendirme', $d['degerlendirme'], $bold, $normal, $np);
    addImzaBlogu($s, "Öğretmen: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addRehberlik($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'REHBERLİK GÖRÜŞME FORMU', $d['gorussme_tarihi'] ?? '', $bold, $center);
    addAlan($s, 'Görüşmeyi Yapan', $ogr, $bold, $normal, $np);
    addAlan($s, 'Öğrenci', $d['ogrenci'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Görüşme Türü', $d['gorussme_turu'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Konu', $d['gorusme_konusu'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['ogrenci_ifadeleri'])) { $s->addText('ÖĞRENCİ İFADELERİ', $bold, $np); $s->addText($d['ogrenci_ifadeleri'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['gozlemler'])) { $s->addText('GÖZLEMLER', $bold, $np); $s->addText($d['gozlemler'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['oneriler'])) addMaddeler($s, 'ÖNERİLER', $d['oneriler'], $bold, $normal, $np, $ip);
    if (!empty($d['takip_tarihi'])) addAlan($s, 'Takip Tarihi', $d['takip_tarihi'], $bold, $normal, $np);
    if (!empty($d['aile_bilgilendirme'])) addAlan($s, 'Aile Bilgilendirme', $d['aile_bilgilendirme'], $bold, $normal, $np);
    addImzaBlogu($s, "Rehber Öğretmen: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addPerformans($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'PERFORMANS GÖREVİ DEĞERLENDİRME FORMU', ($d['ders'] ?? '') . ' — ' . ($d['sinif'] ?? ''), $bold, $center);
    addAlan($s, 'Değerlendiren Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Öğrenci', $d['ogrenci'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Görev Adı', $d['gorev_adi'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Kazanım', $d['kazanim'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Süre', $d['sure'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['gorev_aciklamasi'])) { $s->addText('GÖREV AÇIKLAMASI', $bold, $np); $s->addText($d['gorev_aciklamasi'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['kriterler'])) {
        $s->addText('DEĞERLENDİRME KRİTERLERİ', $bold, $np);
        foreach ($d['kriterler'] as $k) {
            $p = $s->addTextRun($ip);
            $p->addText($k['kriter'] ?? '', $normal);
            $p->addText(' — ' . ($k['puan'] ?? '') . ' puan', $muted);
        }
        $s->addTextBreak(1);
    }
    addAlan($s, 'Alınan Puan', (string)($d['alinan_puan'] ?? ''), $bold, $normal, $np);
    if (!empty($d['degerlendirme'])) addAlan($s, 'Değerlendirme', $d['degerlendirme'], $bold, $normal, $np);
    addImzaBlogu($s, "Öğretmen: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addSurec($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'SÜREÇ DEĞERLENDİRME FORMU', ($d['ders'] ?? '') . ' — ' . ($d['sinif'] ?? ''), $bold, $center);
    addAlan($s, 'Değerlendiren Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Öğrenci', $d['ogrenci'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Süreç Adı', $d['surec_adi'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Süre', $d['sure'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Amaç', $d['amac'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['gozlemler'])) addMaddeler($s, 'HAFTALIK GÖZLEMLER', $d['gozlemler'], $bold, $normal, $np, $ip);
    if (!empty($d['guclü_yonler'])) addMaddeler($s, 'GÜÇLÜ YÖNLER', $d['guclü_yonler'] ?? $d['guclu_yonler'] ?? [], $bold, $normal, $np, $ip);
    if (!empty($d['gelistirilecek_yonler'])) addMaddeler($s, 'GELİŞTİRİLECEK YÖNLER', $d['gelistirilecek_yonler'], $bold, $normal, $np, $ip);
    addAlan($s, 'Puan', (string)($d['puan'] ?? ''), $bold, $normal, $np);
    if (!empty($d['degerlendirme'])) addAlan($s, 'Değerlendirme', $d['degerlendirme'], $bold, $normal, $np);
    addImzaBlogu($s, "Öğretmen: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addAdayOgretmen($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'ADAY ÖĞRETMEN DEĞERLENDİRME DOSYASI', $d['akademik_yil'] ?? '', $bold, $center);
    addAlan($s, 'Danışman Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Aday Öğretmen', $d['aday_ogretmen'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Branş', $d['ders'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['mesleki_yeterlilikler'])) {
        $s->addText('MESLEKİ YETERLİLİKLER', $bold, $np);
        foreach ($d['mesleki_yeterlilikler'] as $alan => $deg) {
            $p = $s->addTextRun($ip);
            $p->addText(ucfirst(str_replace('_', ' ', $alan)) . ': ', $bold);
            $p->addText($deg, $normal);
        }
        $s->addTextBreak(1);
    }
    if (!empty($d['guclu_yonler'])) addMaddeler($s, 'GÜÇLÜ YÖNLER', $d['guclu_yonler'], $bold, $normal, $np, $ip);
    if (!empty($d['gelistirilecek_yonler'])) addMaddeler($s, 'GELİŞTİRİLECEK YÖNLER', $d['gelistirilecek_yonler'], $bold, $normal, $np, $ip);
    if (!empty($d['genel_degerlendirme'])) { $s->addText('GENEL DEĞERLENDİRME', $bold, $np); $s->addText($d['genel_degerlendirme'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['sonuc'])) addAlan($s, 'Sonuç', $d['sonuc'], $bold, $normal, $np);
    addImzaBlogu($s, "Danışman Öğretmen: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addOkulTemelli($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    $donem = $d['donem'] ?? '';
    addOkulBaslik($s, $okul, 'OKUL TEMELLİ MESLEKİ GELİŞİM PLANLAMA KAYDI', $donem ? "$donem. Dönem" : '', $bold, $center);
    addAlan($s, 'Hazırlayan Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Sınıf', $d['sinif'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Plan Türü', $d['plan_turu'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Konu', $d['konu'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Süre', ($d['sure_dk'] ?? '') . ' dakika', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['konu_detay'])) { $s->addText('KONU DETAYI', $bold, $np); $s->addText($d['konu_detay'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['kazanimlar'])) { $s->addText('KAZANIMLAR', $bold, $np); $s->addText($d['kazanimlar'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['etkinlikler'])) { $s->addText('ETKİNLİKLER', $bold, $np); $s->addText($d['etkinlikler'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['degerlendirme'])) addAlan($s, 'Değerlendirme', $d['degerlendirme'], $bold, $normal, $np);
    addImzaBlogu($s, "Öğretmen: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addBepKaba($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'BEP KABA DEĞERLENDİRME FORMU', $d['degerlendirme_tarihi'] ?? '', $bold, $center);
    addAlan($s, 'Değerlendiren Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Öğrenci', $d['ogrenci'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Sınıf', $d['sinif'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Form Türü', $d['form_turu'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['mevcut_durum'])) { $s->addText('MEVCUT DURUM', $bold, $np); $s->addText($d['mevcut_durum'], $normal, $ip); $s->addTextBreak(1); }
    $gYonler = $d['guclü_yonler'] ?? $d['guclu_yonler'] ?? [];
    if (!empty($d['gelistirme_alanlari'])) addMaddeler($s, 'GELİŞTİRME ALANLARI', $d['gelistirme_alanlari'], $bold, $normal, $np, $ip);
    if (!empty($gYonler)) addMaddeler($s, 'GÜÇLÜ YÖNLER', $gYonler, $bold, $normal, $np, $ip);
    if (!empty($d['destek_gereken_alanlar'])) addMaddeler($s, 'DESTEK GEREKEN ALANLAR', $d['destek_gereken_alanlar'], $bold, $normal, $np, $ip);
    if (!empty($d['oncelikli_hedefler'])) addMaddeler($s, 'ÖNCELİKLİ HEDEFLER', $d['oncelikli_hedefler'], $bold, $normal, $np, $ip);
    if (!empty($d['form_icerik'])) { $s->addText('DEĞERLENDİRME ÖZET RAPORU', $bold, $np); $s->addText($d['form_icerik'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['genel_notlar'])) addAlan($s, 'Genel Notlar', $d['genel_notlar'], $bold, $normal, $np);
    addImzaBlogu($s, "Öğretmen: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addDersIci($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'DERS İÇİ DEĞERLENDİRME FORMU', ($d['ders'] ?? '') . ' — ' . ($d['tarih'] ?? ''), $bold, $center);
    addAlan($s, 'Değerlendiren Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Öğrenci', $d['ogrenci'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Konu', $d['konu'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Değerlendirme Türü', $d['tur'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['aciklama'])) { $s->addText('DEĞERLENDİRME', $bold, $np); $s->addText($d['aciklama'], $normal, $ip); $s->addTextBreak(1); }
    addAlan($s, 'Puan', (string)($d['puan'] ?? ''), $bold, $normal, $np);
    addImzaBlogu($s, "Öğretmen: $ogr", $bold, $normal);
}

function addEgzersiz($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'EGZERSİZ PLANI', $d['egzersiz_adi'] ?? '', $bold, $center);
    addAlan($s, 'Hazırlayan Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Sınıf', $d['sinif'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Kategori', $d['kategori'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Süre', ($d['sure_dk'] ?? '') . ' dakika', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['hedefler'])) { $s->addText('HEDEFLER', $bold, $np); $s->addText($d['hedefler'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['materyaller'])) { $s->addText('MATERYALLER', $bold, $np); $s->addText($d['materyaller'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['aciklama'])) { $s->addText('UYGULAMA', $bold, $np); $s->addText($d['aciklama'], $normal, $ip); $s->addTextBreak(1); }
    addImzaBlogu($s, "Öğretmen: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addKazanim($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'KAZANIM DEĞERLENDİRME FORMU', ($d['ders'] ?? '') . ' — ' . ($d['degerlendirme_tarihi'] ?? ''), $bold, $center);
    addAlan($s, 'Değerlendiren Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Öğrenci', $d['ogrenci'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Kazanım Kodu', $d['kazanim_kodu'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Kazanım', $d['kazanim_aciklama'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['gozlem'])) { $s->addText('GÖZLEM', $bold, $np); $s->addText($d['gozlem'], $normal, $ip); $s->addTextBreak(1); }
    addAlan($s, 'Puan', (string)($d['puan'] ?? ''), $bold, $normal, $np);
    if (!empty($d['notlar'])) addAlan($s, 'Notlar', $d['notlar'], $bold, $normal, $np);
    addImzaBlogu($s, "Öğretmen: $ogr", $bold, $normal);
}

function addNobet($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'HAFTALIK NÖBET LİSTESİ', ($d['hafta_baslangic'] ?? '') . ' – ' . ($d['hafta_bitis'] ?? ''), $bold, $center);
    addAlan($s, 'Okul Müdürü', $mudur, $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['nobetci_liste'])) {
        $s->addText('NÖBET ÇİZELGESİ', $bold, $np);
        foreach (explode("\n", $d['nobetci_liste']) as $line) {
            $line = trim($line);
            if ($line) $s->addText($line, $normal, $ip);
        }
        $s->addTextBreak(1);
    }
    if (!empty($d['genel_not'])) addAlan($s, 'Genel Not', $d['genel_not'], $bold, $normal, $np);
    addImzaBlogu($s, "Okul Müdürü: $mudur", $bold, $normal);
}

function addProje($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'PROJE DEĞERLENDİRME FORMU', ($d['ders'] ?? '') . ' — ' . ($d['ogrenci'] ?? ''), $bold, $center);
    addAlan($s, 'Değerlendiren Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Öğrenci', $d['ogrenci'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Proje Adı', $d['proje_adi'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Durum', $d['durum'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Başlama Tarihi', $d['baslama_tarihi'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Bitiş Tarihi', $d['bitis_tarihi'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['aciklama'])) { $s->addText('PROJE AÇIKLAMASI', $bold, $np); $s->addText($d['aciklama'], $normal, $ip); $s->addTextBreak(1); }
    if (!empty($d['degerlendirme_kriterleri'])) addMaddeler($s, 'DEĞERLENDİRME KRİTERLERİ', $d['degerlendirme_kriterleri'], $bold, $normal, $np, $ip);
    addAlan($s, 'Puan', (string)($d['puan'] ?? ''), $bold, $normal, $np);
    addImzaBlogu($s, "Öğretmen: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}

function addUrunDosyasi($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'ÜRÜN DOSYASI DEĞERLENDİRME KAYDI', ($d['ders'] ?? '') . ' — ' . ($d['tarih'] ?? ''), $bold, $center);
    addAlan($s, 'Değerlendiren Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Öğrenci', $d['ogrenci'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Dosya/Çalışma Adı', $d['dosya_adi'] ?? '', $bold, $normal, $np);
    addAlan($s, 'Kategori', $d['kategori'] ?? '', $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['aciklama'])) { $s->addText('DEĞERLENDİRME', $bold, $np); $s->addText($d['aciklama'], $normal, $ip); $s->addTextBreak(1); }
    addAlan($s, 'Puan', (string)($d['puan'] ?? ''), $bold, $normal, $np);
    addImzaBlogu($s, "Öğretmen: $ogr", $bold, $normal);
}

function addYillikPlan($s, $d, $okul, $mudur, $ogr, $bold, $normal, $muted, $center, $np, $ip) {
    addOkulBaslik($s, $okul, 'YILLIK DERS PLANI', ($d['ders'] ?? '') . ' — ' . ($d['sinif'] ?? '') . ' / ' . ($d['akademik_yil'] ?? ''), $bold, $center);
    addAlan($s, 'Hazırlayan Öğretmen', $ogr, $bold, $normal, $np);
    addAlan($s, 'Haftalık Ders Saati', (string)($d['haftalik_saat'] ?? ''), $bold, $normal, $np);
    $s->addTextBreak(1);
    if (!empty($d['donem1_konular'])) addMaddeler($s, '1. DÖNEM KONU BAŞLIKLARI (Eylül–Ocak)', $d['donem1_konular'], $bold, $normal, $np, $ip);
    if (!empty($d['donem2_konular'])) addMaddeler($s, '2. DÖNEM KONU BAŞLIKLARI (Şubat–Haziran)', $d['donem2_konular'], $bold, $normal, $np, $ip);
    if (!empty($d['yontemler'])) addMaddeler($s, 'ÖĞRETİM YÖNTEMLERİ', $d['yontemler'], $bold, $normal, $np, $ip);
    if (!empty($d['olcme_degerlendirme'])) addAlan($s, 'Ölçme-Değerlendirme', $d['olcme_degerlendirme'], $bold, $normal, $np);
    if (!empty($d['kaynaklar'])) addMaddeler($s, 'KAYNAKLAR', $d['kaynaklar'], $bold, $normal, $np, $ip);
    addImzaBlogu($s, "Hazırlayan: $ogr\nOkul Müdürü: $mudur", $bold, $normal);
}
