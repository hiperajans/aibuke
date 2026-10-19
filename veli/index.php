<?php
/**
 * Aibuke - Veli Mini Paneli
 * Token ile erişim: /veli/?t=TOKEN
 * Veli mesaj içeriklerini göremez, sadece özet istatistikler.
 */
require_once __DIR__ . '/../config.php';

$token = trim($_GET['t'] ?? '');
if (!$token || strlen($token) < 20) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Yetkisiz</title><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,sans-serif;background:#0f0f23;color:#e2e8f0;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;text-align:center}.c{max-width:400px}h2{font-size:20px;margin-bottom:12px}p{color:#94a3b8;font-size:14px}</style></head><body><div class="c"><img src="/assets/aibuke-avatar.png" style="width:64px;height:64px;border-radius:50%;margin-bottom:16px;"><h2>Erişim Yetkisi Yok</h2><p>Bu sayfaya erişmek için geçerli bir bağlantıya ihtiyacınız var.</p></div></body></html>';
    exit;
}

$stmt = $pdo->prepare("SELECT pt.*, s.first_name, s.last_name, s.class, s.school_id, s.photo FROM aibuke_parent_tokens pt JOIN students s ON pt.student_id = s.id WHERE pt.token = ? AND pt.is_active = 1");
$stmt->execute([$token]);
$access = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$access) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Geçersiz</title><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,sans-serif;background:#0f0f23;color:#e2e8f0;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;text-align:center}.c{max-width:400px}h2{font-size:20px;margin-bottom:12px}p{color:#94a3b8;font-size:14px}</style></head><body><div class="c"><img src="/assets/aibuke-avatar.png" style="width:64px;height:64px;border-radius:50%;margin-bottom:16px;"><h2>Bağlantı Geçersiz</h2><p>Bu bağlantının süresi dolmuş veya geçersiz olabilir.</p></div></body></html>';
    exit;
}

// KVKK onayı kontrolü
if (!$access['kvkk_consent']) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kvkk_accept'])) {
        $pdo->prepare("UPDATE aibuke_parent_tokens SET kvkk_consent = 1, consent_date = NOW() WHERE id = ?")
            ->execute([$access['id']]);
        $access['kvkk_consent'] = 1;
    } else {
        ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veli Onayı — Aibüke</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: #0a0a1a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex; justify-content: center; align-items: center;
            padding: 20px;
            background-image:
                radial-gradient(ellipse 80% 60% at 50% 0%, rgba(99,102,241,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 100%, rgba(139,92,246,0.08) 0%, transparent 50%);
        }
        .card {
            background: rgba(26,26,46,0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(99,102,241,0.15);
            border-radius: 24px;
            padding: 36px 30px;
            max-width: 440px; width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        .logo-wrap { text-align: center; margin-bottom: 24px; }
        .logo-wrap img { width: 60px; height: 60px; border-radius: 50%; border: 2px solid rgba(99,102,241,0.3); }
        h2 { font-size: 20px; font-weight: 700; margin-bottom: 8px; text-align: center; }
        .desc { font-size: 14px; color: #94a3b8; line-height: 1.6; text-align: center; margin-bottom: 20px; }
        .consent-box {
            background: rgba(99,102,241,0.06);
            border: 1px solid rgba(99,102,241,0.1);
            border-radius: 14px;
            padding: 18px;
            font-size: 13px; line-height: 1.7; color: #94a3b8;
            margin-bottom: 24px;
            max-height: 220px; overflow-y: auto;
        }
        .consent-box::-webkit-scrollbar { width: 4px; }
        .consent-box::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.3); border-radius: 4px; }
        .consent-box strong { color: #e2e8f0; }
        .btn {
            width: 100%; padding: 15px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff; border: none; border-radius: 14px;
            font-size: 15px; font-weight: 600; cursor: pointer;
            transition: all 0.2s; font-family: inherit;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(99,102,241,0.35); }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-wrap"><img src="/assets/aibuke-avatar.png" alt="Aibüke"></div>
        <h2>Veli Bilgilendirme ve Onay</h2>
        <p class="desc">Çocuğunuzun Aibüke Öğretmen platformundaki haftalık özetine erişmek için onayınız gerekmektedir.</p>
        <div class="consent-box">
            <strong>KVKK Aydınlatma Metni</strong><br><br>
            Bu panel aracılığıyla yalnızca aşağıdaki bilgilere erişebilirsiniz:<br>
            • Haftalık çalışma süresi ve çözülen soru sayısı<br>
            • Genel ruh hali trendi (pozitif/nötr/negatif)<br>
            • Güçlü ve gelişim gereken ders alanları<br>
            • Kazanılan rozetler ve başarılar<br><br>
            <strong>Erişemeyeceğiniz bilgiler:</strong><br>
            • Mesaj içerikleri (öğrenci gizliliği)<br>
            • Bireysel soru detayları<br>
            • Detaylı duygu analizi raporları<br><br>
            6698 sayılı KVKK kapsamında, 18 yaş altı öğrencilerin kişisel verilerinin işlenmesi için veli/vasi onayı gerekmektedir.
            "Onaylıyorum" butonuna tıklayarak yukarıdaki bilgilendirmeyi okuduğunuzu ve veri işlenmesine onay verdiğinizi kabul ediyorsunuz.
        </div>
        <form method="POST">
            <input type="hidden" name="kvkk_accept" value="1">
            <button type="submit" class="btn">Okudum, Onaylıyorum</button>
        </form>
    </div>
</body>
</html>
        <?php
        exit;
    }
}

// Son erişim güncelle
$pdo->prepare("UPDATE aibuke_parent_tokens SET last_accessed = NOW() WHERE id = ?")->execute([$access['id']]);

$studentId = $access['student_id'];
$schoolId = $access['school_id'];

// ─── Haftalık İstatistikler ───
$stmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_messages WHERE student_id = ? AND role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute([$studentId]);
$weeklyMessages = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT DATE(created_at)) FROM aibuke_messages WHERE student_id = ? AND role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute([$studentId]);
$activeDays = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT ROUND(AVG(sentiment_score), 2) FROM aibuke_messages WHERE student_id = ? AND role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute([$studentId]);
$avgSentiment = (float) $stmt->fetchColumn();

// Duygu durumu detaylı
if ($avgSentiment > 0.5)      { $moodEmoji = '😄'; $moodText = 'Harika'; $moodColor = '#22c55e'; $moodBg = 'rgba(34,197,94,0.1)'; }
elseif ($avgSentiment > 0.2)  { $moodEmoji = '😊'; $moodText = 'Pozitif'; $moodColor = '#22c55e'; $moodBg = 'rgba(34,197,94,0.08)'; }
elseif ($avgSentiment > -0.2) { $moodEmoji = '😐'; $moodText = 'Normal'; $moodColor = '#f59e0b'; $moodBg = 'rgba(245,158,11,0.08)'; }
elseif ($avgSentiment > -0.5) { $moodEmoji = '😟'; $moodText = 'Endişeli'; $moodColor = '#ef4444'; $moodBg = 'rgba(239,68,68,0.08)'; }
else                          { $moodEmoji = '😢'; $moodText = 'Zor Dönem'; $moodColor = '#ef4444'; $moodBg = 'rgba(239,68,68,0.12)'; }

require_once __DIR__ . '/../includes/PointsManager.php';
$pm = new PointsManager($pdo);
$streak = $pm->calculateStreak($studentId);
$totalPoints = $pm->getTotalPoints($studentId);
$badges = $pm->getStudentBadges($studentId);
$rank = $pm->getStudentRank($studentId, $schoolId);

// Güçlü ve zayıf konular
$stmt = $pdo->prepare("SELECT subject, SUM(correct_answers) as correct, SUM(questions_solved - correct_answers) as wrong FROM aibuke_student_progress WHERE student_id = ? GROUP BY subject ORDER BY wrong DESC LIMIT 5");
$stmt->execute([$studentId]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$strongSubjects = [];
$weakSubjects = [];
foreach ($subjects as $s) {
    $total = $s['correct'] + $s['wrong'];
    if ($total === 0) continue;
    $rate = $s['correct'] / $total;
    if ($rate >= 0.7) $strongSubjects[] = $s['subject'];
    else $weakSubjects[] = $s['subject'];
}

// Son kazanılan rozetler
$stmt = $pdo->prepare("SELECT b.badge_name, b.badge_icon FROM aibuke_student_badges sb JOIN aibuke_badges b ON sb.badge_id = b.id WHERE sb.student_id = ? AND sb.earned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY sb.earned_at DESC");
$stmt->execute([$studentId]);
$newBadges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Günlük aktivite (son 7 gün)
$stmt = $pdo->prepare("SELECT DATE(created_at) as day, COUNT(*) as cnt FROM aibuke_messages WHERE student_id = ? AND role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY day ORDER BY day");
$stmt->execute([$studentId]);
$dailyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
$maxDaily = max(array_column($dailyActivity, 'cnt') ?: [1]);

// Aktiflik yüzdesi
$activityPct = round(($activeDays / 7) * 100);

// Çalışma programı
$stmt = $pdo->prepare("SELECT schedule_type, title, schedule_data FROM aibuke_study_schedules WHERE student_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$studentId]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);
$scheduleItems = [];
if ($schedule) {
    $sd = json_decode($schedule['schedule_data'], true);
    if ($sd) $scheduleItems = $sd;
}

// Günlük plan (bugün veya en son)
$stmt = $pdo->prepare("SELECT plan_date, plan_content, completed, motivation_tip FROM aibuke_daily_plans WHERE student_id = ? ORDER BY plan_date DESC LIMIT 1");
$stmt->execute([$studentId]);
$dailyPlan = $stmt->fetch(PDO::FETCH_ASSOC);
$planItems = [];
if ($dailyPlan && $dailyPlan['plan_content']) {
    $pc = json_decode($dailyPlan['plan_content'], true);
    if ($pc && isset($pc['maddeler'])) $planItems = $pc['maddeler'];
    elseif ($pc && is_array($pc)) $planItems = $pc;
}

// Çalışma grupları
$stmt = $pdo->prepare("
    SELECT g.group_name, g.topic, g.subject, g.expires_at, g.is_active,
           (SELECT COUNT(*) FROM aibuke_group_members WHERE group_id = g.id) as member_count,
           (SELECT COUNT(*) FROM aibuke_group_messages WHERE group_id = g.id AND student_id = ?) as my_messages
    FROM aibuke_study_groups g
    JOIN aibuke_group_members gm ON g.id = gm.group_id
    WHERE gm.student_id = ?
    ORDER BY gm.joined_at DESC
    LIMIT 5
");
$stmt->execute([$studentId, $studentId]);
$studyGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Turnuva sonuçları
$stmt = $pdo->prepare("
    SELECT t.title, t.subject, t.question_count, t.status, t.starts_at,
           tp.score, tp.correct_count, tp.wrong_count, tp.avg_time_seconds, tp.finished,
           (SELECT COUNT(*) FROM aibuke_tournament_participants WHERE tournament_id = t.id) as total_participants,
           (SELECT COUNT(*) + 1 FROM aibuke_tournament_participants
            WHERE tournament_id = t.id AND (score > tp.score OR (score = tp.score AND avg_time_seconds < tp.avg_time_seconds))
           ) as rank_position
    FROM aibuke_tournament_participants tp
    JOIN aibuke_tournaments t ON tp.tournament_id = t.id
    WHERE tp.student_id = ?
    ORDER BY t.starts_at DESC
    LIMIT 5
");
$stmt->execute([$studentId]);
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Bahçe durumu
$stmt = $pdo->prepare("SELECT * FROM aibuke_gardens WHERE student_id = ?");
$stmt->execute([$studentId]);
$garden = $stmt->fetch(PDO::FETCH_ASSOC);
$gardenLevels = [
    1 => ['emoji' => '🏜️', 'name' => 'Kuru Toprak', 'desc' => 'Henüz başlangıçta'],
    2 => ['emoji' => '🌱', 'name' => 'Filizlenmiş', 'desc' => 'İlk adımlar atıldı'],
    3 => ['emoji' => '🌿', 'name' => 'Yeşil Yapraklar', 'desc' => 'Düzenli çalışıyor'],
    4 => ['emoji' => '🌸', 'name' => 'Çiçek Açmış', 'desc' => 'Çok iyi gidiyor'],
    5 => ['emoji' => '🌳', 'name' => 'Kocaman Ağaç', 'desc' => 'Harika performans'],
    6 => ['emoji' => '🏡', 'name' => 'Hayal Bahçesi', 'desc' => 'Şampiyon seviye'],
];
$gardenLevel = $garden ? ($garden['garden_level'] ?: 1) : null;
$gardenInfo = $gardenLevel ? $gardenLevels[$gardenLevel] : null;

// Güvenlik uyarıları
$stmt = $pdo->prepare("
    SELECT alert_type, severity, trigger_message, created_at, is_resolved
    FROM aibuke_safety_alerts
    WHERE student_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY created_at DESC LIMIT 5
");
$stmt->execute([$studentId]);
$safetyAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$safetyTypeLabels = [
    'kendine_zarar' => ['label' => 'Kendine Zarar Riski', 'icon' => '🚨', 'color' => '#ef4444'],
    'siddet'        => ['label' => 'Şiddet İması', 'icon' => '⚠️', 'color' => '#f59e0b'],
    'zarar_gorme'   => ['label' => 'Zarar Görme', 'icon' => '🆘', 'color' => '#ef4444'],
    'zorbalik'      => ['label' => 'Zorbalık / Dışlanma', 'icon' => '💔', 'color' => '#f97316'],
    'kritik'        => ['label' => 'Kritik Durum', 'icon' => '🚨', 'color' => '#ef4444'],
];

// Son 7 günde işlenen konular (öğrencinin sınıfı)
$stmt = $pdo->prepare("
    SELECT ik.ders_tarihi, ik.ders, ik.konu_basligi, ik.ev_odevi, st.full_name as ogretmen
    FROM islenen_konular ik
    JOIN staff st ON ik.ogretmen_id = st.id
    WHERE ik.school_id = ?
      AND ik.sinif = ?
      AND ik.ders_tarihi >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY ik.ders_tarihi DESC, ik.ders ASC
");
$stmt->execute([$access['school_id'], $access['class']]);
$islenenKonular = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haftalık Özet — Aibüke Öğretmen</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0a0a1a; --surface: rgba(26,26,46,0.7); --surface-solid: #1a1a2e;
            --border: rgba(99,102,241,0.12); --text: #e2e8f0; --muted: #7c8db0;
            --primary: #6366f1; --primary-soft: rgba(99,102,241,0.1);
            --success: #22c55e; --error: #ef4444; --warning: #f59e0b;
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa);
        }
        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; padding: 0;
            background-image:
                radial-gradient(ellipse 90% 50% at 50% -10%, rgba(99,102,241,0.15) 0%, transparent 55%),
                radial-gradient(ellipse 60% 40% at 100% 100%, rgba(139,92,246,0.08) 0%, transparent 50%);
        }

        .page { max-width: 480px; margin: 0 auto; padding: 20px 16px 40px; }

        /* Hero */
        .hero {
            text-align: center; padding: 40px 20px 30px;
            position: relative;
        }
        .hero::before {
            content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%);
            width: 200px; height: 200px; border-radius: 50%;
            background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
            filter: blur(40px); pointer-events: none;
        }
        .hero-avatar {
            width: 88px; height: 88px; border-radius: 50%;
            border: 3px solid transparent;
            background-image: linear-gradient(var(--bg), var(--bg)), var(--gradient);
            background-origin: border-box;
            background-clip: content-box, border-box;
            box-shadow: 0 0 30px rgba(99,102,241,0.25), 0 0 60px rgba(139,92,246,0.1);
            margin-bottom: 16px; position: relative;
            object-fit: cover; object-position: center top;
            animation: avatarGlow 3s ease-in-out infinite alternate;
        }
        @keyframes avatarGlow {
            0% { box-shadow: 0 0 20px rgba(99,102,241,0.2), 0 0 40px rgba(139,92,246,0.05); }
            100% { box-shadow: 0 0 30px rgba(99,102,241,0.35), 0 0 60px rgba(139,92,246,0.15); }
        }
        .hero-name {
            font-size: 26px; font-weight: 700; letter-spacing: -0.5px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #e2e8f0 0%, #a78bfa 50%, #818cf8 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
            background-size: 200% 200%;
            animation: nameShimmer 4s ease-in-out infinite;
        }
        @keyframes nameShimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0 0;
            margin-bottom: -10px;
        }
        .top-bar-logo {
            display: flex; align-items: center; gap: 8px;
            text-decoration: none;
        }
        .top-bar-logo img { width: 28px; height: 28px; border-radius: 50%; }
        .top-bar-logo span {
            font-size: 14px; font-weight: 700; color: var(--text);
            letter-spacing: -0.3px;
        }
        .veli-badge {
            font-size: 10px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 1px;
            color: #a78bfa;
            background: rgba(139,92,246,0.12);
            border: 1px solid rgba(139,92,246,0.2);
            border-radius: 100px;
            padding: 5px 14px;
            backdrop-filter: blur(8px);
        }
        .hero .meta {
            font-size: 13px; color: var(--muted);
            display: inline-flex; align-items: center; gap: 8px;
        }
        .hero .meta .dot { width: 4px; height: 4px; border-radius: 50%; background: var(--muted); }

        /* Mood Banner */
        .mood-banner {
            display: flex; align-items: center; gap: 16px;
            padding: 18px 20px; border-radius: 18px;
            margin-bottom: 16px; position: relative; overflow: hidden;
            background: <?= $moodBg ?>;
            border: 1px solid <?= $moodColor ?>22;
        }
        .mood-emoji { font-size: 44px; line-height: 1; }
        .mood-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .mood-value { font-size: 20px; font-weight: 700; color: <?= $moodColor ?>; }
        .mood-sub { font-size: 12px; color: var(--muted); margin-top: 2px; }

        /* Stat Cards */
        .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 16px; }
        .stat {
            background: var(--surface); backdrop-filter: blur(12px);
            border: 1px solid var(--border); border-radius: 16px;
            padding: 18px 16px; text-align: center;
            transition: transform 0.2s;
        }
        .stat:hover { transform: translateY(-2px); }
        .stat-icon { font-size: 20px; margin-bottom: 8px; }
        .stat-val {
            font-size: 28px; font-weight: 700;
            background: var(--gradient); -webkit-background-clip: text;
            -webkit-text-fill-color: transparent; background-clip: text;
            line-height: 1.1;
        }
        .stat-lbl { font-size: 11px; color: var(--muted); margin-top: 4px; font-weight: 500; }

        /* Activity Chart */
        .card {
            background: var(--surface); backdrop-filter: blur(12px);
            border: 1px solid var(--border); border-radius: 18px;
            padding: 20px; margin-bottom: 16px;
        }
        .card-head {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 16px;
        }
        .card-title { font-size: 14px; font-weight: 600; }
        .card-badge {
            font-size: 11px; padding: 3px 10px; border-radius: 20px;
            background: var(--primary-soft); color: var(--primary); font-weight: 600;
        }

        .activity-bars {
            display: flex; align-items: flex-end; gap: 6px; height: 60px;
        }
        .activity-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .activity-bar {
            width: 100%; border-radius: 6px; min-height: 4px;
            background: var(--gradient); opacity: 0.85;
            transition: height 0.5s ease;
        }
        .activity-day { font-size: 10px; color: var(--muted); }

        /* Progress Ring */
        .ring-wrap { display: flex; align-items: center; gap: 20px; }
        .ring-svg { width: 72px; height: 72px; transform: rotate(-90deg); }
        .ring-bg { fill: none; stroke: rgba(99,102,241,0.1); stroke-width: 6; }
        .ring-fill {
            fill: none; stroke: url(#ringGrad); stroke-width: 6;
            stroke-linecap: round;
            transition: stroke-dashoffset 1s ease;
        }
        .ring-text { font-size: 14px; font-weight: 700; }
        .ring-label { font-size: 12px; color: var(--muted); }

        /* Subjects */
        .subject-list { display: flex; flex-direction: column; gap: 8px; }
        .subject-row {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; border-radius: 12px;
            background: rgba(255,255,255,0.02);
        }
        .subject-icon {
            width: 32px; height: 32px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700;
        }
        .subject-icon.strong { background: rgba(34,197,94,0.12); color: var(--success); }
        .subject-icon.weak { background: rgba(239,68,68,0.1); color: var(--error); }
        .subject-name { flex: 1; font-size: 14px; font-weight: 500; }
        .subject-status { font-size: 12px; font-weight: 600; }

        /* Badges */
        .badge-row {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 14px; border-radius: 12px;
            background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.12);
            margin: 4px; font-size: 13px;
        }
        .badge-icon { font-size: 18px; }

        /* Schedule */
        .schedule-list { display: flex; flex-direction: column; gap: 0; position: relative; }
        .schedule-item {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 8px 0; position: relative;
        }
        .schedule-item:not(:last-child)::after {
            content: ''; position: absolute; left: 5px; top: 28px;
            width: 2px; height: calc(100% - 16px);
            background: linear-gradient(180deg, var(--primary), rgba(99,102,241,0.15));
        }
        .schedule-dot {
            width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0;
            background: var(--primary); margin-top: 4px;
            box-shadow: 0 0 8px rgba(99,102,241,0.4);
        }
        .schedule-info { flex: 1; }
        .schedule-topic { font-size: 13px; font-weight: 500; line-height: 1.4; }
        .schedule-time { font-size: 11px; color: var(--muted); margin-top: 2px; display: flex; align-items: center; gap: 4px; }

        /* Garden */
        /* Güvenlik Uyarıları */
        .safety-card {
            background: rgba(239,68,68,0.06);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: 18px; padding: 20px; margin-bottom: 16px;
            position: relative; overflow: hidden;
        }
        .safety-card::before {
            content: ''; position: absolute; top: 0; left: 0;
            width: 4px; height: 100%; background: #ef4444;
        }
        .safety-card-title {
            font-size: 15px; font-weight: 700; color: #ef4444;
            display: flex; align-items: center; gap: 8px; margin-bottom: 14px;
        }
        .safety-alert-row {
            padding: 12px 14px; border-radius: 12px;
            background: rgba(0,0,0,0.15); margin-bottom: 8px;
            border-left: 3px solid;
        }
        .safety-alert-row:last-child { margin-bottom: 0; }
        .safety-alert-head {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 6px;
        }
        .safety-alert-type { font-size: 13px; font-weight: 600; }
        .safety-alert-date { font-size: 11px; color: var(--muted); }
        .safety-alert-msg {
            font-size: 12px; color: var(--muted); line-height: 1.5;
            overflow: hidden; text-overflow: ellipsis;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        }
        .safety-resolved {
            display: inline-block; font-size: 10px; font-weight: 600;
            background: rgba(34,197,94,0.15); color: #22c55e;
            padding: 2px 8px; border-radius: 100px; margin-left: 8px;
        }
        .safety-tip {
            margin-top: 14px; padding: 12px 14px; border-radius: 12px;
            background: rgba(239,68,68,0.08); font-size: 12px; line-height: 1.6;
            color: var(--text);
        }

        /* İşlenen Konular */
        .ik-card {
            background: var(--surface); backdrop-filter: blur(12px);
            border: 1px solid var(--border); border-radius: 18px;
            padding: 20px; margin-bottom: 16px;
            position: relative; overflow: hidden;
        }
        .ik-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, #6366f1, #a78bfa, #22c55e, #6366f1);
            background-size: 300% 100%;
            animation: ikShimmer 4s linear infinite;
        }
        @keyframes ikShimmer {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
        }
        .ik-day-group { margin-bottom: 16px; }
        .ik-day-group:last-child { margin-bottom: 0; }
        .ik-day-label {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 700; color: var(--primary);
            text-transform: uppercase; letter-spacing: 0.8px;
            margin-bottom: 10px;
            background: rgba(99,102,241,0.08);
            padding: 4px 12px; border-radius: 100px;
            border: 1px solid rgba(99,102,241,0.12);
        }
        .ik-day-label i { font-size: 9px; }
        .ik-timeline { position: relative; padding-left: 18px; }
        .ik-timeline::before {
            content: ''; position: absolute; left: 4px; top: 6px; bottom: 6px;
            width: 2px; border-radius: 2px;
            background: linear-gradient(180deg, rgba(99,102,241,0.4), rgba(167,139,250,0.15));
        }
        .ik-item {
            position: relative; padding: 10px 14px; margin-bottom: 8px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(99,102,241,0.06);
            border-radius: 14px;
            transition: all 0.2s;
        }
        .ik-item:hover {
            background: rgba(99,102,241,0.04);
            border-color: rgba(99,102,241,0.15);
            transform: translateX(3px);
        }
        .ik-item:last-child { margin-bottom: 0; }
        .ik-dot {
            position: absolute; left: -18px; top: 14px;
            width: 10px; height: 10px; border-radius: 50%;
            background: var(--primary);
            border: 2px solid var(--bg);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.2), 0 0 8px rgba(99,102,241,0.4);
        }
        .ik-item-head {
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
            margin-bottom: 4px;
        }
        .ik-ders-badge {
            font-size: 10px; font-weight: 700; letter-spacing: 0.3px;
            padding: 3px 10px; border-radius: 100px;
            background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(167,139,250,0.12));
            color: #a78bfa;
            border: 1px solid rgba(139,92,246,0.15);
        }
        .ik-konu { font-size: 13px; font-weight: 600; color: var(--text); }
        .ik-ogretmen {
            font-size: 11px; color: var(--muted);
            display: flex; align-items: center; gap: 4px;
        }
        .ik-ogretmen i { font-size: 9px; color: rgba(99,102,241,0.5); }
        .ik-odev {
            margin-top: 8px; padding: 8px 12px;
            border-radius: 10px;
            background: rgba(245,158,11,0.06);
            border: 1px solid rgba(245,158,11,0.1);
            display: flex; align-items: flex-start; gap: 8px;
            font-size: 11px; line-height: 1.5;
        }
        .ik-odev-icon {
            width: 22px; height: 22px; border-radius: 6px; flex-shrink: 0;
            background: rgba(245,158,11,0.12);
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; color: var(--warning);
        }
        .ik-odev-text { color: var(--muted); }
        .ik-odev-text strong { color: var(--warning); }
        .ik-empty {
            text-align: center; padding: 20px; color: var(--muted); font-size: 12px;
        }

        .garden-banner {
            display: flex; align-items: center; gap: 16px;
            padding: 18px 20px; border-radius: 18px;
            background: linear-gradient(135deg, rgba(34,197,94,0.08), rgba(99,102,241,0.06));
            border: 1px solid rgba(34,197,94,0.12); margin-bottom: 16px;
        }
        .garden-big-emoji { font-size: 44px; line-height: 1; }
        .garden-level-name { font-size: 16px; font-weight: 700; }
        .garden-stats-row { display: flex; gap: 14px; margin-top: 4px; font-size: 12px; color: var(--muted); }
        .garden-stats-row span { display: flex; align-items: center; gap: 4px; }

        /* Tournament */
        .tm-row {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 14px; border-radius: 12px;
            background: rgba(255,255,255,0.02); margin-bottom: 6px;
        }
        .tm-rank-badge {
            width: 32px; height: 32px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700;
        }
        .tm-rank-badge.gold { background: rgba(245,158,11,0.15); }
        .tm-rank-badge.silver { background: rgba(148,163,184,0.15); }
        .tm-rank-badge.bronze { background: rgba(180,83,9,0.15); }
        .tm-rank-badge.other { background: var(--primary-soft); color: var(--primary); }
        .tm-info { flex: 1; }
        .tm-info-title { font-size: 13px; font-weight: 500; }
        .tm-info-meta { font-size: 11px; color: var(--muted); margin-top: 2px; }
        .tm-score { font-size: 14px; font-weight: 700; color: var(--primary); }

        /* Group */
        .group-row {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 14px; border-radius: 12px;
            background: rgba(255,255,255,0.02); margin-bottom: 6px;
        }
        .group-icon-box {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            background: var(--primary-soft);
        }
        .group-info { flex: 1; }
        .group-info-name { font-size: 13px; font-weight: 500; }
        .group-info-meta { font-size: 11px; color: var(--muted); margin-top: 2px; }
        .group-msg-count { font-size: 12px; font-weight: 600; color: var(--muted); }

        /* Points — Epic Card */
        .points-card {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 40%, #a855f7 70%, #6366f1 100%);
            background-size: 300% 300%;
            animation: pointsGradient 6s ease infinite;
            border-radius: 22px;
            padding: 32px 24px 28px; text-align: center; margin-bottom: 16px;
            position: relative; overflow: hidden;
            box-shadow: 0 8px 32px rgba(99,102,241,0.3), 0 0 60px rgba(139,92,246,0.15);
        }
        @keyframes pointsGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .points-card::before {
            content: ''; position: absolute; top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: conic-gradient(from 0deg, transparent 0%, rgba(255,255,255,0.08) 10%, transparent 20%);
            animation: pointsSpin 8s linear infinite;
        }
        @keyframes pointsSpin {
            100% { transform: rotate(360deg); }
        }
        .points-card::after {
            content: ''; position: absolute; inset: 0;
            background:
                radial-gradient(circle at 20% 30%, rgba(255,255,255,0.12), transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255,255,255,0.06), transparent 40%);
            pointer-events: none;
        }
        .points-trophy {
            font-size: 40px; line-height: 1; position: relative;
            display: inline-block;
            animation: trophyBounce 2s ease-in-out infinite;
            filter: drop-shadow(0 4px 12px rgba(245,158,11,0.4));
        }
        @keyframes trophyBounce {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-6px) scale(1.08); }
        }
        .points-val {
            font-size: 52px; font-weight: 800; color: #fff; position: relative;
            letter-spacing: -1px; margin: 8px 0 2px;
            text-shadow: 0 2px 20px rgba(255,255,255,0.3);
            animation: pointsPulse 3s ease-in-out infinite;
        }
        @keyframes pointsPulse {
            0%, 100% { text-shadow: 0 2px 20px rgba(255,255,255,0.3); }
            50% { text-shadow: 0 2px 30px rgba(255,255,255,0.6), 0 0 60px rgba(255,255,255,0.15); }
        }
        .points-lbl {
            font-size: 13px; color: rgba(255,255,255,0.65); position: relative;
            text-transform: uppercase; letter-spacing: 2px; font-weight: 600;
        }
        .points-badges {
            margin-top: 16px; position: relative;
            display: flex; justify-content: center; gap: 8px; flex-wrap: wrap;
        }
        .points-badge-item {
            width: 42px; height: 42px; border-radius: 50%;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            animation: badgeFloat 3s ease-in-out infinite;
            cursor: default;
            transition: transform 0.2s;
        }
        .points-badge-item:hover {
            transform: scale(1.2) !important;
            background: rgba(255,255,255,0.2);
        }
        .points-badge-item:nth-child(1) { animation-delay: 0s; }
        .points-badge-item:nth-child(2) { animation-delay: 0.3s; }
        .points-badge-item:nth-child(3) { animation-delay: 0.6s; }
        .points-badge-item:nth-child(4) { animation-delay: 0.9s; }
        .points-badge-item:nth-child(5) { animation-delay: 1.2s; }
        .points-badge-item:nth-child(6) { animation-delay: 1.5s; }
        @keyframes badgeFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        /* Sparkle particles */
        .points-sparkles {
            position: absolute; inset: 0; pointer-events: none; overflow: hidden;
        }
        .points-sparkle {
            position: absolute; width: 3px; height: 3px;
            background: #fff; border-radius: 50%;
            opacity: 0; animation: sparkle 4s linear infinite;
        }
        @keyframes sparkle {
            0% { opacity: 0; transform: translateY(0) scale(0); }
            10% { opacity: 0.8; transform: scale(1); }
            50% { opacity: 0.3; }
            100% { opacity: 0; transform: translateY(-80px) scale(0); }
        }

        /* Footer */
        .footer {
            text-align: center; padding: 24px 0 0;
            border-top: 1px solid var(--border); margin-top: 8px;
        }
        .footer-logo { height: 24px; opacity: 0.4; margin-bottom: 10px; }
        .footer p { font-size: 11px; color: var(--muted); line-height: 1.6; }

        /* Animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate { animation: fadeUp 0.5s ease forwards; opacity: 0; }
        .d1 { animation-delay: 0.1s; } .d2 { animation-delay: 0.2s; }
        .d3 { animation-delay: 0.3s; } .d4 { animation-delay: 0.4s; }
        .d5 { animation-delay: 0.5s; } .d6 { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <svg style="position:absolute;width:0;height:0;">
        <defs>
            <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#6366f1"/>
                <stop offset="100%" stop-color="#a78bfa"/>
            </linearGradient>
        </defs>
    </svg>

    <div class="page">
        <!-- Top Bar -->
        <div class="top-bar animate d1">
            <div class="top-bar-logo">
                <img src="/assets/aibuke-avatar.png" alt="Aibüke">
                <span>Aibüke</span>
            </div>
            <div class="veli-badge"><i class="fas fa-shield-halved" style="margin-right:4px;font-size:9px;"></i> Veli Paneli</div>
        </div>

        <!-- Hero -->
        <div class="hero animate d1">
            <?php
                $photoUrl = !empty($access['photo'])
                    ? '/uploads/photos/' . htmlspecialchars($access['photo'])
                    : '/assets/aibuke-avatar.png';
                $studentFullName = htmlspecialchars(mb_convert_case(mb_strtolower($access['first_name'] . ' ' . $access['last_name'], 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
            ?>
            <img src="<?= $photoUrl ?>" alt="Öğrenci" class="hero-avatar">
            <div class="hero-name"><?= $studentFullName ?></div>
            <div class="meta">
                <span><?= htmlspecialchars($access['class']) ?></span>
                <span class="dot"></span>
                <span><?= date('d M Y') ?></span>
            </div>
        </div>

        <!-- Mood Banner -->
        <div class="mood-banner animate d2">
            <div class="mood-emoji"><?= $moodEmoji ?></div>
            <div>
                <div class="mood-label">Genel Ruh Hali</div>
                <div class="mood-value"><?= $moodText ?></div>
                <div class="mood-sub">Son 7 günün ortalamasına göre</div>
            </div>
        </div>

        <!-- Güvenlik Uyarıları -->
        <?php if (!empty($safetyAlerts)): ?>
        <div class="safety-card animate d2">
            <div class="safety-card-title">
                <span>🚨</span> Güvenlik Uyarıları
                <span style="margin-left:auto;font-size:11px;font-weight:500;color:var(--muted);">Son 30 gün</span>
            </div>
            <?php foreach ($safetyAlerts as $sa):
                $typeInfo = $safetyTypeLabels[$sa['alert_type']] ?? ['label'=>$sa['alert_type'],'icon'=>'⚠️','color'=>'#f59e0b'];
                $dateStr = date('d.m.Y H:i', strtotime($sa['created_at']));
            ?>
            <div class="safety-alert-row" style="border-left-color:<?= $typeInfo['color'] ?>">
                <div class="safety-alert-head">
                    <div class="safety-alert-type" style="color:<?= $typeInfo['color'] ?>">
                        <?= $typeInfo['icon'] ?> <?= $typeInfo['label'] ?>
                        <?php if ($sa['is_resolved']): ?><span class="safety-resolved">Çözüldü</span><?php endif; ?>
                    </div>
                    <div class="safety-alert-date"><?= $dateStr ?></div>
                </div>
                <div class="safety-alert-msg">"<?= htmlspecialchars(mb_substr($sa['trigger_message'], 0, 150, 'UTF-8')) ?><?= mb_strlen($sa['trigger_message'], 'UTF-8') > 150 ? '...' : '' ?>"</div>
            </div>
            <?php endforeach; ?>
            <div class="safety-tip">
                💡 <strong>Önemli:</strong> Bu uyarılar çocuğunuzun mesajlarından otomatik olarak algılanmıştır.
                Okul rehberlik servisi de bilgilendirilmiştir.
                Çocuğunuzla sakin bir şekilde konuşmanızı ve gerekirse profesyonel destek almanızı öneririz.
                <br><strong>Acil durumlarda: 183 ALO Sosyal Destek Hattı</strong>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats animate d3">
            <div class="stat">
                <div class="stat-icon"><i class="fas fa-message"></i></div>
                <div class="stat-val"><?= $weeklyMessages ?></div>
                <div class="stat-lbl">Mesaj / Soru</div>
            </div>
            <div class="stat">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-val"><?= $activeDays ?><span style="font-size:16px;-webkit-text-fill-color:var(--muted);">/7</span></div>
                <div class="stat-lbl">Aktif Gün</div>
            </div>
            <div class="stat">
                <div class="stat-icon"><i class="fas fa-fire"></i></div>
                <div class="stat-val"><?= $streak ?></div>
                <div class="stat-lbl">Kesintisiz Seri 🔥</div>
            </div>
            <div class="stat">
                <div class="stat-icon"><i class="fas fa-ranking-star"></i></div>
                <div class="stat-val">#<?= $rank ?></div>
                <div class="stat-lbl">Okul Sırası</div>
            </div>
        </div>

        <!-- Activity Chart -->
        <?php if (!empty($dailyActivity)): ?>
        <div class="card animate d4">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:6px;"></i> Günlük Aktivite</div>
                <div class="card-badge"><?= $activityPct ?>% aktif</div>
            </div>
            <div class="activity-bars">
                <?php
                $dayNames = ['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'];
                $weekData = [];
                // Son 7 günü doldur
                for ($i = 6; $i >= 0; $i--) {
                    $d = date('Y-m-d', strtotime("-{$i} days"));
                    $weekData[$d] = 0;
                }
                foreach ($dailyActivity as $da) {
                    if (isset($weekData[$da['day']])) $weekData[$da['day']] = $da['cnt'];
                }
                $idx = 0;
                foreach ($weekData as $day => $cnt):
                    $h = $maxDaily > 0 ? max(4, round(($cnt / $maxDaily) * 56)) : 4;
                    $dayNum = (int) date('N', strtotime($day));
                ?>
                <div class="activity-bar-wrap">
                    <div class="activity-bar" style="height:<?= $h ?>px;<?= $cnt === 0 ? 'opacity:0.15;' : '' ?>"></div>
                    <div class="activity-day"><?= $dayNames[$dayNum - 1] ?></div>
                </div>
                <?php $idx++; endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Aktiflik Ring + Info -->
        <div class="card animate d4">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-bullseye" style="color:var(--primary);margin-right:6px;"></i> Haftalık Hedef</div>
            </div>
            <?php
            $circumference = 2 * M_PI * 30;
            $offset = $circumference - ($activityPct / 100) * $circumference;
            ?>
            <div class="ring-wrap">
                <svg class="ring-svg" viewBox="0 0 72 72">
                    <circle class="ring-bg" cx="36" cy="36" r="30"/>
                    <circle class="ring-fill" cx="36" cy="36" r="30"
                            stroke-dasharray="<?= round($circumference, 1) ?>"
                            stroke-dashoffset="<?= round($offset, 1) ?>"/>
                </svg>
                <div>
                    <div class="ring-text"><?= $activeDays ?> / 7 gün aktif</div>
                    <div class="ring-label"><?= $activeDays >= 5 ? 'Harika gidiyor!' : ($activeDays >= 3 ? 'İyi, biraz daha artırabilir.' : 'Daha aktif olması gerekiyor.') ?></div>
                </div>
            </div>
        </div>

        <!-- Dersler -->
        <?php if (!empty($strongSubjects) || !empty($weakSubjects)): ?>
        <div class="card animate d5">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-graduation-cap" style="color:var(--primary);margin-right:6px;"></i> Ders Durumu</div>
            </div>
            <div class="subject-list">
                <?php foreach ($strongSubjects as $s): ?>
                <div class="subject-row">
                    <div class="subject-icon strong"><i class="fas fa-check"></i></div>
                    <div class="subject-name"><?= htmlspecialchars($s) ?></div>
                    <div class="subject-status" style="color:var(--success);">Güçlü</div>
                </div>
                <?php endforeach; ?>
                <?php foreach ($weakSubjects as $s): ?>
                <div class="subject-row">
                    <div class="subject-icon weak"><i class="fas fa-arrow-trend-up"></i></div>
                    <div class="subject-name"><?= htmlspecialchars($s) ?></div>
                    <div class="subject-status" style="color:var(--warning);">Gelişiyor</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Çalışma Programı -->
        <?php if (!empty($scheduleItems) || !empty($planItems)): ?>
        <div class="card animate d5">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-book-open" style="color:var(--primary);margin-right:6px;"></i> Çalışma Programı</div>
                <?php if ($dailyPlan): ?>
                <div class="card-badge"><?= $dailyPlan['completed'] ? 'Tamamlandı' : 'Devam ediyor' ?></div>
                <?php endif; ?>
            </div>

            <?php if (!empty($planItems)): ?>
            <div style="font-size:11px;color:var(--muted);margin-bottom:10px;font-weight:600;text-transform:uppercase;">
                <?= date('d M', strtotime($dailyPlan['plan_date'])) ?> Günlük Plan
            </div>
            <div class="schedule-list">
                <?php foreach ($planItems as $item): ?>
                <div class="schedule-item">
                    <div class="schedule-dot"></div>
                    <div class="schedule-info">
                        <div class="schedule-topic"><?= htmlspecialchars($item['konu'] ?? $item['topic'] ?? '') ?></div>
                        <?php if (!empty($item['sure_dk'])): ?>
                        <div class="schedule-time"><i class="fas fa-clock"></i> <?= $item['sure_dk'] ?> dk</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($scheduleItems) && is_array($scheduleItems)): ?>
            <div style="font-size:11px;color:var(--muted);margin:<?= !empty($planItems) ? '16px' : '0' ?> 0 10px;font-weight:600;text-transform:uppercase;">
                <?= htmlspecialchars($schedule['title'] ?? 'Haftalık Program') ?>
            </div>
            <div class="schedule-list">
                <?php
                // Haftalık program formatı çeşitli olabilir
                $items = $scheduleItems;
                if (isset($items['gunler'])) $items = $items['gunler'];
                if (!is_array($items)) $items = [];
                $shown = 0;
                foreach ($items as $key => $val):
                    if ($shown >= 7) break;
                    if (is_array($val) && isset($val['konu'])):
                ?>
                <div class="schedule-item">
                    <div class="schedule-dot"></div>
                    <div class="schedule-info">
                        <div class="schedule-topic"><?= htmlspecialchars($val['konu'] ?? $val['ders'] ?? '') ?></div>
                        <?php if (!empty($val['sure_dk'])): ?>
                        <div class="schedule-time"><i class="fas fa-clock"></i> <?= $val['sure_dk'] ?> dk</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php $shown++; endif; endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($dailyPlan['motivation_tip'])): ?>
            <div style="margin-top:12px;padding:10px 14px;border-radius:10px;background:rgba(99,102,241,0.06);font-size:12px;color:var(--muted);line-height:1.5;">
                <i class="fas fa-lightbulb" style="color:var(--warning);margin-right:4px;"></i> <?= htmlspecialchars($dailyPlan['motivation_tip']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- İşlenen Konular -->
        <?php if (!empty($islenenKonular)): ?>
        <div class="ik-card animate d5">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-chalkboard-teacher" style="color:var(--primary);margin-right:6px;"></i> Okulda İşlenen Konular</div>
                <div class="card-badge"><?= count($islenenKonular) ?> konu</div>
            </div>
            <?php
            $groupedByDate = [];
            foreach ($islenenKonular as $ik) {
                $groupedByDate[$ik['ders_tarihi']][] = $ik;
            }
            $gunler = ['Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi','Pazar'];
            $bugun = date('Y-m-d');
            $dun = date('Y-m-d', strtotime('-1 day'));
            foreach ($groupedByDate as $tarih => $dersler):
                $gunAdi = $gunler[(int)date('N', strtotime($tarih)) - 1];
                $tarihStr = date('d M', strtotime($tarih));
                $gunLabel = $tarih === $bugun ? 'Bugün' : ($tarih === $dun ? 'Dün' : $gunAdi);
            ?>
            <div class="ik-day-group">
                <div class="ik-day-label">
                    <i class="fas fa-calendar-day"></i> <?= $gunLabel ?>, <?= $tarihStr ?>
                </div>
                <div class="ik-timeline">
                    <?php foreach ($dersler as $d): ?>
                    <div class="ik-item">
                        <div class="ik-dot"></div>
                        <div class="ik-item-head">
                            <span class="ik-ders-badge"><?= htmlspecialchars($d['ders']) ?></span>
                            <span class="ik-konu"><?= htmlspecialchars($d['konu_basligi']) ?></span>
                        </div>
                        <div class="ik-ogretmen"><i class="fas fa-user"></i> <?= htmlspecialchars($d['ogretmen']) ?></div>
                        <?php if ($d['ev_odevi']): ?>
                        <div class="ik-odev">
                            <div class="ik-odev-icon"><i class="fas fa-pen-to-square"></i></div>
                            <div class="ik-odev-text"><strong>Ev Ödevi:</strong> <?= htmlspecialchars(mb_substr($d['ev_odevi'], 0, 120)) ?><?= mb_strlen($d['ev_odevi']) > 120 ? '…' : '' ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Bahçe Durumu -->
        <?php if ($gardenInfo): ?>
        <div class="garden-banner animate d5">
            <div class="garden-big-emoji"><?= $gardenInfo['emoji'] ?></div>
            <div>
                <div class="garden-level-name"><?= $gardenInfo['name'] ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:2px;"><?= $gardenInfo['desc'] ?></div>
                <div class="garden-stats-row">
                    <span>🍃 <?= $garden['total_leaves'] ?? 0 ?> yaprak</span>
                    <span>🌸 <?= $garden['total_flowers'] ?? 0 ?> çiçek</span>
                    <span>🔥 <?= $garden['streak_days'] ?? 0 ?> gün</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Turnuva Sonuçları -->
        <?php if (!empty($tournaments)): ?>
        <div class="card animate d5">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-trophy" style="color:var(--warning);margin-right:6px;"></i> Turnuva Sonuçları</div>
                <div class="card-badge"><?= count($tournaments) ?> turnuva</div>
            </div>
            <?php
            $medals = ['🥇','🥈','🥉'];
            foreach ($tournaments as $t):
                $r = (int) $t['rank_position'];
                $medalEmoji = $medals[$r - 1] ?? $r . '.';
                $rankCls = $r === 1 ? 'gold' : ($r === 2 ? 'silver' : ($r === 3 ? 'bronze' : 'other'));
                $statusText = $t['status'] === 'finished' ? ($t['correct_count'] . '/' . $t['question_count'] . ' doğru') : 'Devam ediyor';
            ?>
            <div class="tm-row">
                <div class="tm-rank-badge <?= $rankCls ?>"><?= $medalEmoji ?></div>
                <div class="tm-info">
                    <div class="tm-info-title"><?= htmlspecialchars($t['title']) ?></div>
                    <div class="tm-info-meta"><?= $statusText ?> · <?= $t['total_participants'] ?> kişi · ort. <?= $t['avg_time_seconds'] ?>s</div>
                </div>
                <div class="tm-score"><?= $t['score'] ?> puan</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Çalışma Grupları -->
        <?php if (!empty($studyGroups)): ?>
        <div class="card animate d5">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-users" style="color:var(--primary);margin-right:6px;"></i> Çalışma Grupları</div>
            </div>
            <?php foreach ($studyGroups as $sg):
                $isActive = $sg['is_active'] && strtotime($sg['expires_at']) > time();
            ?>
            <div class="group-row">
                <div class="group-icon-box"><?= $isActive ? '👥' : '🔒' ?></div>
                <div class="group-info">
                    <div class="group-info-name"><?= htmlspecialchars($sg['group_name']) ?></div>
                    <div class="group-info-meta">
                        <?= htmlspecialchars($sg['topic'] ?: $sg['subject'] ?: '') ?> · <?= $sg['member_count'] ?> üye
                        <?= $isActive ? '' : ' · <span style="color:var(--error)">Süresi dolmuş</span>' ?>
                    </div>
                </div>
                <div class="group-msg-count"><?= $sg['my_messages'] ?> mesaj</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Rozetler -->
        <?php if (!empty($newBadges)): ?>
        <div class="card animate d5">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-award" style="color:var(--warning);margin-right:6px;"></i> Bu Hafta Kazanılan</div>
            </div>
            <?php foreach ($newBadges as $b): ?>
                <span class="badge-row"><span class="badge-icon"><?= $b['badge_icon'] ?></span> <?= htmlspecialchars($b['badge_name']) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Points Card -->
        <div class="points-card animate d6">
            <div class="points-sparkles">
                <div class="points-sparkle" style="left:10%;top:60%;animation-delay:0s;"></div>
                <div class="points-sparkle" style="left:25%;top:80%;animation-delay:0.8s;"></div>
                <div class="points-sparkle" style="left:50%;top:70%;animation-delay:1.6s;"></div>
                <div class="points-sparkle" style="left:70%;top:85%;animation-delay:2.4s;"></div>
                <div class="points-sparkle" style="left:85%;top:65%;animation-delay:3.2s;"></div>
                <div class="points-sparkle" style="left:40%;top:90%;animation-delay:0.4s;"></div>
                <div class="points-sparkle" style="left:60%;top:75%;animation-delay:1.2s;"></div>
                <div class="points-sparkle" style="left:15%;top:85%;animation-delay:2s;"></div>
            </div>
            <div class="points-trophy">🏆</div>
            <div class="points-val"><?= number_format($totalPoints) ?></div>
            <div class="points-lbl">Toplam Puan</div>
            <?php if (!empty($badges)): ?>
            <div class="points-badges">
                <?php foreach (array_slice($badges, 0, 6) as $b): ?>
                    <div class="points-badge-item" title="<?= htmlspecialchars($b['badge_name']) ?>"><?= $b['badge_icon'] ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div style="position:relative;margin-top:14px;font-size:11px;color:rgba(255,255,255,0.5);line-height:1.5;">
                Rozetler çocuğunuzun başarılarını simgeler.<br>Ne kadar çok rozet varsa o kadar başarılı!
            </div>
        </div>

        <!-- Footer -->
        <div class="footer animate d6">
            <img src="/assets/aibuke-logo.png" alt="Aibüke" class="footer-logo"><br>
            <p>
                Aibüke Öğretmen — Veli Bilgilendirme Paneli<br>
                Mesaj içerikleri gizlidir ve bu panelde gösterilmez.<br>
                Sorularınız için okul rehberlik servisi ile iletişime geçebilirsiniz.
            </p>
        </div>
    </div>
</body>
</html>
