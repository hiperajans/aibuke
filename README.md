# Aibüke Öğretmen

Öğrenciler için yapay zekâ destekli sınav koçu ve öğretmenler için evrak/plan paneli. PHP + MySQL ile yazılmış, bağımsız çalışan bir web uygulaması.

## Bölümler

| Yol | Ne yapar |
|-----|----------|
| `/` , `chat.php` | Öğrenci girişi ve Aibüke sohbeti (soru çözümü, konu anlatımı, görsel ile soru, sesli yanıt) |
| `/veli/` | Veliye özel, tek kullanımlık bağlantıyla açılan ilerleme paneli |
| `/ogretmen/` | Öğretmen paneli: yıllık/günlük ders planı, BEP, İYEP, rehberlik, zümre, kulüp, not takibi, Word çıktısı |
| `/admin/` | Okul yöneticisi paneli: öğretmenler, öğrenciler, soru havuzu, turnuvalar, raporlar |
| `cron/` | Günlük plan, bildirim, özet, puan, haftalık analiz ve soru havuzu işleri |

## Kurulum

```bash
composer install
cp .env.example .env      # değerleri doldurun
php migrate.php           # tabloları oluşturur
```

`.env` içinde gereken değerler:

- `DB_*` — MySQL bağlantısı
- `APP_URL` — uygulamanın dış adresi (veli bağlantıları ve bildirimler için)
- `GEMINI_API_KEY` — Google Gemini (sohbet, görsel okuma)
- `MINIMAX_API_KEY` — MiniMax (plan üretimi, analiz)
- `GOOGLE_TTS_API_KEY` — Google Cloud Text-to-Speech (sesli yanıt)
- `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` — isteğe bağlı, Web Push bildirimleri

Hiçbir anahtar kodda tutulmaz; `.env` depoya eklenmez.

## Cron örneği

```cron
0 3 * * *   php /yol/aibuke/cron/daily_cleanup.php
0 7 * * *   php /yol/aibuke/cron/generate_plans.php
0 8 * * *   php /yol/aibuke/cron/send_notifications.php morning
0 14 * * *  php /yol/aibuke/cron/send_notifications.php reminder
0 21 * * *  php /yol/aibuke/cron/send_notifications.php night
0 18 * * *  php /yol/aibuke/cron/comeback_check.php
0 */6 * * * php /yol/aibuke/cron/summarize_conversations.php
0 23 * * *  php /yol/aibuke/cron/update_points.php
0 20 * * 0  php /yol/aibuke/cron/weekly_analysis.php
30 7 * * *  php /yol/aibuke/cron/generate_voice.php
```

## Lisans

© Hiper Ajans. Tüm hakları saklıdır.
