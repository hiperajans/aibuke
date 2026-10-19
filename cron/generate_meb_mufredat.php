<?php
/**
 * Aibuke - MEB Müfredat Üretici
 * CLI: php /var/www/aibuke/cron/generate_meb_mufredat.php [options]
 *
 * MiniMax M2.7 API kullanarak MEB TYMM 2025-2026 müfredatına uygun
 * yillik_plan_mufredat tablosuna haftalık planlar ekler.
 *
 * Kullanım:
 *   php generate_meb_mufredat.php                    # Tüm ders/sınıf kombinasyonları için
 *   php generate_meb_mufredat.php --brans=1          # Sadece branş ID 1 için
 *   php generate_meb_mufredat.php --sinif=5          # Sadece 5. sınıf için
 *   php generate_mef_mufredat.php --donem=1          # Sadece 1. dönem için
 *   php generate_meb_mufredat.php --dry-run          # Gerçek üretim yapmadan dene
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/MinimaxAPI.php';

$options = getopt('', ['brans:', 'sinif:', 'donem:', 'dry-run', 'help']);
$dryRun = isset($options['dry-run']);

if (isset($options['help']) || isset($options['h'])) {
    echo "MEB Müfredat Üretici\n";
    echo "Kullanım: php generate_meb_mufredat.php [seçenekler]\n";
    echo "Seçenekler:\n";
    echo "  --brans=ID    Sadece belirtilen branş ID için üret\n";
    echo "  --sinif=NO    Sadece belirtilen sınıf için üret (5-12)\n";
    echo "  --donem=NO    Sadece belirtilen dönem için üret (1-2)\n";
    echo "  --dry-run     Üretim yapmadan sadece dene\n";
    echo "  --help        Bu yardım mesajını göster\n";
    exit(0);
}

echo "=== MEB Müfredat Üretici ===\n";
echo "Tarih: " . date('Y-m-d H:i:s') . "\n";
if ($dryRun) echo "MOD: DRY-RUN (üretim yapılmayacak)\n";
echo "\n";

$api = new MinimaxAPI();

// Branşlar
$branslar = $pdo->query("SELECT id, branch_name FROM branches ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$siniflar = ['5','6','7','8','9','10','11','12'];
$donemler = [1, 2];

// Filtreler
$filterBrans = isset($options['brans']) ? (int)$options['brans'] : null;
$filterSinif = isset($options['sinif']) ? $options['sinif'] : null;
$filterDonem = isset($options['donem']) ? (int)$options['donem'] : null;

$toplam = 0;
$basarili = 0;
$hatali = 0;
$atlanan = 0;

foreach ($branslar as $brans) {
    if ($filterBrans && $brans['id'] != $filterBrans) continue;

    foreach ($siniflar as $sinif) {
        if ($filterSinif && $sinif != $filterSinif) continue;

        foreach ($donemler as $donem) {
            if ($filterDonem && $donem != $filterDonem) continue;

            // Haftalar
            $haftalar = $pdo->prepare("SELECT * FROM yillik_plan_haftalar WHERE school_id=0 AND donem=? ORDER BY hafta_no");
            $haftalar->execute([$donem]);
            $haftalar = $haftalar->fetchAll(PDO::FETCH_ASSOC);

            if (empty($haftalar)) {
                echo "[ATLA] {$brans['branch_name']} - Sınıf $sinif - Dönem $donem: Hafta verisi yok\n";
                $atlanan++;
                continue;
            }

            echo "\n{$brans['branch_name']} - Sınıf $sinif - Dönem $donem (" . count($haftalar) . " hafta)\n";
            echo str_repeat('-', 50) . "\n";

            foreach ($haftalar as $hafta) {
                $haftaNo = $hafta['hafta_no'];
                $basTarih = $hafta['baslangic_tarihi'];
                $bitTarih = $hafta['bitis_tarihi'];

                // Zaten plan var mı kontrol et (okul_id=0 merkezi)
                $stmt = $pdo->prepare("SELECT id FROM yillik_plan_mufredat WHERE school_id=0 AND branch_id=? AND sinif=? AND donem=? AND hafta_no=? AND gun_no=1");
                $stmt->execute([$brans['id'], $sinif, $donem, $haftaNo]);
                $mevcut = $stmt->fetch();

                if ($mevcut) {
                    echo "  [MEVCUT] Hafta $haftaNo - atlanıyor\n";
                    $atlanan++;
                    continue;
                }

                $toplam++;

                if ($dryRun) {
                    echo "  [DRY] Hafta $haftaNo ($basTarih - $bitTarih) - üretilecek\n";
                    $basarili++;
                    continue;
                }

                // MiniMax API çağrısı
                $donemAd = $donem == 1 ? '1. Dönem' : '2. Dönem';
                $prompt = "MEB 2025-2026 $sinif. sınıf {$brans['branch_name']} dersi $donemAd hafta $haftaNo icin bir haftalık ders planı olustur.

Tarih araligi: $basTarih - $bitTarih

Türkçe yanıt ver. Yanıtı sadece JSON olarak ver, baska hiçbir sey yazma.

JSON formatı:
{
  \"konu_basligi\": \"...\", (max 200 karakter)
  \"konu_detay\": \"...\", (detayli konu anlatimi, alt basliklar dahil, bol nokta isaretli liste)
  \"kazanimlar\": \"...\", (MEB kazanım kodlari ile, ornek: T.5.1.1, M.7.1.1.1, Fen.8.1.2 - her satirda bir kazanim)
  \"oneri_ev_odevi\": \"...\", (max 500 karakter)
  \"kaynak\": \"...\", (ders kitabi sayfa referansi, ornek: Matematik Ders Kitabi 5. Sinif Sayfa 45-47)
  \"saat_sayisi\": 2
}

Kazanım kodları MEB resmi formatında olmalı (ornegin: T.5.1.1, M.7.1.1.1, Fen.8.1.2).
Her kazanım ayrı satırda yazılmalı. Konu detayı detaylı ve açıklayıcı olmalı.";

                $result = $api->generateResponse($prompt, [
                    'persona_mode' => 'koc',
                    'history' => [],
                    'student' => ['school_name' => 'Okul']
                ]);

                if (!$result['success'] || empty($result['text'])) {
                    echo "  [HATA] Hafta $haftaNo - API hatasi: " . ($result['error'] ?? 'bilinmeyen') . "\n";
                    $hatali++;
                    continue;
                }

                // JSON parse
                $text = trim($result['text']);
                if (preg_match('/```(?:json)?\s*([\s\S]+?)```/', $text, $m)) {
                    $text = $m[1];
                }
                $data = json_decode($text, true);

                if (!is_array($data) || empty($data['konu_basligi'])) {
                    echo "  [HATA] Hafta $haftaNo - JSON parse basarisiz veya bos konu\n";
                    $hatali++;
                    continue;
                }

                $baslik = mb_substr(trim($data['konu_basligi'] ?? ''), 0, 200);
                $detay = trim($data['konu_detay'] ?? '');
                $kazanim = trim($data['kazanimlar'] ?? '');
                $evOdevi = mb_substr(trim($data['oneri_ev_odevi'] ?? ''), 0, 500);
                $kaynak = trim($data['kaynak'] ?? '');
                $saat = max(1, min(6, (int)($data['saat_sayisi'] ?? 2)));

                // Veritabanina kaydet (school_id = 0, merkezi sablon)
                $pdo->prepare("INSERT INTO yillik_plan_mufredat (school_id, branch_id, sinif, donem, hafta_no, gun_no, konu_basligi, konu_detay, kazanimlar, oneri_ev_odevi, kaynak, saat_sayisi, created_by) VALUES (0, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, NULL)")
                    ->execute([$brans['id'], $sinif, $donem, $haftaNo, $baslik, $detay, $kazanim, $evOdevi, $kaynak, $saat]);

                echo "  [OK] Hafta $haftaNo: " . mb_substr($baslik, 0, 60) . "\n";
                $basarili++;

                // Rate limit korumasi - her istek arası 1 sn bekle
                sleep(1);
            }
        }
    }
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "TAMAMLANDI\n";
echo "Toplam hafta: $toplam\n";
echo "Basarili: $basarili\n";
echo "Hatali: $hatali\n";
echo "Atlanan (mevcut/atlanan): $atlanan\n";
echo "Tarih: " . date('Y-m-d H:i:s') . "\n";
