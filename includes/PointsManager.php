<?php
/**
 * Aibuke Öğretmen - Puan & Rozet Yöneticisi
 * Oyunlaştırma mantığı
 */

require_once __DIR__ . '/../config.php';

class PointsManager {

    private $pdo;

    // Puan tablosu
    private $pointValues = [
        'daily_login'      => 5,
        'question_solved'  => 10,
        'correct_answer'   => 15,
        'photo_question'   => 10,
        'study_30min'      => 20,
        'exam_uploaded'    => 20,
        'streak_3day'      => 25,
        'plan_completed'   => 30,
        'streak_7day'      => 50,
        'helped_friend'    => 15
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Puan ekle
     */
    public function addPoints($studentId, $actionType, $description = '') {
        if (!isset($this->pointValues[$actionType])) return false;

        $points = $this->pointValues[$actionType];

        // Aynı gün aynı aksiyondan tekrar puan verme kontrolü (bazı tipler için)
        if (in_array($actionType, ['daily_login', 'streak_3day', 'streak_7day', 'study_30min'])) {
            $stmt = $this->pdo->prepare("
                SELECT id FROM aibuke_points
                WHERE student_id = ? AND action_type = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute([$studentId, $actionType]);
            if ($stmt->fetch()) return false; // Bugün zaten verilmiş
        }

        // school_id'yi al
        $stmt = $this->pdo->prepare("SELECT school_id FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        if (!$student) return false;

        $stmt = $this->pdo->prepare("
            INSERT INTO aibuke_points (student_id, school_id, action_type, points, description)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$studentId, $student['school_id'], $actionType, $points, $description]);

        // Rozet kontrolü
        $this->checkBadges($studentId);

        return $points;
    }

    /**
     * Günlük giriş puanı ver + streak kontrolü
     */
    public function recordDailyLogin($studentId) {
        $this->addPoints($studentId, 'daily_login', 'Günlük giriş');

        // Streak hesapla
        $streak = $this->calculateStreak($studentId);

        if ($streak >= 7) {
            $this->addPoints($studentId, 'streak_7day', "{$streak} gün streak!");
        } elseif ($streak >= 3) {
            $this->addPoints($studentId, 'streak_3day', "{$streak} gün streak!");
        }

        return $streak;
    }

    /**
     * Streak hesapla (üst üste kaç gün girmiş)
     */
    public function calculateStreak($studentId) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT usage_date
            FROM aibuke_daily_usage
            WHERE student_id = ?
            ORDER BY usage_date DESC
            LIMIT 60
        ");
        $stmt->execute([$studentId]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($dates)) return 0;

        $streak = 0;
        $expected = new DateTime();

        // Bugün henüz kayıt yoksa dünden başla
        if ($dates[0] !== $expected->format('Y-m-d')) {
            $expected->modify('-1 day');
            if ($dates[0] !== $expected->format('Y-m-d')) {
                return 0; // Dün de girmemiş, streak kırılmış
            }
        }

        foreach ($dates as $date) {
            if ($date === $expected->format('Y-m-d')) {
                $streak++;
                $expected->modify('-1 day');
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Öğrencinin toplam puanı
     */
    public function getTotalPoints($studentId) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(points), 0) as total
            FROM aibuke_points WHERE student_id = ?
        ");
        $stmt->execute([$studentId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Öğrencinin rozetleri
     */
    public function getStudentBadges($studentId) {
        $stmt = $this->pdo->prepare("
            SELECT b.badge_code, b.badge_name, b.badge_icon, b.description, sb.earned_at
            FROM aibuke_student_badges sb
            JOIN aibuke_badges b ON sb.badge_id = b.id
            WHERE sb.student_id = ?
            ORDER BY sb.earned_at DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }

    /**
     * Okul bazlı liderlik tablosu
     */
    public function getLeaderboard($schoolId, $limit = 20, $period = 'all', $class = null) {
        $dateFilter = '';
        if ($period === 'weekly') {
            $dateFilter = 'AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        } elseif ($period === 'monthly') {
            $dateFilter = 'AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }

        $classFilter = '';
        $params = [$schoolId];
        if ($class) {
            $classFilter = 'AND s.class = ?';
            $params[] = $class;
        }
        $params[] = $limit;

        $stmt = $this->pdo->prepare("
            SELECT
                p.student_id,
                s.first_name,
                s.last_name,
                s.nickname,
                s.class,
                SUM(p.points) as total_points,
                (SELECT GROUP_CONCAT(b.badge_icon ORDER BY sb2.earned_at DESC SEPARATOR '')
                 FROM aibuke_student_badges sb2
                 JOIN aibuke_badges b ON sb2.badge_id = b.id
                 WHERE sb2.student_id = p.student_id
                 LIMIT 3) as badges
            FROM aibuke_points p
            JOIN students s ON p.student_id = s.id
            WHERE p.school_id = ? {$classFilter} {$dateFilter}
            GROUP BY p.student_id
            ORDER BY total_points DESC
            LIMIT ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Öğrencinin okul sıralaması
     */
    public function getStudentRank($studentId, $schoolId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) + 1 as rank_position
            FROM (
                SELECT student_id, SUM(points) as total
                FROM aibuke_points
                WHERE school_id = ?
                GROUP BY student_id
                HAVING total > (
                    SELECT COALESCE(SUM(points), 0)
                    FROM aibuke_points WHERE student_id = ?
                )
            ) ranked
        ");
        $stmt->execute([$schoolId, $studentId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Öğrencinin puan profili (özet)
     */
    public function getStudentProfile($studentId, $schoolId) {
        return [
            'total_points'  => $this->getTotalPoints($studentId),
            'rank'          => $this->getStudentRank($studentId, $schoolId),
            'streak'        => $this->calculateStreak($studentId),
            'badges'        => $this->getStudentBadges($studentId),
            'today_points'  => $this->getTodayPoints($studentId),
            'correct_count' => $this->getCorrectAnswerCount($studentId)
        ];
    }

    /**
     * Bugün kazanılan puanlar
     */
    public function getTodayPoints($studentId) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(points), 0)
            FROM aibuke_points
            WHERE student_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$studentId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Toplam doğru cevap sayısı
     */
    public function getCorrectAnswerCount($studentId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM aibuke_points
            WHERE student_id = ? AND action_type = 'correct_answer'
        ");
        $stmt->execute([$studentId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Sınıfındaki yüzdelik dilim (Agresif Rekabet)
     */
    public function getClassPercentile($studentId, $schoolId) {
        $student = $this->pdo->prepare("SELECT class FROM students WHERE id = ?");
        $student->execute([$studentId]);
        $class = $student->fetchColumn();
        if (!$class) return null;

        $myPoints = $this->getTotalPoints($studentId);

        // Aynı sınıftaki toplam öğrenci ve benden düşük olanlar
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM aibuke_student_profiles sp
            JOIN students s ON sp.student_id = s.id
            WHERE s.school_id = ? AND s.class = ?
        ");
        $stmt->execute([$schoolId, $class]);
        $totalInClass = (int) $stmt->fetchColumn();
        if ($totalInClass <= 1) return ['percentile' => 100, 'class' => $class, 'total' => $totalInClass];

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT sp.student_id, COALESCE(SUM(p.points), 0) as total
                FROM aibuke_student_profiles sp
                JOIN students s ON sp.student_id = s.id
                LEFT JOIN aibuke_points p ON sp.student_id = p.student_id
                WHERE s.school_id = ? AND s.class = ?
                GROUP BY sp.student_id
                HAVING total < ?
            ) below_me
        ");
        $stmt->execute([$schoolId, $class, $myPoints]);
        $belowMe = (int) $stmt->fetchColumn();

        $percentile = round(($belowMe / $totalInClass) * 100);
        return ['percentile' => $percentile, 'class' => $class, 'total' => $totalInClass, 'rank_in_class' => $totalInClass - $belowMe];
    }

    /**
     * Rekabet verisi — seni geçen kişi, sınıf sıralaması
     */
    public function getCompetitionData($studentId, $schoolId) {
        $percentile = $this->getClassPercentile($studentId, $schoolId);
        $myPoints = $this->getTotalPoints($studentId);

        // Beni geçen en yakın kişi
        $stmt = $this->pdo->prepare("
            SELECT s.first_name, COALESCE(SUM(p.points), 0) as total
            FROM aibuke_student_profiles sp
            JOIN students s ON sp.student_id = s.id
            LEFT JOIN aibuke_points p ON sp.student_id = p.student_id
            WHERE s.school_id = ? AND sp.student_id != ?
            GROUP BY sp.student_id
            HAVING total > ?
            ORDER BY total ASC
            LIMIT 1
        ");
        $stmt->execute([$schoolId, $studentId, $myPoints]);
        $aheadOfMe = $stmt->fetch(PDO::FETCH_ASSOC);

        // Haftalık okul sıralamasındaki pozisyon
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) + 1
            FROM (
                SELECT student_id, SUM(points) as total
                FROM aibuke_points
                WHERE school_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY student_id
                HAVING total > (
                    SELECT COALESCE(SUM(points), 0)
                    FROM aibuke_points
                    WHERE student_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                )
            ) ranked
        ");
        $stmt->execute([$schoolId, $studentId]);
        $weeklyRank = (int) $stmt->fetchColumn();

        return [
            'percentile' => $percentile,
            'ahead_of_me' => $aheadOfMe,
            'gap' => $aheadOfMe ? ($aheadOfMe['total'] - $myPoints) : 0,
            'weekly_rank' => $weeklyRank
        ];
    }

    /**
     * Rozet kazanım kontrolü
     */
    private function checkBadges($studentId) {
        $totalPoints = $this->getTotalPoints($studentId);
        $streak = $this->calculateStreak($studentId);
        $correctCount = $this->getCorrectAnswerCount($studentId);

        // Tüm rozet tanımlarını al
        $stmt = $this->pdo->query("SELECT * FROM aibuke_badges");
        $allBadges = $stmt->fetchAll();

        foreach ($allBadges as $badge) {
            $earned = false;

            switch ($badge['requirement_type']) {
                case 'total_points':
                    $earned = $totalPoints >= $badge['requirement_value'];
                    break;
                case 'streak':
                    $earned = $streak >= $badge['requirement_value'];
                    break;
                case 'questions_solved':
                    $earned = $correctCount >= $badge['requirement_value'];
                    break;
                case 'monthly_rank':
                    // Aylık sıralama — ayrı cron ile kontrol edilir
                    break;
            }

            if ($earned) {
                // Zaten varsa atla (INSERT IGNORE)
                $stmt2 = $this->pdo->prepare("
                    INSERT IGNORE INTO aibuke_student_badges (student_id, badge_id)
                    VALUES (?, ?)
                ");
                $stmt2->execute([$studentId, $badge['id']]);
            }
        }
    }
}
