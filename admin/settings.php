<?php
require_once __DIR__ . '/auth.php';
session_start();
requireAdminLogin();
$admin = getAdminInfo();
$schoolId = $admin['school_id'];

// Okul istatistikleri
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT sp.student_id) as total FROM aibuke_student_profiles sp JOIN students s ON sp.student_id = s.id WHERE s.school_id = ?");
$stmt->execute([$schoolId]);
$totalStudents = (int) $stmt->fetchColumn();

// Günlük mesaj limiti
$dailyLimit = 50; // config'den alınabilir

// Red flag count for sidebar
$stmtRF = $pdo->prepare("SELECT COUNT(*) FROM aibuke_sentiment_alerts sa JOIN students s ON sa.student_id = s.id WHERE s.school_id = ? AND sa.is_resolved = 0");
$stmtRF->execute([$schoolId]);
$redFlagCount = (int) $stmtRF->fetchColumn();

// Okul bilgilerini çek (müdür adı dahil)
$stmtSchool = $pdo->prepare("SELECT name, principal_name FROM schools WHERE id = ?");
$stmtSchool->execute([$schoolId]);
$schoolInfo = $stmtSchool->fetch(PDO::FETCH_ASSOC);

// Müdür adı kaydet
$successMsg = '';
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['principal_name'])) {
    $principalName = trim($_POST['principal_name']);
    $stmt = $pdo->prepare("UPDATE schools SET principal_name = ? WHERE id = ?");
    $stmt->execute([$principalName, $schoolId]);
    $schoolInfo['principal_name'] = $principalName;
    $successMsg = 'Müdür adı kaydedildi.';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayarlar — Aibüke Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0f0f23; --surface: #1a1a2e; --border: #2d2d44; --text: #e2e8f0; --muted: #94a3b8; --primary: #6366f1; --success: #22c55e; --error: #ef4444; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }
        .main { padding: 24px 30px; }
        .page-header h1 { font-size: 22px; margin-bottom: 24px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 20px; }
        .card-title { font-size: 15px; font-weight: 600; margin-bottom: 16px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 14px; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--muted); }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; }
        .badge-red { background: rgba(239,68,68,0.15); color: var(--error); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 9px 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 14px; }
        .form-group input:focus { outline: none; border-color: var(--primary); }
        .btn-save { background: var(--primary); color: #fff; border: none; padding: 9px 20px; border-radius: 8px; font-size: 14px; cursor: pointer; }
        .btn-save:hover { opacity: 0.9; }
        .alert-success { background: rgba(34,197,94,0.1); border: 1px solid var(--success); color: var(--success); padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="page-header"><h1>Ayarlar</h1></div>

        <div class="card">
            <div class="card-title">Okul Bilgileri</div>
            <?php if ($successMsg): ?>
                <div class="alert-success"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>
            <div class="info-row"><span class="info-label">Okul</span><span><?= htmlspecialchars($admin['school']) ?></span></div>
            <div class="info-row"><span class="info-label">Yönetici</span><span><?= htmlspecialchars($admin['name']) ?></span></div>
            <div class="info-row"><span class="info-label">Toplam Öğrenci</span><span><?= $totalStudents ?></span></div>
            <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                <form method="POST">
                    <div class="form-group">
                        <label>Okul Müdürü Adı Soyadı <small style="color:var(--muted)">(evraklarda otomatik kullanılır)</small></label>
                        <input type="text" name="principal_name" value="<?= htmlspecialchars($schoolInfo['principal_name'] ?? '') ?>" placeholder="Örn: Ahmet Yılmaz">
                    </div>
                    <button type="submit" class="btn-save">Kaydet</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Sistem Limitleri</div>
            <div class="info-row"><span class="info-label">Günlük mesaj limiti</span><span><?= $dailyLimit ?> mesaj/öğrenci</span></div>
            <div class="info-row"><span class="info-label">Dosya boyut limiti</span><span>5 MB</span></div>
            <div class="info-row"><span class="info-label">Desteklenen formatlar</span><span>JPG, PNG, WEBP</span></div>
        </div>

        <div class="card">
            <div class="card-title">AI Modelleri</div>
            <div class="info-row"><span class="info-label">Soru çözüm (BRAIN)</span><span>Gemini 2.5 Flash</span></div>
            <div class="info-row"><span class="info-label">Motivasyon (HEART)</span><span>MiniMax M2-her</span></div>
            <div class="info-row"><span class="info-label">Analiz (LOGIC)</span><span>MiniMax M2.5</span></div>
        </div>

        <div class="card">
            <div class="card-title">Güvenlik</div>
            <div class="info-row"><span class="info-label">Filtre katmanları</span><span>3 (Blacklist + Konu + AI Guardrail)</span></div>
            <div class="info-row"><span class="info-label">Duygu analizi</span><span>Aktif</span></div>
            <div class="info-row"><span class="info-label">Red Flag sistemi</span><span>Aktif (3 ardışık negatif)</span></div>
        </div>
    </div>
    <script src="/admin/assets/mobile.js?v=<?= filemtime(__DIR__.'/assets/mobile.js') ?>"></script>
</body>
</html>
