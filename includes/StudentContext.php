<?php
/**
 * Aibuke Ogretmen - Ogrenci Baglami
 */

require_once __DIR__ . '/../config.php';

/**
 * Ogrencinin baglam verilerini getirir
 */
function getStudentContext($pdo, $studentId) {
    $student = getStudentBasicInfo($pdo, $studentId);
    if (!$student) return null;

    $context = [
        'student' => $student,
        'profile' => getOrCreateProfile($pdo, $studentId, $student),
        'behavior_patterns' => getBehaviorPatterns($pdo, $studentId)
    ];

    return $context;
}

/**
 * Temel ogrenci bilgileri
 */
function getStudentBasicInfo($pdo, $studentId) {
    $stmt = $pdo->prepare("
        SELECT s.*, sch.name as school_name
        FROM students s
        LEFT JOIN schools sch ON s.school_id = sch.id
        WHERE s.id = ?
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Profili getirir veya olusturur
 */
function getOrCreateProfile($pdo, $studentId, $student) {
    $stmt = $pdo->prepare("SELECT * FROM aibuke_student_profiles WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        // Profil olustur
        $examType = detectExamType($student['class']);
        $persona = detectPersona($examType);

        $stmt = $pdo->prepare("
            INSERT INTO aibuke_student_profiles
            (student_id, exam_type, persona_mode)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$studentId, $examType, $persona]);

        $profile = [
            'student_id' => $studentId,
            'exam_type' => $examType,
            'persona_mode' => $persona,
            'target_profession' => null,
            'onboarding_completed' => 0
        ];
    }

    return $profile;
}

/**
 * Siniftan sinav turunu tespit et
 */
function detectExamType($class) {
    if (preg_match('/^8/', $class)) {
        return 'lgs';
    } elseif (preg_match('/^12|mezun/i', $class)) {
        return 'yks';
    }
    return 'obp';
}

/**
 * Sinav turunu persona moda cevir
 */
function detectPersona($examType) {
    switch ($examType) {
        case 'lgs': return 'abla';
        case 'yks': return 'koc';
        default: return 'rehber';
    }
}

/**
 * Ogrencinin aktif davranis pattern'larini getirir
 */
function getBehaviorPatterns($pdo, $studentId) {
    $stmt = $pdo->prepare("
        SELECT pattern_type, description, ai_recommendation, confidence_score
        FROM aibuke_student_behavior_patterns
        WHERE student_id = ? AND is_active = 1
        ORDER BY confidence_score DESC
        LIMIT 3
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * AI icin baglam kartini olusturur
 */
function buildContextCard($pdo, $studentId) {
    $context = getStudentContext($pdo, $studentId);
    if (!$context) return '';

    $s = $context['student'];
    $p = $context['profile'];

    // Kalan gun hesapla
    $daysUntil = 0;
    if ($p['exam_type'] === 'lgs') {
        $daysUntil = (strtotime('2026-06-01') - time()) / (60 * 60 * 24);
    } elseif ($p['exam_type'] === 'yks') {
        $daysUntil = (strtotime('2026-06-20') - time()) / (60 * 60 * 24);
    }

    // Öğrenme stili
    $learningStyle = '';
    if (!empty($p['learning_style'])) {
        $styleNames = [
            'gorsel' => 'Görsel',
            'isitsel' => 'İşitsel',
            'okuma_yazma' => 'Okuma/Yazma',
            'kinestetik' => 'Kinestetik'
        ];
        $styleName = $styleNames[$p['learning_style']] ?? $p['learning_style'];
        $confidence = $p['learning_style_confidence'] ?? 0;
        $learningStyle = "Ogrenme Stili: {$styleName} (%{$confidence} guven)";
    }

    // Davranış pattern'ları
    $patterns = $context['behavior_patterns'] ?? [];
    $patternLines = [];
    foreach ($patterns as $pat) {
        $patternLines[] = "- [{$pat['pattern_type']}] {$pat['description']} → {$pat['ai_recommendation']}";
    }

    $card = "OGRENCI KARTI
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ad: {$s['first_name']} " . ($s['nickname'] ? "({$s['nickname']}) " : "") . "{$s['last_name']}
Seviye: " . strtoupper($p['exam_type']) . ($p['yks_field'] ? " - " . strtoupper($p['yks_field']) : "") . "
Sinif: {$s['class']}, {$s['school_name']}
Sinava Kalan Gun: " . max(0, round($daysUntil)) . "
Hedef: " . ($p['target_profession'] ?: 'Belirlenmemiş') . "
Persona: {$p['persona_mode']}
{$learningStyle}
" . (count($patternLines) > 0 ? "Davranis Gozlemleri:\n" . implode("\n", $patternLines) : "") . "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

    return $card;
}
