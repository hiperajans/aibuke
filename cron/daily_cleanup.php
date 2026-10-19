<?php
/**
 * Aibuke Ogretmen - Gunluk Temizlik Cronu
 * 90+ gunluk eski mesajlari siler
 * 30-90 gunluk mesajlari ozetler
 */

require_once __DIR__ . '/../config.php';

echo "Aibuke Gunluk Temizlik\n";
echo "======================\n\n";

$now = date('Y-m-d H:i:s');

// 90+ gunluk mesajlari sil
$stmt = $pdo->prepare("
    DELETE FROM aibuke_messages
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
");
$deleted = $stmt->rowCount();
echo "[OK] 90+ gunluk mesajlar silindi: $deleted\n";

// 30-90 gunluk konusmalari ozetle
$stmt = $pdo->query("
    SELECT id, student_id FROM aibuke_conversations
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND summary IS NULL
    LIMIT 100
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "[OK] Konusma ozetleniyor: " . count($conversations) . " adet\n";

foreach ($conversations as $conv) {
    // Son 10 mesaji al
    $stmt = $pdo->prepare("
        SELECT content FROM aibuke_messages
        WHERE conversation_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$conv['id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Basit ozet (ilk 200 karakter)
    if (!empty($messages)) {
        $summary = mb_substr($messages[0]['content'], 0, 200);

        $stmt = $pdo->prepare("
            UPDATE aibuke_conversations
            SET summary = ?
            WHERE id = ?
        ");
        $stmt->execute([$summary, $conv['id']]);
    }
}

// Kullanim tablolarini temizle (180+ gunluk)
$stmt = $pdo->query("
    DELETE FROM aibuke_daily_usage
    WHERE usage_date < DATE_SUB(NOW(), INTERVAL 180 DAY)
");
echo "[OK] Eski kullanim kayitlari temizlendi\n";

// Session temizligi
$stmt = $pdo->query("
    DELETE FROM aibuke_daily_usage
    WHERE usage_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
");
echo "[OK] 30+ gunluk kullanim verileri temizlendi\n\n";

echo "Temizlik tamamlandi!\n";
