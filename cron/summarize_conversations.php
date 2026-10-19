<?php
/**
 * Aibuke - Konuşma Özetleme (Gemini ile)
 * 20+ mesajlık konuşmaları özetler (token tasarrufu)
 * Cron: Her 6 saatte bir
 * php /var/www/aibuke/cron/summarize_conversations.php
 */

require_once __DIR__ . '/../config.php';

echo "=== Konuşma Özetleme ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

// Özeti olmayan ve mesaj sayısı 20'yi aşmış aktif konuşmalar
$stmt = $pdo->query("
    SELECT c.id, c.student_id,
           (SELECT COUNT(*) FROM aibuke_messages WHERE conversation_id = c.id) as msg_count
    FROM aibuke_conversations c
    WHERE c.is_active = 1 AND c.summary IS NULL
    HAVING msg_count >= 20
    LIMIT 50
");
$conversations = $stmt->fetchAll();

echo "Özetlenecek konuşma: " . count($conversations) . "\n\n";

$success = 0;

foreach ($conversations as $conv) {
    $stmt = $pdo->prepare("
        SELECT role, content FROM aibuke_messages
        WHERE conversation_id = ? AND role IN ('student','aibuke')
        ORDER BY created_at ASC LIMIT 20
    ");
    $stmt->execute([$conv['id']]);
    $messages = $stmt->fetchAll();

    $text = '';
    foreach ($messages as $m) {
        $r = $m['role'] === 'student' ? 'Öğrenci' : 'Aibüke';
        $text .= "{$r}: {$m['content']}\n";
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;
    $data = [
        'contents' => [['role' => 'user', 'parts' => [['text' => "Bu konuşmayı 2-3 cümleyle özetle. Öğrencinin adını, konuşulan konuları ve önemli detayları koru. Sadece özeti yaz.\n\n{$text}"]]]],
        'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 256]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($resp, true);
    $summary = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($summary) {
        $pdo->prepare("UPDATE aibuke_conversations SET summary = ? WHERE id = ?")->execute([trim($summary), $conv['id']]);
        $success++;
        echo "[OK] Konuşma #{$conv['id']} (öğrenci {$conv['student_id']})\n";
    } else {
        echo "[HATA] Konuşma #{$conv['id']}\n";
    }

    usleep(500000); // 0.5s bekleme (rate limit)
}

echo "\n=== {$success} konuşma özetlendi ===\n";
