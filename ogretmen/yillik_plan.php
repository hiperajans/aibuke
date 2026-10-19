<?php
/**
 * Aibuke - Öğretmen Yıllık Plan Görünümü
 * Okulun/merkezi müfredat planını haftalık matris halinde gösterir.
 */
require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();

$ogretmen = getOgretmenInfo();
$userId   = $ogretmen['id'];
$schoolId = $ogretmen['school_id'];

// Ogretmenin branch_id'sini al
$staffRow = $pdo->prepare("SELECT branch_id, sinif_ogretmeni FROM staff WHERE id=?");
$staffRow->execute([$userId]);
$staffRow = $staffRow->fetch(PDO::FETCH_ASSOC);
$branchId = (int)($staffRow['branch_id'] ?? 0);

// Brans bilgisi
$branchName = '';
if ($branchId) {
    $branchName = $pdo->prepare("SELECT branch_name FROM branches WHERE id=?");
    $branchName->execute([$branchId]);
    $branchName = $branchName->fetchColumn();
}

// Sinif listesi
$siniflar = ['5','6','7','8','9','10','11','12'];

// Filtre parametreleri
$fDonem   = isset($_GET['donem']) ? (int)$_GET['donem'] : 1;
$fSinif   = isset($_GET['sinif']) ? trim($_GET['sinif']) : '';

// Hafta bilgileri
$haftalar = $pdo->prepare("SELECT * FROM yillik_plan_haftalar WHERE (school_id=? OR school_id=0) AND donem=? ORDER BY hafta_no");
$haftalar->execute([$schoolId, $fDonem]);
$haftalar = $haftalar->fetchAll(PDO::FETCH_ASSOC);

// Plan verisi - oncelik: okul plani > merkezi sablon
$plans = [];
if ($branchId && $fSinif) {
    $stmt = $pdo->prepare("
        SELECT m.* FROM yillik_plan_mufredat m
        WHERE m.branch_id=? AND m.sinif=? AND m.donem=?
          AND m.school_id = IFNULL(
            (SELECT MIN(m2.school_id) FROM yillik_plan_mufredat m2
             WHERE m2.branch_id=m.branch_id AND m2.sinif=m.sinif AND m2.donem=m.donem
               AND m2.school_id=? AND m2.hafta_no=m.hafta_no AND m2.gun_no=m.gun_no),
            0
          )
        ORDER BY m.hafta_no, m.gun_no
    ");
    // Once okul ozel plani, yoksa merkezi
    $ozelPlans = [];
    $stmtOz = $pdo->prepare("SELECT * FROM yillik_plan_mufredat WHERE school_id=? AND branch_id=? AND sinif=? AND donem=? ORDER BY hafta_no, gun_no");
    $stmtOz->execute([$schoolId, $branchId, $fSinif, $fDonem]);
    $ozelPlans = $stmtOz->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($ozelPlans)) {
        $plans = $ozelPlans;
    } else {
        $stmtMrk = $pdo->prepare("SELECT * FROM yillik_plan_mufredat WHERE school_id=0 AND branch_id=? AND sinif=? AND donem=? ORDER BY hafta_no, gun_no");
        $stmtMrk->execute([$branchId, $fSinif, $fDonem]);
        $plans = $stmtMrk->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Plan haritasi: hafta_no => [gun_no => plan]
$planMap = [];
foreach ($plans as $p) {
    $planMap[$p['hafta_no']][$p['gun_no']] = $p;
}

// Hafta map: hafta_no => hafta row
$haftaMap = [];
foreach ($haftalar as $h) {
    $haftaMap[$h['hafta_no']] = $h;
}

$gunler = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma'];

// Plan kaynagi bilgisi
$kaynakOkul = !empty($ozelPlans);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yıllık Planım - Aibuke</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #0a0a1a; --surface: rgba(26,26,46,0.85); --surface-solid: #1a1a2e;
            --border: rgba(99,102,241,0.12); --border-solid: #2d2d44;
            --text: #e2e8f0; --muted: #7c8db0;
            --primary: #6366f1; --accent: #a78bfa; --success: #22c55e; --warning: #f59e0b;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); }

        .main { margin-left: 240px; padding: 28px 32px; min-height: 100vh; }

        .page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 22px; font-weight: 700; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--primary); }

        .filter-bar { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-select { padding: 9px 14px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 10px; color: var(--text); font-size: 13px; outline: none; font-family: inherit; cursor: pointer; }
        .form-select:focus { border-color: var(--primary); }

        .branch-tag { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.15); border-radius: 10px; font-size: 13px; font-weight: 600; color: var(--accent); }
        .branch-tag i { font-size: 12px; }

        .source-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 600; }
        .source-school { background: rgba(34,197,94,0.1); color: var(--success); }
        .source-central { background: rgba(99,102,241,0.1); color: var(--primary); }

        .stats-row { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 16px 20px; flex: 1; min-width: 140px; }
        .stat-card .stat-val { font-size: 24px; font-weight: 700; }
        .stat-card .stat-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
        .stat-val.clr-primary { color: var(--primary); }
        .stat-val.clr-accent { color: var(--accent); }
        .stat-val.clr-success { color: var(--success); }

        .weeks-grid { display: flex; flex-direction: column; gap: 12px; }
        .week-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
        .week-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; cursor: pointer; transition: background 0.2s; }
        .week-header:hover { background: rgba(99,102,241,0.04); }
        .week-header-left { display: flex; align-items: center; gap: 12px; }
        .week-no { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #6366f1, #8b5cf6); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0; }
        .week-info { font-size: 12px; color: var(--muted); }
        .week-dates { font-weight: 600; font-size: 13px; color: var(--text); }
        .week-toggle { color: var(--muted); font-size: 13px; transition: transform 0.25s; }
        .week-card.open .week-toggle { transform: rotate(180deg); }

        .week-body { max-height: 0; overflow: hidden; transition: max-height 0.35s ease; }
        .week-card.open .week-body { max-height: 2000px; }

        .day-row { display: flex; gap: 12px; padding: 12px 18px; border-top: 1px solid rgba(255,255,255,0.04); flex-wrap: wrap; }
        .day-label { width: 90px; flex-shrink: 0; font-size: 12px; font-weight: 600; color: var(--muted); padding-top: 4px; }
        .day-content { flex: 1; min-width: 200px; }
        .day-empty { font-size: 12px; color: var(--muted); font-style: italic; padding: 8px 0; }

        .plan-bubble { background: rgba(0,0,0,0.2); border: 1px solid var(--border-solid); border-radius: 12px; padding: 14px 16px; }
        .plan-title { font-size: 14px; font-weight: 600; margin-bottom: 6px; }
        .plan-detail { font-size: 12px; color: var(--muted); line-height: 1.6; margin-bottom: 8px; white-space: pre-line; }
        .plan-meta { display: flex; gap: 12px; flex-wrap: wrap; }
        .plan-meta-item { font-size: 11px; color: var(--muted); display: flex; align-items: center; gap: 4px; }
        .plan-meta-item i { font-size: 10px; color: var(--primary); }
        .plan-kazanimlar { background: rgba(99,102,241,0.06); border-radius: 8px; padding: 8px 12px; font-size: 11px; color: var(--accent); margin-top: 8px; line-height: 1.5; }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 48px; opacity: 0.15; margin-bottom: 16px; display: block; }
        .empty-state p { color: var(--muted); font-size: 14px; margin-bottom: 6px; }
        .empty-state .hint { font-size: 12px; color: rgba(124,141,176,0.6); }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 18px; }
            .stats-row { gap: 8px; }
            .stat-card { padding: 12px 14px; }
            .day-row { flex-direction: column; gap: 4px; }
            .day-label { width: auto; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-book-bookmark"></i> Yıllık Planım</h1>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <?php if ($branchName): ?>
                <span class="branch-tag"><i class="fas fa-chalkboard"></i> <?= htmlspecialchars($branchName) ?></span>
            <?php endif; ?>
            <?php if (!empty($plans)): ?>
                <span class="source-badge <?= $kaynakOkul ? 'source-school' : 'source-central' ?>">
                    <i class="fas fa-<?= $kaynakOkul ? 'school' : 'globe' ?>"></i>
                    <?= $kaynakOkul ? 'Okul Planı' : 'Merkezi Plan' ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtre -->
    <div class="filter-bar">
        <form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <div class="filter-group">
                <label class="filter-label">Dönem</label>
                <select name="donem" class="form-select" onchange="this.form.submit()">
                    <option value="1" <?= $fDonem == 1 ? 'selected' : '' ?>>1. Dönem</option>
                    <option value="2" <?= $fDonem == 2 ? 'selected' : '' ?>>2. Dönem</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Sınıf</label>
                <select name="sinif" class="form-select" onchange="this.form.submit()">
                    <option value="">Sınıf Seçin</option>
                    <?php foreach ($siniflar as $s): ?>
                        <option value="<?= $s ?>" <?= $fSinif === $s ? 'selected' : '' ?>><?= $s ?>. Sınıf</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if (!$branchId): ?>
        <div class="empty-state">
            <i class="fas fa-exclamation-circle"></i>
            <p>Branş bilgisi bulunamadı.</p>
            <p class="hint">Yöneticinizle iletişime geçin.</p>
        </div>
    <?php elseif (!$fSinif): ?>
        <div class="empty-state">
            <i class="fas fa-hand-pointer"></i>
            <p>Plan görüntülemek için sınıf seçin.</p>
        </div>
    <?php elseif (empty($plans)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-xmark"></i>
            <p>Bu sınıf ve dönem için plan bulunamadı.</p>
            <p class="hint"><?= htmlspecialchars($branchName) ?> — <?= $fSinif ?>. Sınıf, <?= $fDonem ?>. Dönem</p>
        </div>
    <?php else: ?>
        <!-- Istatistikler -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-val clr-primary"><?= count($plans) ?></div>
                <div class="stat-label">Planlı Gün</div>
            </div>
            <div class="stat-card">
                <div class="stat-val clr-accent"><?= count(array_filter($haftalar, fn($h) => isset($planMap[$h['hafta_no']]))) ?></div>
                <div class="stat-label">Dolu Hafta</div>
            </div>
            <div class="stat-card">
                <div class="stat-val clr-success"><?= count($haftalar) ?></div>
                <div class="stat-label">Toplam Hafta</div>
            </div>
        </div>

        <!-- Hafta Kartları -->
        <div class="weeks-grid">
            <?php foreach ($haftalar as $h): ?>
                <?php
                $weekPlans = $planMap[$h['hafta_no']] ?? [];
                $hasPlans = !empty($weekPlans);
                ?>
                <div class="week-card <?= $hasPlans ? 'open' : '' ?>">
                    <div class="week-header" onclick="this.parentElement.classList.toggle('open')">
                        <div class="week-header-left">
                            <div class="week-no"><?= $h['hafta_no'] ?></div>
                            <div>
                                <div class="week-dates">
                                    <?= date('d.m.Y', strtotime($h['baslangic_tarihi'])) ?> — <?= date('d.m.Y', strtotime($h['bitis_tarihi'])) ?>
                                </div>
                                <div class="week-info">
                                    <?= $hasPlans ? count($weekPlans) . ' planlı gün' : 'Plan yok' ?>
                                </div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down week-toggle"></i>
                    </div>
                    <div class="week-body">
                        <?php foreach ($gunler as $gunNo => $gunAd): ?>
                            <div class="day-row">
                                <div class="day-label"><?= $gunAd ?></div>
                                <div class="day-content">
                                    <?php if (isset($weekPlans[$gunNo])):
                                        $dp = $weekPlans[$gunNo];
                                    ?>
                                        <div class="plan-bubble">
                                            <div class="plan-title"><?= htmlspecialchars($dp['konu_basligi']) ?></div>
                                            <?php if ($dp['konu_detay']): ?>
                                                <div class="plan-detail"><?= htmlspecialchars($dp['konu_detay']) ?></div>
                                            <?php endif; ?>
                                            <div class="plan-meta">
                                                <?php if ($dp['saat_sayisi']): ?>
                                                    <span class="plan-meta-item"><i class="fas fa-clock"></i> <?= $dp['saat_sayisi'] ?> saat</span>
                                                <?php endif; ?>
                                                <?php if ($dp['kaynak']): ?>
                                                    <span class="plan-meta-item"><i class="fas fa-book"></i> <?= htmlspecialchars($dp['kaynak']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($dp['oneri_ev_odevi']): ?>
                                                    <span class="plan-meta-item"><i class="fas fa-pen"></i> Ödev: <?= htmlspecialchars($dp['oneri_ev_odevi']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($dp['kazanimlar']): ?>
                                                <div class="plan-kazanimlar">
                                                    <i class="fas fa-bullseye" style="margin-right:4px;"></i> <?= htmlspecialchars($dp['kazanimlar']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="day-empty">—</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
