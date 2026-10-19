-- Aibüke Öğretmen veritabanı şeması (yalnızca yapı, veri yok)
-- Kullanım: mysql aibuke < database/schema.sql && php migrate.php

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Çekirdek: okul / öğrenci / personel / yönetici

CREATE TABLE IF NOT EXISTS schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT '',
    principal_name VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    branch_name VARCHAR(255) NOT NULL,
    UNIQUE KEY uk_branch_school (branch_name, school_id),
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS students (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS staff (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS school_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS super_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS aibuke_push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    p256dh_key VARCHAR(255) NOT NULL,
    auth_key VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Uygulama tabloları

CREATE TABLE IF NOT EXISTS `aday_ogretmen_dosyasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `aday_adi` varchar(200) NOT NULL,
  `tarih` date DEFAULT NULL,
  `dosya_turu` varchar(100) DEFAULT NULL COMMENT 'sinav,performans,gozlem,...',
  `puan` tinyint(4) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `notlar` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aday` (`aday_adi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `badge_code` varchar(50) NOT NULL,
  `badge_name` varchar(100) NOT NULL,
  `badge_icon` varchar(10) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `requirement_type` varchar(50) DEFAULT NULL,
  `requirement_value` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `badge_code` (`badge_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_conversations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT 'Yeni Sohbet',
  `summary` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_school` (`school_id`),
  KEY `idx_active` (`student_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_daily_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `plan_date` date NOT NULL,
  `plan_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`plan_content`)),
  `weak_topics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`weak_topics`)),
  `mood_note` text DEFAULT NULL,
  `motivation_tip` text DEFAULT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_date` (`student_id`,`plan_date`),
  KEY `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_daily_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `usage_date` date NOT NULL,
  `message_count` int(11) DEFAULT 0,
  `exam_sim_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `tokens_consumed` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_date` (`student_id`,`usage_date`),
  KEY `idx_date` (`usage_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_exam_analyses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `exam_type` varchar(100) DEFAULT NULL,
  `exam_date` date DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `analysis_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`analysis_result`)),
  `weak_topics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`weak_topics`)),
  `strong_topics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`strong_topics`)),
  `total_correct` int(11) DEFAULT NULL,
  `total_wrong` int(11) DEFAULT NULL,
  `total_empty` int(11) DEFAULT NULL,
  `net_score` decimal(5,2) DEFAULT NULL,
  `ai_recommendation` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_school` (`school_id`),
  KEY `idx_date` (`student_id`,`exam_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_gardens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `garden_level` tinyint(4) DEFAULT 1,
  `total_leaves` int(11) DEFAULT 0,
  `total_flowers` int(11) DEFAULT 0,
  `decorations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`decorations`)),
  `last_watered` date DEFAULT NULL,
  `streak_days` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_active_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_group_student` (`group_id`,`student_id`),
  CONSTRAINT `aibuke_group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `aibuke_study_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_group_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `is_ai` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_group_date` (`group_id`,`created_at`),
  CONSTRAINT `aibuke_group_messages_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `aibuke_study_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) NOT NULL,
  `student_id` int(11) NOT NULL,
  `role` enum('student','aibuke') NOT NULL,
  `content` text NOT NULL,
  `ai_model` varchar(30) DEFAULT NULL,
  `has_image` tinyint(1) DEFAULT 0,
  `image_path` varchar(500) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT 0,
  `sentiment_score` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `youtube_query` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conv_date` (`conversation_id`,`created_at`),
  KEY `idx_student_date` (`student_id`,`created_at`),
  KEY `idx_cleanup` (`created_at`),
  CONSTRAINT `aibuke_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `aibuke_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_parent_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `parent_email` varchar(255) DEFAULT NULL,
  `kvkk_consent` tinyint(1) DEFAULT 0,
  `consent_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_accessed` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_points` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `action_type` enum('question_solved','correct_answer','daily_login','streak_3day','streak_7day','study_30min','photo_question','plan_completed','exam_uploaded','helped_friend') NOT NULL,
  `points` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_school_points` (`school_id`,`points`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_question_pool` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(50) NOT NULL,
  `topic` varchar(150) DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') NOT NULL DEFAULT 'medium',
  `exam_type` enum('lgs','tyt','ayt') NOT NULL DEFAULT 'tyt',
  `question_text` text NOT NULL,
  `option_a` text NOT NULL,
  `option_b` text NOT NULL,
  `option_c` text NOT NULL,
  `option_d` text NOT NULL,
  `option_e` text DEFAULT NULL,
  `correct_answer` char(1) NOT NULL,
  `explanation` text DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `has_image` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `question_hash` char(32) DEFAULT NULL,
  `times_used` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_question_hash` (`question_hash`),
  KEY `idx_pool_query` (`subject`,`exam_type`,`difficulty`,`is_active`,`times_used`),
  KEY `idx_pool_topic` (`subject`,`topic`,`exam_type`),
  KEY `idx_pool_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_safety_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `alert_type` enum('kendine_zarar','siddet','zarar_gorme','zorbalik','kritik') NOT NULL,
  `severity` enum('dusuk','orta','yuksek','kritik') NOT NULL DEFAULT 'yuksek',
  `trigger_message` text NOT NULL,
  `ai_response` text DEFAULT NULL,
  `gathered_info` text DEFAULT NULL COMMENT 'AI nin topladığı ek bilgiler JSON',
  `conversation_id` int(11) DEFAULT NULL,
  `is_notified_admin` tinyint(1) DEFAULT 0,
  `is_notified_parent` tinyint(1) DEFAULT 0,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_by` varchar(100) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_school` (`school_id`),
  KEY `idx_type` (`alert_type`),
  KEY `idx_resolved` (`is_resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `aibuke_school_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `report_month` date NOT NULL,
  `report_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`report_content`)),
  `generated_by` varchar(30) DEFAULT 'deepseek',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_school_month` (`school_id`,`report_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_sentiment_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `alert_type` enum('red_flag','gold_flag','critical') NOT NULL,
  `trigger_messages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`trigger_messages`)),
  `avg_score` decimal(3,2) DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_by` varchar(100) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_school_type` (`school_id`,`alert_type`),
  KEY `idx_unresolved` (`is_resolved`,`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_student_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_badge` (`student_id`,`badge_id`),
  KEY `idx_student` (`student_id`),
  KEY `badge_id` (`badge_id`),
  CONSTRAINT `aibuke_student_badges_ibfk_1` FOREIGN KEY (`badge_id`) REFERENCES `aibuke_badges` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_student_behavior_patterns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `pattern_type` enum('study_habit','error_pattern','emotional','engagement') NOT NULL,
  `description` text NOT NULL,
  `ai_recommendation` text DEFAULT NULL,
  `confidence_score` tinyint(4) DEFAULT 50,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_active` (`student_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_student_profiles` (
  `student_id` int(11) NOT NULL,
  `exam_type` enum('lgs','yks','obp') NOT NULL,
  `yks_field` enum('mf','tm','sozel','dil') DEFAULT NULL,
  `persona_mode` enum('abla','koc','rehber') NOT NULL,
  `target_profession` varchar(255) DEFAULT NULL,
  `target_university` varchar(255) DEFAULT NULL,
  `target_department` varchar(255) DEFAULT NULL,
  `target_score` int(11) DEFAULT NULL,
  `study_hours_per_day` tinyint(4) DEFAULT 4,
  `daily_mood_score` tinyint(4) DEFAULT 5,
  `learning_style` enum('gorsel','isitsel','okuma_yazma','kinestetik') DEFAULT NULL,
  `learning_style_confidence` tinyint(4) DEFAULT 0,
  `onboarding_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_student_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `strength_score` tinyint(4) DEFAULT NULL,
  `questions_solved` int(11) DEFAULT 0,
  `correct_answers` int(11) DEFAULT 0,
  `error_type_careless` int(11) DEFAULT 0,
  `error_type_knowledge` int(11) DEFAULT 0,
  `error_type_time` int(11) DEFAULT 0,
  `error_type_confusion` int(11) DEFAULT 0,
  `last_studied_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_subject` (`student_id`,`subject`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_study_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `created_by` enum('system','student') DEFAULT 'system',
  `max_members` tinyint(4) DEFAULT 4,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `emptied_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_school` (`school_id`),
  KEY `idx_active` (`is_active`,`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_study_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `schedule_type` enum('weekly','daily','exam_prep') DEFAULT 'weekly',
  `title` varchar(255) DEFAULT NULL,
  `schedule_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`schedule_data`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_active` (`student_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_tournaments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `tournament_type` enum('konu','genel','haftalik') DEFAULT 'konu',
  `question_count` tinyint(4) DEFAULT 5,
  `time_per_question` int(11) DEFAULT 120,
  `status` enum('waiting','active','finished') DEFAULT 'waiting',
  `starts_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_school_status` (`school_id`,`status`),
  KEY `idx_starts` (`starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_tournament_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_answer` char(1) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `time_spent` int(11) DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_answer` (`tournament_id`,`student_id`,`question_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `aibuke_tournament_answers_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `aibuke_tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `aibuke_tournament_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `aibuke_tournament_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_tournament_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `score` int(11) DEFAULT 0,
  `correct_count` tinyint(4) DEFAULT 0,
  `wrong_count` tinyint(4) DEFAULT 0,
  `avg_time_seconds` decimal(5,1) DEFAULT 0.0,
  `finished` tinyint(1) DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tournament_student` (`tournament_id`,`student_id`),
  CONSTRAINT `aibuke_tournament_participants_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `aibuke_tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_tournament_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `question_order` tinyint(4) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(500) DEFAULT NULL,
  `option_b` varchar(500) DEFAULT NULL,
  `option_c` varchar(500) DEFAULT NULL,
  `option_d` varchar(500) DEFAULT NULL,
  `correct_answer` char(1) NOT NULL,
  `explanation` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tournament_id` (`tournament_id`),
  CONSTRAINT `aibuke_tournament_questions_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `aibuke_tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_tts_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usage_month` date NOT NULL,
  `total_characters` int(11) DEFAULT 0,
  `free_limit` int(11) DEFAULT 1000000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_month` (`usage_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `aibuke_voice_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `message_type` enum('morning_greeting','motivation','study_reminder','achievement') DEFAULT NULL,
  `text_content` text DEFAULT NULL,
  `audio_path` varchar(500) DEFAULT NULL,
  `char_count` int(11) DEFAULT 0,
  `is_sent` tinyint(1) DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_unsent` (`is_sent`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `bep_kaba_form` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `form_turu` enum('sinif','bireysel') DEFAULT 'bireysel',
  `sinif` varchar(20) DEFAULT NULL,
  `degerlendirme_tarihi` date DEFAULT NULL,
  `ogretmen_adi` varchar(200) DEFAULT NULL,
  `form_icerik` longtext DEFAULT NULL COMMENT 'JSON form alanlarini depolar',
  `genel_notlar` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`,`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `bep_plan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `veli_adi` varchar(200) DEFAULT NULL,
  `hedefler` longtext DEFAULT NULL COMMENT 'JSON: [{hedefe: str, kazanim: str}]',
  `etkinlikler` longtext DEFAULT NULL COMMENT 'JSON: [{etkinlik: str, tarih: str, durum: str}]',
  `degerlendirme` longtext DEFAULT NULL COMMENT 'JSON: [{tarih: str, sonuc: str, not: str}]',
  `baslama_tarihi` date DEFAULT NULL,
  `bitis_tarihi` date DEFAULT NULL,
  `durum` enum('hazirlaniyor','uygulamada','tamamlandi','iptal') DEFAULT 'hazirlaniyor',
  `olusturan_not` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`,`school_id`),
  KEY `idx_durum` (`durum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `degerlendirme_kriterler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `tur` enum('performans','proje','urun_dosyasi','ders_ici') NOT NULL,
  `kriter_adi` varchar(255) NOT NULL,
  `puan_agirlik` tinyint(4) DEFAULT 1 COMMENT 'Agirlik/carpan',
  `aktif` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tur` (`tur`),
  KEY `idx_aktif` (`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `ders_ici_degerlendirme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `ders` varchar(100) NOT NULL,
  `konu` varchar(255) NOT NULL,
  `puan` tinyint(4) DEFAULT NULL,
  `tarih` date NOT NULL,
  `tur` enum('soru_cevap','etkinlik','grup_calismasi','bireysel') DEFAULT 'soru_cevap',
  `aciklama` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`,`school_id`),
  KEY `idx_tarih` (`tarih`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `dosyalarim` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `dosya_adi` varchar(255) NOT NULL,
  `kategori` varchar(100) DEFAULT 'genel',
  `dosya_turu` varchar(50) DEFAULT NULL COMMENT 'pdf,doc,img,video...',
  `dosya_yolu` varchar(500) NOT NULL,
  `dosya_boyut` int(11) DEFAULT 0 COMMENT 'byte',
  `aciklama` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kategori` (`kategori`),
  KEY `idx_olusturan` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `egzersiz_planlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `egzersiz_adi` varchar(200) NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `sinif` varchar(20) DEFAULT NULL,
  `sure_dk` int(11) DEFAULT 40 COMMENT 'egzersiz suresi dakika',
  `hedefler` text DEFAULT NULL,
  `materyaller` text DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kategori` (`kategori`),
  KEY `idx_sinif` (`sinif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `islenen_konular` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogretmen_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `ders` varchar(100) NOT NULL,
  `sinif` varchar(30) NOT NULL,
  `ders_tarihi` date NOT NULL,
  `konu_basligi` varchar(200) NOT NULL,
  `konu_detay` text DEFAULT NULL,
  `kazanimlar` text DEFAULT NULL,
  `ev_odevi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogretmen` (`ogretmen_id`),
  KEY `idx_school_tarih` (`school_id`,`ders_tarihi`),
  KEY `idx_sinif_tarih` (`school_id`,`sinif`,`ders_tarihi`),
  CONSTRAINT `islenen_konular_ibfk_1` FOREIGN KEY (`ogretmen_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `iyep_plan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `tur` enum('turkce','matematik') NOT NULL,
  `sinif` varchar(20) NOT NULL,
  `baslama_tarihi` date DEFAULT NULL,
  `bitis_tarihi` date DEFAULT NULL,
  `mevcut_durum` text DEFAULT NULL COMMENT 'Mevcut seviye tanimi',
  `hedeflenen_kazanimlar` longtext DEFAULT NULL COMMENT 'JSON: [{kod: str, aciklama: str, durum: str}]',
  `uygulanan_etkinlikler` longtext DEFAULT NULL COMMENT 'JSON: [{tarih: str, etkinlik: str, sonuc: str}]',
  `degerlendirme_sonuc` text DEFAULT NULL,
  `genel_notlar` text DEFAULT NULL,
  `olusturan_not` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`,`school_id`),
  KEY `idx_tur` (`tur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `kazanim_degerlendirme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `ogrenci_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `ders` varchar(100) NOT NULL,
  `kazanim_kodu` varchar(50) NOT NULL,
  `kazanim_aciklama` text DEFAULT NULL,
  `degerlendirme_tarihi` date NOT NULL,
  `puan` tinyint(4) DEFAULT NULL COMMENT '0-100 arası puan',
  `gozlem` text DEFAULT NULL,
  `notlar` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`),
  KEY `idx_ders` (`ders`,`kazanim_kodu`),
  KEY `idx_school` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `kulup_evraklari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `kulup_adi` varchar(150) NOT NULL,
  `etkinlik_tarihi` date NOT NULL,
  `etkinlik_saati` varchar(50) DEFAULT NULL,
  `etkinlik_yeri` varchar(200) DEFAULT NULL,
  `katilimci_sayisi` int(11) DEFAULT 0,
  `aciklama` text DEFAULT NULL,
  `ogrenci_listesi` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ogrenci_listesi`)),
  `hedefler` text DEFAULT NULL,
  `sonuclar` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_school` (`school_id`),
  KEY `idx_kulup` (`school_id`,`kulup_adi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `maarif_rapor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `rapor_ayi` varchar(20) NOT NULL,
  `rapor_yili` int(11) NOT NULL,
  `sinif` varchar(20) DEFAULT NULL,
  `icerik` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`icerik`)),
  `genel_degerlendirme` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rapor` (`school_id`,`rapor_ayi`,`rapor_yili`,`sinif`),
  KEY `idx_school` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `nobet_listesi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `hafta_baslangic` date NOT NULL,
  `hafta_bitis` date NOT NULL,
  `nobetci_liste` longtext DEFAULT NULL COMMENT 'JSON: [{sinif: str, ogretmen: str, tarih: str}]',
  `genel_not` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hafta` (`hafta_baslangic`,`hafta_bitis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `not_takip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `sinav_turu` enum('deneme','quiz','sozlu') NOT NULL,
  `sinav_tarihi` date NOT NULL,
  `puan` decimal(5,2) DEFAULT NULL,
  `dogru` int(11) DEFAULT NULL,
  `yanlis` int(11) DEFAULT NULL,
  `bos` int(11) DEFAULT NULL,
  `ders` varchar(100) DEFAULT NULL,
  `not_icerik` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`not_icerik`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`),
  KEY `idx_school` (`school_id`),
  KEY `idx_tarih` (`sinav_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `okul_takvim` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `event_type` enum('tatil','sinav','donem_basi','donem_sonu','ders_gunu','ozel') NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_teaching_day` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_date` (`school_id`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `okul_temelli_planlama` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `sinif` varchar(20) NOT NULL,
  `donem` tinyint(4) DEFAULT 1,
  `plan_turu` enum('ilkokul_1_2','ilkokul_5','ortaokul_9','lise_10','ozel') NOT NULL,
  `konu` varchar(255) NOT NULL,
  `konu_detay` text DEFAULT NULL,
  `kazanimlar` text DEFAULT NULL,
  `etkinlikler` text DEFAULT NULL,
  `tarih` date DEFAULT NULL,
  `sure_dk` int(11) DEFAULT 40,
  `degerlendirme` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sinif` (`sinif`),
  KEY `idx_tur` (`plan_turu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `performans_gorevler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `ders` varchar(100) NOT NULL,
  `konu` varchar(255) NOT NULL,
  `puan` tinyint(4) DEFAULT NULL,
  `tarih` date NOT NULL,
  `aciklama` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`,`school_id`),
  KEY `idx_tarih` (`tarih`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `projeler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `proje_adi` varchar(255) NOT NULL,
  `ders` varchar(100) NOT NULL,
  `durum` enum('devam','tamamlandi','iptal') DEFAULT 'devam',
  `puan` tinyint(4) DEFAULT NULL,
  `baslama_tarihi` date DEFAULT NULL,
  `bitis_tarihi` date DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`,`school_id`),
  KEY `idx_durum` (`durum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `rehberlik_gorisme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `tur` enum('ogrenci','veli') NOT NULL,
  `gorisme_tarihi` date DEFAULT NULL,
  `konu` varchar(255) DEFAULT NULL,
  `icerik` text DEFAULT NULL,
  `sonuc` text DEFAULT NULL,
  `takip_tarihi` date DEFAULT NULL,
  `olusturan_not` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`,`school_id`),
  KEY `idx_tur` (`tur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `rehberlik_takip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `takip_turu` enum('boy','kilo','boy_kilo') NOT NULL,
  `olcum_tarihi` date NOT NULL,
  `boy` decimal(5,2) DEFAULT NULL COMMENT 'cm',
  `kilo` decimal(5,2) DEFAULT NULL COMMENT 'kg',
  `vki` decimal(5,2) DEFAULT NULL COMMENT 'vucut kitle indeksi',
  `yorum` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`,`school_id`),
  KEY `idx_tarih` (`olcum_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `siniflar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `sinif_adi` varchar(30) NOT NULL,
  `yil` int(11) NOT NULL DEFAULT 2025,
  `ogretmen_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sinif` (`school_id`,`sinif_adi`,`yil`),
  KEY `idx_school` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `surec_degerlendirme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `ogrenci_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `ders` varchar(100) NOT NULL,
  `surec_adi` varchar(200) NOT NULL,
  `surec_turu` enum('proje','performans','urun_dosyasi','ders_ici') DEFAULT 'proje',
  `degerlendirme_tarihi` date NOT NULL,
  `puan` tinyint(4) DEFAULT NULL COMMENT '0-100 arası puan',
  `kriterler` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'kriter_adi => puan seklinde' CHECK (json_valid(`kriterler`)),
  `gozlem` text DEFAULT NULL,
  `notlar` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`),
  KEY `idx_ders` (`ders`),
  KEY `idx_school` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `urun_dosyalari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `dosya_adi` varchar(255) NOT NULL,
  `ders` varchar(100) NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `puan` tinyint(4) DEFAULT NULL,
  `tarih` date NOT NULL,
  `dosya_yolu` varchar(500) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci` (`ogrenci_id`,`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `veli_toplanti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `sinif` varchar(20) NOT NULL,
  `toplanti_tarihi` date NOT NULL,
  `toplanti_saati` varchar(20) DEFAULT NULL,
  `gundem` text DEFAULT NULL,
  `kararlar` text DEFAULT NULL,
  `katilimci_sayisi` int(11) DEFAULT 0,
  `notlar` text DEFAULT NULL,
  `veli_gorusleri` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_school` (`school_id`),
  KEY `idx_sinif` (`school_id`,`sinif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `yazili_ortalama` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `sinif` varchar(20) NOT NULL,
  `ders` varchar(100) NOT NULL,
  `donem` tinyint(1) NOT NULL,
  `yazili1` decimal(5,2) DEFAULT NULL,
  `yazili2` decimal(5,2) DEFAULT NULL,
  `yazili3` decimal(5,2) DEFAULT NULL,
  `sozlu1` decimal(5,2) DEFAULT NULL,
  `sozlu2` decimal(5,2) DEFAULT NULL,
  `dinleme1` decimal(5,2) DEFAULT NULL,
  `dinleme2` decimal(5,2) DEFAULT NULL,
  `ortalama` decimal(5,2) GENERATED ALWAYS AS (case when `yazili1` is not null or `yazili2` is not null or `yazili3` is not null then round((coalesce(`yazili1`,0) + coalesce(`yazili2`,0) + coalesce(`yazili3`,0) + coalesce(`sozlu1`,0) + coalesce(`sozlu2`,0) + coalesce(`dinleme1`,0) + coalesce(`dinleme2`,0)) / (if(`yazili1` is null,0,1) + if(`yazili2` is null,0,1) + if(`yazili3` is null,0,1) + if(`sozlu1` is null,0,1) + if(`sozlu2` is null,0,1) + if(`dinleme1` is null,0,1) + if(`dinleme2` is null,0,1)),2) else NULL end) STORED,
  `yil` int(11) DEFAULT 2025,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ogrenci_ders` (`ogrenci_id`,`ders`,`donem`,`yil`),
  KEY `idx_school` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `yillik_plan_haftalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `donem` tinyint(1) NOT NULL,
  `hafta_no` tinyint(2) NOT NULL,
  `baslangic_tarihi` date NOT NULL,
  `bitis_tarihi` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_hafta` (`school_id`,`donem`,`hafta_no`),
  KEY `idx_date` (`school_id`,`baslangic_tarihi`,`bitis_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `yillik_plan_mufredat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `sinif` varchar(20) NOT NULL,
  `donem` tinyint(1) NOT NULL,
  `hafta_no` tinyint(2) NOT NULL,
  `gun_no` tinyint(1) NOT NULL DEFAULT 1,
  `konu_basligi` varchar(255) NOT NULL,
  `konu_detay` text DEFAULT NULL,
  `kazanimlar` text DEFAULT NULL,
  `oneri_ev_odevi` varchar(500) DEFAULT NULL,
  `kaynak` varchar(255) DEFAULT NULL,
  `saat_sayisi` tinyint(1) DEFAULT 2,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plan` (`school_id`,`branch_id`,`sinif`,`donem`,`hafta_no`,`gun_no`),
  KEY `idx_lookup` (`school_id`,`branch_id`,`sinif`,`donem`,`hafta_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `zumre_evraklari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `okul_turu` enum('ilkokul','ortaokul','lise','meslek','ozelegitim') NOT NULL,
  `ders` varchar(100) NOT NULL,
  `donem` varchar(20) NOT NULL,
  `toplanti_tarihi` date NOT NULL,
  `katilimcilar` text DEFAULT NULL,
  `gundem` text DEFAULT NULL,
  `kararlar` text DEFAULT NULL,
  `notlar` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_school` (`school_id`),
  KEY `idx_ders` (`school_id`,`ders`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

SET FOREIGN_KEY_CHECKS = 1;
