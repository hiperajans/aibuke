<?php
/**
 * Aibuke Ogretmen - Duygu Durumu Analizi (Sentiment Tracking)
 * Keyword tabanli, bedava sentiment skoru
 */

require_once __DIR__ . '/../config.php';

class SentimentAnalyzer {

    private $positive = [
        'basarili', 'basardin', 'basardim', 'basar', 'harika', 'super', 'muhtesem',
        'efso', 'efsane', 'gut', 'güzel', 'iyi', 'cok iyi', 'süper', 'tesekkur',
        'memnun', 'mutlu', 'sevindim', 'nefis', 'kral', 'sahane', 'alman',
        'basardim', 'anladim', 'anliyorum', 'cozdu', 'cozdum', 'basitmis',
        'kolay', 'basit', 'heyecanli', 'motivasyon', 'moral', 'guclu'
    ];

    private $negative = [
        'yapamiyorum', 'yapamiyorum', 'anlamiyorum', 'zor', 'zormus', 'sikildim',
        'biktim', 'sikildim', 'biktim', 'berbat', 'kotu', ' berbat', 'nefret',
        'bosversin', 'istemiyorum', 'bunalim', 'bunaldim', 'bezdim', 'yorgun',
        'umudum kırıldı', 'hayal kirıklığı', 'basarisiz', 'kaybettim', 'kaybettim',
        'sinir', 'kızdım', 'kizdim', 'of', 'yeter', 'bırak', 'bırakıyorum'
    ];

    private $critical = [
        'intihar', 'olmek', 'olsem', 'olmek', 'bitirsem', 'kendime zarar',
        'hissiz', 'anlamsiz', 'bos', 'hicbir sey', 'değilim', 'degilim',
        'yok olmak', 'yokum', 'bitiyorum', 'son', 'final'
    ];

    /**
     * Mesajin sentiment skorunu hesaplar
     * @return int -2 to +2
     */
    public function analyze($message) {
        $lower = mb_strtolower($message, 'UTF-8');
        $score = 0;

        // Kritik kelimeler kontrolu (-2)
        foreach ($this->critical as $word) {
            if (mb_strpos($lower, $word) !== false) {
                return -2;
            }
        }

        // Negatif kelimeler kontrolu (-1)
        foreach ($this->negative as $word) {
            if (mb_strpos($lower, $word) !== false) {
                $score -= 1;
            }
        }

        // Pozitif kelimeler kontrolu (+1)
        foreach ($this->positive as $word) {
            if (mb_strpos($lower, $word) !== false) {
                $score += 1;
            }
        }

        // Skoru sinirla
        return max(-2, min(2, $score));
    }

    /**
     * Son N mesajin ortalama sentimentini hesapla
     */
    public function getAverageSentiment($pdo, $studentId, $messageCount = 3) {
        $stmt = $pdo->prepare("
            SELECT sentiment_score FROM aibuke_messages
            WHERE student_id = ? AND role = 'student'
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$studentId, $messageCount]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($messages)) return 0;

        $total = array_sum(array_column($messages, 'sentiment_score'));
        return $total / count($messages);
    }

    /**
     * Red Flag kontrolu - ardisik negatif mesajlar
     */
    public function checkRedFlag($pdo, $studentId) {
        $avgScore = $this->getAverageSentiment($pdo, $studentId, 3);

        // Son 3 mesajin ortalamasi -1.0'dan kucukse Red Flag
        if ($avgScore < -1.0) {
            $this->createAlert($pdo, $studentId, 'red_flag', $avgScore);
            return true;
        }

        // Gold Flag - motivasyon yuksek
        if ($avgScore > 1.5) {
            $this->createAlert($pdo, $studentId, 'gold_flag', $avgScore);
            return false;
        }

        return false;
    }

    /**
     * Alarm olustur
     */
    private function createAlert($pdo, $studentId, $type, $score) {
        // Son 3 mesajin ID'lerini al
        $stmt = $pdo->prepare("
            SELECT id FROM aibuke_messages
            WHERE student_id = ? AND role = 'student'
            ORDER BY created_at DESC
            LIMIT 3
        ");
        $stmt->execute([$studentId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $messageIds = array_column($messages, 'id');

        // Okul ID'sini al
        $stmt = $pdo->prepare("SELECT school_id FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            INSERT INTO aibuke_sentiment_alerts
            (student_id, school_id, alert_type, trigger_messages, avg_score)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $studentId,
            $student['school_id'],
            $type,
            json_encode($messageIds),
            $score
        ]);
    }

    /**
     * Emoji donusumu (skor -> emoji)
     */
    public function scoreToEmoji($score) {
        switch ($score) {
            case 2: return '😊';  // Cok positif
            case 1: return '🙂';  // Pozitif
            case 0: return '😐';  // Notr
            case -1: return '😔'; // Negatif
            case -2: return '😢'; // Kritik
            default: return '😐';
        }
    }
}
