<?php
/**
 * Aibuke Veritabanı Migrasyon Scripti
 * Okul/öğrenci/personel çekirdek tablolarını ve tüm aibuke_* tablolarını oluşturur
 */

require_once 'config.php';

echo "<h1>Aibuke Veritabanı Migrasyonu</h1>";
echo "<pre>";

$tables = [];

// ──────────────────────────────────────────────────────────
// ÇEKİRDEK: OKUL / ÖĞRENCİ / PERSONEL / YÖNETİCİ
// ──────────────────────────────────────────────────────────

// Tablo C1: Okullar
$tables[] = "CREATE TABLE IF NOT EXISTS schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT '',
    principal_name VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo C2: Branşlar
$tables[] = "CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    branch_name VARCHAR(255) NOT NULL,
    UNIQUE KEY uk_branch_school (branch_name, school_id),
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo C3: Öğrenciler
$tables[] = "CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    nickname VARCHAR(255) DEFAULT NULL,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    class VARCHAR(50) NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_school_class (school_id, class),
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo C4: Personel (öğretmenler)
$tables[] = "CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    role ENUM('principal', 'vice_principal', 'teacher') NOT NULL DEFAULT 'teacher',
    branch_id INT DEFAULT NULL,
    sinif_ogretmeni VARCHAR(50) DEFAULT NULL,
    username VARCHAR(100) DEFAULT NULL UNIQUE,
    password VARCHAR(255) DEFAULT NULL,
    INDEX idx_school_role (school_id, role),
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo C5: Okul yöneticileri
$tables[] = "CREATE TABLE IF NOT EXISTS school_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo C6: Süper yöneticiler
$tables[] = "CREATE TABLE IF NOT EXISTS super_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo C7: Web Push abonelikleri
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    p256dh_key VARCHAR(255) NOT NULL,
    auth_key VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 1: Konuşmalar
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_conversations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    school_id INT NOT NULL,
    title VARCHAR(255) DEFAULT 'Yeni Sohbet',
    summary TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_school (school_id),
    INDEX idx_active (student_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 2: Mesajlar
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT NOT NULL,
    student_id INT NOT NULL,
    role ENUM('student', 'aibuke') NOT NULL,
    content TEXT NOT NULL,
    ai_model VARCHAR(30),
    has_image TINYINT(1) DEFAULT 0,
    image_path VARCHAR(500),
    tokens_used INT DEFAULT 0,
    sentiment_score TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conv_date (conversation_id, created_at),
    INDEX idx_student_date (student_id, created_at),
    INDEX idx_cleanup (created_at),
    FOREIGN KEY (conversation_id) REFERENCES aibuke_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 3: Günlük kullanım (rate limiting)
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_daily_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    usage_date DATE NOT NULL,
    message_count INT DEFAULT 0,
    tokens_consumed INT DEFAULT 0,
    UNIQUE KEY uk_student_date (student_id, usage_date),
    INDEX idx_date (usage_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 4: Öğrenci profilleri
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_student_profiles (
    student_id INT PRIMARY KEY,
    exam_type ENUM('lgs', 'yks', 'obp') NOT NULL,
    yks_field ENUM('mf', 'tm', 'sozel', 'dil'),
    persona_mode ENUM('abla', 'koc', 'rehber') NOT NULL,
    target_profession VARCHAR(255),
    target_university VARCHAR(255),
    target_department VARCHAR(255),
    target_score INT,
    study_hours_per_day TINYINT DEFAULT 4,
    daily_mood_score TINYINT DEFAULT 5,
    learning_style ENUM('gorsel', 'isitsel', 'okuma_yazma', 'kinestetik'),
    learning_style_confidence TINYINT DEFAULT 0,
    onboarding_completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 5: Günlük planlar (Faz 2)
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_daily_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    plan_date DATE NOT NULL,
    plan_content JSON,
    weak_topics JSON,
    mood_note TEXT,
    motivation_tip TEXT,
    completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_student_date (student_id, plan_date),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 6: Öğrenci ilerleme (Faz 2)
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_student_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject VARCHAR(100),
    topic VARCHAR(255),
    strength_score TINYINT,
    questions_solved INT DEFAULT 0,
    correct_answers INT DEFAULT 0,
    error_type_careless INT DEFAULT 0,
    error_type_knowledge INT DEFAULT 0,
    error_type_time INT DEFAULT 0,
    error_type_confusion INT DEFAULT 0,
    last_studied_at TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_subject (student_id, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 7: Puan sistemi
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_points (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    school_id INT NOT NULL,
    action_type ENUM(
        'question_solved', 'correct_answer', 'daily_login',
        'streak_3day', 'streak_7day', 'study_30min',
        'photo_question', 'plan_completed', 'exam_uploaded', 'helped_friend'
    ) NOT NULL,
    points INT NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_school_points (school_id, points),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 8: Rozet tanımları
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    badge_code VARCHAR(50) UNIQUE NOT NULL,
    badge_name VARCHAR(100) NOT NULL,
    badge_icon VARCHAR(10) NOT NULL,
    description VARCHAR(255),
    requirement_type VARCHAR(50),
    requirement_value INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 9: Öğrenci rozetleri
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_student_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_student_badge (student_id, badge_id),
    INDEX idx_student (student_id),
    FOREIGN KEY (badge_id) REFERENCES aibuke_badges(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 10: Deneme analizleri (Faz 2)
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_exam_analyses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    school_id INT NOT NULL,
    exam_type VARCHAR(100),
    exam_date DATE,
    image_path VARCHAR(500),
    analysis_result JSON,
    weak_topics JSON,
    strong_topics JSON,
    total_correct INT,
    total_wrong INT,
    total_empty INT,
    net_score DECIMAL(5,2),
    ai_recommendation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_school (school_id),
    INDEX idx_date (student_id, exam_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 11: Duygu durumu alarmları
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_sentiment_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    school_id INT NOT NULL,
    alert_type ENUM('red_flag', 'gold_flag', 'critical') NOT NULL,
    trigger_messages JSON,
    avg_score DECIMAL(3,2),
    is_resolved TINYINT(1) DEFAULT 0,
    resolved_by VARCHAR(100),
    resolved_at DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_school_type (school_id, alert_type),
    INDEX idx_unresolved (is_resolved, school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 12: Davranış pattern'leri (Faz 2)
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_student_behavior_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    pattern_type ENUM('study_habit', 'error_pattern', 'emotional', 'engagement') NOT NULL,
    description TEXT NOT NULL,
    ai_recommendation TEXT,
    confidence_score TINYINT DEFAULT 50,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_active (student_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 13: Grup çalışma odaları (Faz 3)
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_study_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    group_name VARCHAR(255),
    topic VARCHAR(255),
    subject VARCHAR(100),
    created_by ENUM('system', 'student') DEFAULT 'system',
    max_members TINYINT DEFAULT 4,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    emptied_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_school (school_id),
    INDEX idx_active (is_active, school_id),
    INDEX idx_emptied (emptied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 14: Grup üyelikleri
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    student_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_group_student (group_id, student_id),
    FOREIGN KEY (group_id) REFERENCES aibuke_study_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 15: Grup mesajları
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_group_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    student_id INT,
    content TEXT NOT NULL,
    is_ai TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_group_date (group_id, created_at),
    FOREIGN KEY (group_id) REFERENCES aibuke_study_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 16: Okul raporları (Faz 3)
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_school_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    report_month DATE NOT NULL,
    report_content JSON,
    generated_by VARCHAR(30) DEFAULT 'deepseek',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_school_month (school_id, report_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 17: Sesli mesaj kayıtları (Faz 2)
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_voice_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    message_type ENUM('morning_greeting', 'motivation', 'study_reminder', 'achievement'),
    text_content TEXT,
    audio_path VARCHAR(500),
    char_count INT DEFAULT 0,
    is_sent TINYINT(1) DEFAULT 0,
    sent_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_unsent (is_sent, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 18: Haftalık çalışma programları (Faz 2)
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_study_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    schedule_type ENUM('weekly', 'daily', 'exam_prep') DEFAULT 'weekly',
    title VARCHAR(255),
    schedule_data JSON NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_active (student_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 18: Haftalık çalışma programları (Faz 2)
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_study_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    schedule_type ENUM('weekly', 'daily', 'exam_prep') DEFAULT 'weekly',
    title VARCHAR(255),
    schedule_data JSON NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_active (student_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 19: TTS kullanım takibi
$tables[] = "CREATE TABLE IF NOT EXISTS aibuke_tts_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usage_month DATE NOT NULL,
    total_characters INT DEFAULT 0,
    free_limit INT DEFAULT 1000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_month (usage_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// ──────────────────────────────────────────────────────────
// FAZ 1: YILLIK PLAN + GÜNLÜK PLAN + TAKVIM
// ──────────────────────────────────────────────────────────

// Tablo 20: Yıllık Plan Müfredatı
$tables[] = "CREATE TABLE IF NOT EXISTS yillik_plan_mufredat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    branch_id INT NOT NULL,
    sinif VARCHAR(20) NOT NULL,
    donem TINYINT(1) NOT NULL COMMENT '1=1.donem, 2=2.donem',
    hafta_no TINYINT(2) NOT NULL COMMENT '1-20, donem ici hafta numarasi',
    gun_no TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Pzt, 2=Sal, ...',
    konu_basligi VARCHAR(255) NOT NULL,
    konu_detay TEXT,
    kazanimlar TEXT,
    oneri_ev_odevi VARCHAR(500),
    kaynak VARCHAR(255) COMMENT 'ders kitabi sayfa referansi',
    saat_sayisi TINYINT(1) DEFAULT 2,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_plan (school_id, branch_id, sinif, donem, hafta_no, gun_no),
    INDEX idx_lookup (school_id, branch_id, sinif, donem, hafta_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 21: Hafta-Tarih Eşlemesi
$tables[] = "CREATE TABLE IF NOT EXISTS yillik_plan_haftalar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    donem TINYINT(1) NOT NULL,
    hafta_no TINYINT(2) NOT NULL,
    baslangic_tarihi DATE NOT NULL,
    bitis_tarihi DATE NOT NULL,
    UNIQUE KEY uk_hafta (school_id, donem, hafta_no),
    INDEX idx_date (school_id, baslangic_tarihi, bitis_tarihi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 22: Okul Takvimi (Tatiller, Sınavlar, Özel Günler)
$tables[] = "CREATE TABLE IF NOT EXISTS okul_takvim (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    event_type ENUM('tatil','sinav','donem_basi','donem_sonu','ders_gunu','ozel') NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_teaching_day TINYINT(1) DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (school_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tabloları oluştur
foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
        echo "[OK] Tablo oluşturuldu\n";
    } catch (PDOException $e) {
        echo "[HATA] " . $e->getMessage() . "\n";
    }
}

// Rozetleri ekle
$badges = [
    ['filiz', 'Filiz', '🌱', 'İlk 50 puan', 'total_points', 50],
    ['kitap_kurdu', 'Kitap Kurdu', '📚', '500 puan', 'total_points', 500],
    ['ateslansen', 'Ateslansen', '🔥', '7 gün streak', 'streak', 7],
    ['yildiz', 'Yıldız Öğrenci', '⭐', '1000 puan', 'total_points', 1000],
    ['nokta_atıcı', 'Nokta Atıcı', '🎯', '50 doğru cevap', 'questions_solved', 50],
    ['vazgecmez', 'Vazgeçmez', '💪', '30 gün streak', 'streak', 30],
    ['deha', 'Deha', '🧠', '5000 puan', 'total_points', 5000],
    ['sampiyon', 'Şampiyon', '🏆', 'Aylık okul birincisi', 'monthly_rank', 1]
];

$stmt = $pdo->prepare("INSERT IGNORE INTO aibuke_badges (badge_code, badge_name, badge_icon, description, requirement_type, requirement_value) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($badges as $badge) {
    $stmt->execute($badge);
}

echo "\n[OK] Rozetler eklendi\n";

// ──────────────────────────────────────────────────────────
// FAZ 1: TAKVİM SEED DATA (2025-2026 - Merkezi, school_id=0)
// ──────────────────────────────────────────────────────────

$takvimEvents = [
    // 1. Dönem
    ['donem_basi', '1. Dönem Başı', '2025-09-08', '2025-09-08', 1],
    ['tatil', '29 Ekim Cumhuriyet Bayramı', '2025-10-29', '2025-10-29', 0],
    ['ozel', '10 Kasım Atatürk\'ü Anma', '2025-11-10', '2025-11-10', 0],
    ['tatil', 'Yılbaşı Tatili', '2025-12-31', '2026-01-01', 0],
    ['sinav', '1. Dönem 1. Yazılı Haftası', '2025-12-15', '2025-12-19', 1],
    ['sinav', '1. Dönem 2. Yazılı Haftası', '2026-01-06', '2026-01-09', 1],
    ['donem_sonu', '1. Dönem Sonu', '2026-01-16', '2026-01-16', 0],
    ['tatil', 'Yarıyıl Tatili', '2026-01-19', '2026-01-30', 0],
    // 2. Dönem
    ['donem_basi', '2. Dönem Başı', '2026-02-02', '2026-02-02', 1],
    ['tatil', '23 Nisan Ulusal Egemenlik ve Çocuk Bayramı', '2026-04-23', '2026-04-23', 0],
    ['sinav', '2. Dönem 1. Yazılı Haftası', '2026-03-09', '2026-03-13', 1],
    ['sinav', '2. Dönem 2. Yazılı Haftası', '2026-04-06', '2026-04-10', 1],
    ['ozel', '19 Mayıs Atatürk\'ü Anma ve Gençlik ve Spor Bayramı', '2026-05-19', '2026-05-19', 0],
    ['sinav', 'LGS Deneme Sınavı', '2026-05-14', '2026-05-15', 1],
    ['donem_sonu', '2. Dönem Sonu', '2026-06-12', '2026-06-12', 0],
    ['tatil', 'Yaz Tatili Başlangıcı', '2026-06-13', '2026-09-07', 0],
];

$stmtTakvim = $pdo->prepare("INSERT IGNORE INTO okul_takvim (school_id, event_type, event_name, start_date, end_date, is_teaching_day) VALUES (0, ?, ?, ?, ?, ?)");
foreach ($takvimEvents as $e) {
    $stmtTakvim->execute($e);
}

// Hafta-tarih eşlemesi (1. dönem 15 hafta + 2. dönem 15 hafta = 30 hafta)
$haftalar = [];

// 1. Dönem haftaları (8 Eylül 2025 - 16 Ocak 2026)
$haftaNo = 1;
$baslangic = new DateTime('2025-09-08');
for ($i = 0; $i < 15; $i++) {
    $bas = clone $baslangic;
    $bit = clone $baslangic;
    $bit->add(new DateInterval('P6D'));
    $haftalar[] = [0, 1, $haftaNo, $bas->format('Y-m-d'), $bit->format('Y-m-d')];
    $baslangic->add(new DateInterval('P7D'));
    $haftaNo++;
}

// Yarıyıl tatili atla (19-30 Ocak)

// 2. Dönem haftaları (2 Şubat 2026 - 12 Haziran 2026)
$haftaNo = 1;
$baslangic2 = new DateTime('2026-02-02');
for ($i = 0; $i < 18; $i++) {
    $bas = clone $baslangic2;
    $bit = clone $baslangic2;
    $bit->add(new DateInterval('P6D'));
    $haftalar[] = [0, 2, $haftaNo, $bas->format('Y-m-d'), $bit->format('Y-m-d')];
    $baslangic2->add(new DateInterval('P7D'));
    $haftaNo++;
}

$stmtHafta = $pdo->prepare("INSERT IGNORE INTO yillik_plan_haftalar (school_id, donem, hafta_no, baslangic_tarihi, bitis_tarihi) VALUES (?, ?, ?, ?, ?)");
foreach ($haftalar as $h) {
    $stmtHafta->execute($h);
}

echo "[OK] Varsayılan takvim ve haftalar eklendi (school_id=0)\n";

// ──────────────────────────────────────────────────────────
// FAZ 2: ZÜMRE + KULÜP + VELİ TOPLANTI + MAARİF RAPOR
// ──────────────────────────────────────────────────────────

// Tablo 23: Zümre Evrakları
$tables[] = "CREATE TABLE IF NOT EXISTS zumre_evraklari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    created_by INT NOT NULL,
    okul_turu ENUM('ilkokul','ortaokul','lise','meslek','ozelegitim') NOT NULL,
    ders VARCHAR(100) NOT NULL,
    donem VARCHAR(20) NOT NULL,
    toplanti_tarihi DATE NOT NULL,
    katilimcilar TEXT,
    gundem TEXT,
    kararlar TEXT,
    notlar TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_school (school_id),
    INDEX idx_ders (school_id, ders)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 24: Kulüp Evrakları
$tables[] = "CREATE TABLE IF NOT EXISTS kulup_evraklari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    created_by INT NOT NULL,
    kulup_adi VARCHAR(150) NOT NULL,
    etkinlik_tarihi DATE NOT NULL,
    etkinlik_saati VARCHAR(50),
    etkinlik_yeri VARCHAR(200),
    katilimci_sayisi INT DEFAULT 0,
    aciklama TEXT,
    ogrenci_listesi JSON,
    hedefler TEXT,
    sonuclar TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_school (school_id),
    INDEX idx_kulup (school_id, kulup_adi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 25: Veli Toplantı Tutanakları
$tables[] = "CREATE TABLE IF NOT EXISTS veli_toplanti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    created_by INT NOT NULL,
    sinif VARCHAR(20) NOT NULL,
    toplanti_tarihi DATE NOT NULL,
    toplanti_saati VARCHAR(20),
    gundem TEXT,
    kararlar TEXT,
    katilimci_sayisi INT DEFAULT 0,
    notlar TEXT,
    veli_gorusleri TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_school (school_id),
    INDEX idx_sinif (school_id, sinif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 26: Maarif Aylık Raporları
$tables[] = "CREATE TABLE IF NOT EXISTS maarif_rapor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    created_by INT NOT NULL,
    rapor_ayi VARCHAR(20) NOT NULL,
    rapor_yili INT NOT NULL,
    sinif VARCHAR(20),
    icerik JSON,
    genel_degerlendirme TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rapor (school_id, rapor_ayi, rapor_yili, sinif),
    INDEX idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// ──────────────────────────────────────────────────────────
// FAZ 3: ÖĞRENCİ/SINIF İŞLEMLERİ + NOT TAKİP
// ──────────────────────────────────────────────────────────

// Tablo 27: Sınıflar
$tables[] = "CREATE TABLE IF NOT EXISTS siniflar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    sinif_adi VARCHAR(30) NOT NULL,
    yil INT NOT NULL DEFAULT 2025,
    ogretmen_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sinif (school_id, sinif_adi, yil),
    INDEX idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 28: Not Takip (Deneme/Quiz/Sözlü)
$tables[] = "CREATE TABLE IF NOT EXISTS not_takip (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ogrenci_id INT NOT NULL,
    school_id INT NOT NULL,
    created_by INT NOT NULL,
    sinav_turu ENUM('deneme','quiz','sozlu') NOT NULL,
    sinav_tarihi DATE NOT NULL,
    puan DECIMAL(5,2) DEFAULT NULL,
    dogru INT DEFAULT NULL,
    yanlis INT DEFAULT NULL,
    bos INT DEFAULT NULL,
    ders VARCHAR(100) DEFAULT NULL,
    not_icerik JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ogrenci (ogrenci_id),
    INDEX idx_school (school_id),
    INDEX idx_tarih (sinav_tarihi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 29: Yazılı/Konuşma/Dinleme Ortalamaları
$tables[] = "CREATE TABLE IF NOT EXISTS yazili_ortalama (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ogrenci_id INT NOT NULL,
    school_id INT NOT NULL,
    created_by INT NOT NULL,
    sinif VARCHAR(20) NOT NULL,
    ders VARCHAR(100) NOT NULL,
    donem TINYINT(1) NOT NULL,
    yazili1 DECIMAL(5,2) DEFAULT NULL,
    yazili2 DECIMAL(5,2) DEFAULT NULL,
    yazili3 DECIMAL(5,2) DEFAULT NULL,
    sozlu1 DECIMAL(5,2) DEFAULT NULL,
    sozlu2 DECIMAL(5,2) DEFAULT NULL,
    dinleme1 DECIMAL(5,2) DEFAULT NULL,
    dinleme2 DECIMAL(5,2) DEFAULT NULL,
    yil INT DEFAULT 2025,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ogrenci_ders (ogrenci_id, ders, donem, yil),
    INDEX idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 30: Kazanım Değerlendirme
$tables[] = "CREATE TABLE IF NOT EXISTS kazanim_degerlendirme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    ogrenci_id INT NOT NULL,
    created_by INT NOT NULL,
    ders VARCHAR(100) NOT NULL,
    kazanim_kodu VARCHAR(50) NOT NULL,
    kazanim_aciklama TEXT,
    degerlendirme_tarihi DATE NOT NULL,
    puan TINYINT DEFAULT NULL,
    gozlem TEXT,
    notlar TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ogrenci (ogrenci_id),
    INDEX idx_ders (ders, kazanim_kodu),
    INDEX idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

// Tablo 31: Süreç Değerlendirme
$tables[] = "CREATE TABLE IF NOT EXISTS surec_degerlendirme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    ogrenci_id INT NOT NULL,
    created_by INT NOT NULL,
    ders VARCHAR(100) NOT NULL,
    surec_adi VARCHAR(200) NOT NULL,
    surec_turu ENUM('proje','performans','urun_dosyasi','ders_ici') DEFAULT 'proje',
    degerlendirme_tarihi DATE NOT NULL,
    puan TINYINT DEFAULT NULL,
    kriterler JSON,
    gozlem TEXT,
    notlar TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ogrenci (ogrenci_id),
    INDEX idx_ders (ders),
    INDEX idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci";

echo "\n=== MİGRASYON TAMAMLANDI ===\n";
echo "<p><a href='index.php'>Ana sayfaya dön</a></p>";
