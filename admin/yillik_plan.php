<?php
/**
 * Aibuke Admin - Yıllık Plan Yönetimi
 * Müfredat CRUD + MiniMax AI ile üretim
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/MinimaxAPI.php';
session_start();
requireAdminLogin();
$admin = getAdminInfo();
$schoolId = (int)$admin['school_id'];
$isSuperAdmin = ($admin['role'] === 'super_admin');

$mesaj = '';
$hata = '';

// Okul seçimi — $activeSchool ataması sorgulardan ÖNCE yapılmalı
$activeSchool = $schoolId;
if ($isSuperAdmin && isset($_GET['school_id'])) {
    $activeSchool = (int)$_GET['school_id'];
}

// Red flag count for sidebar
$stmtRF = $pdo->prepare("SELECT COUNT(*) FROM aibuke_sentiment_alerts sa JOIN students s ON sa.student_id = s.id WHERE s.school_id = ? AND sa.is_resolved = 0");
$stmtRF->execute([$activeSchool]);
$redFlagCount = (int) $stmtRF->fetchColumn();

$okullar = [];
if ($isSuperAdmin) {
    $stmt = $pdo->query("SELECT id, name FROM schools ORDER BY name");
    $okullar = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Brans listesi
$branches = $pdo->query("SELECT id, branch_name FROM branches ORDER BY branch_name")->fetchAll(PDO::FETCH_ASSOC);
$siniflar = ['5','6','7','8','9','10','11','12'];

// ── POST: Mufredat Ekle / Guncelle ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_plan') {
    $branchId = (int)($_POST['branch_id'] ?? 0);
    $sinif    = trim($_POST['sinif'] ?? '');
    $donem    = (int)($_POST['donem'] ?? 1);
    $haftaNo  = (int)($_POST['hafta_no'] ?? 0);
    $gunNo    = (int)($_POST['gun_no'] ?? 1);
    $baslik   = trim($_POST['konu_basligi'] ?? '');
    $detay    = trim($_POST['konu_detay'] ?? '');
    $kazanim  = trim($_POST['kazanimlar'] ?? '');
    $evOdevi  = trim($_POST['oneri_ev_odevi'] ?? '');
    $kaynak   = trim($_POST['kaynak'] ?? '');
    $saat     = (int)($_POST['saat_sayisi'] ?? 2);

    if ($branchId && $sinif && $baslik && $haftaNo) {
        // Upsert
        $pdo->prepare("INSERT INTO yillik_plan_mufredat (school_id, branch_id, sinif, donem, hafta_no, gun_no, konu_basligi, konu_detay, kazanimlar, oneri_ev_odevi, kaynak, saat_sayisi, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE konu_basligi=VALUES(konu_basligi), konu_detay=VALUES(konu_detay), kazanimlar=VALUES(kazanimlar), oneri_ev_odevi=VALUES(oneri_ev_odevi), kaynak=VALUES(kaynak), saat_sayisi=VALUES(saat_sayisi)")
            ->execute([$activeSchool, $branchId, $sinif, $donem, $haftaNo, $gunNo, $baslik, $detay, $kazanim, $evOdevi, $kaynak, $saat, $admin['id']]);
        $mesaj = 'Plan kaydedildi.';
    } else {
        $hata = 'Branş, sınıf ve konu başlığı zorunludur.';
    }
}

// ── POST: Tek Sayfa AI Uretimi ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ai_generate_single') {
    $branchId = (int)($_POST['branch_id'] ?? 0);
    $sinif    = trim($_POST['sinif'] ?? '');
    $donem    = (int)($_POST['donem'] ?? 1);
    $haftaNo  = (int)($_POST['hafta_no'] ?? 0);
    $gunNo    = (int)($_POST['gun_no'] ?? 1);

    if (!$branchId || !$sinif || !$haftaNo) {
        $hata = 'Branş, sınıf ve hafta seçimi zorunludur.';
    } else {
        // MiniMax'e istek at
        $branch = $pdo->prepare("SELECT branch_name FROM branches WHERE id=?");
        $branch->execute([$branchId]);
        $branch = $branch->fetchColumn();
        $donemAd = $donem == 1 ? '1. Dönem' : '2. Dönem';

        $prompt = "MEB 2025-2026 $sinif. sınıf $branch dersi $donemAd hafta $haftaNo için bir günlük ders planı oluşturun.

Türkçe yanıt ver. Yanıtı sadece JSON olarak ver, başka hiçbir şey yazma.

JSON formatı:
{
  \"konu_basligi\": \"...\", (max 200 karakter)
  \"konu_detay\": \"...\", (detayli konu anlatimi, alt basliklar dahil)
  \"kazanimlar\": \"...\", (MEB kazanım kodlari ile, ornek: T.5.1.1)
  \"oneri_ev_odevi\": \"...\", (max 500 karakter)
  \"kaynak\": \"...\", (ders kitabi sayfa referansi)
  \"saat_sayisi\": 2
}

Kazanim kodlari MEB resmi formatinda olmali (ornegin: T.5.1.1, M.7.1.1.1, Fen.8.1.2).
Konu detay kisminda ogrenme hedeflerini ve onemli noktalari belirt.";

        $api = new MinimaxAPI();
        $result = $api->generateResponse($prompt, [
            'persona_mode' => 'koc',
            'history' => [],
            'student' => ['school_name' => 'Okul']
        ]);

        if ($result['success'] && !empty($result['text'])) {
            // Try to extract JSON
            $text = trim($result['text']);
            // Remove markdown code blocks if present
            if (preg_match('/```(?:json)?\s*([\s\S]+?)```/', $text, $m)) {
                $text = $m[1];
            }
            $data = json_decode($text, true);
            if (is_array($data)) {
                $baslik  = $data['konu_basligi'] ?? '';
                $detay   = $data['konu_detay'] ?? '';
                $kazanim = $data['kazanimlar'] ?? '';
                $evOdevi = $data['oneri_ev_odevi'] ?? '';
                $kaynak  = $data['kaynak'] ?? '';
                $saat    = (int)($data['saat_sayisi'] ?? 2);

                $pdo->prepare("INSERT INTO yillik_plan_mufredat (school_id, branch_id, sinif, donem, hafta_no, gun_no, konu_basligi, konu_detay, kazanimlar, oneri_ev_odevi, kaynak, saat_sayisi, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE konu_basligi=VALUES(konu_basligi), konu_detay=VALUES(konu_detay), kazanimlar=VALUES(kazanimlar), oneri_ev_odevi=VALUES(oneri_ev_odevi), kaynak=VALUES(kaynak), saat_sayisi=VALUES(saat_sayisi)")
                    ->execute([$activeSchool, $branchId, $sinif, $donem, $haftaNo, $gunNo, $baslik, $detay, $kazanim, $evOdevi, $kaynak, $saat, $admin['id']]);
                $mesaj = 'AI plan üretti ve kaydetti.';
            } else {
                $hata = 'AI yanıtı JSON olarak parse edilemedi.';
            }
        } else {
            $hata = 'AI üretimi başarısız: ' . ($result['error'] ?? 'Bilinmeyen hata');
        }
    }
}

// ── AJAX: Hafta planlarini getir ─────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_plans') {
    header('Content-Type: application/json');
    $branchId = (int)($_GET['branch_id'] ?? 0);
    $sinif    = trim($_GET['sinif'] ?? '');
    $donem    = (int)($_GET['donem'] ?? 1);

    $plans = $pdo->prepare("SELECT * FROM yillik_plan_mufredat WHERE school_id=? AND branch_id=? AND sinif=? AND donem=? ORDER BY hafta_no, gun_no");
    $plans->execute([$activeSchool, $branchId, $sinif, $donem]);
    $plans = $plans->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'plans' => $plans, 'count' => count($plans)]);
    exit;
}

// ── AJAX: Hafta bilgisi getir ─────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_week') {
    header('Content-Type: application/json');
    $haftaNo = (int)($_GET['hafta_no'] ?? 0);
    $donem   = (int)($_GET['donem'] ?? 1);

    $hafta = $pdo->prepare("SELECT * FROM yillik_plan_haftalar WHERE school_id=? AND donem=? AND hafta_no=?");
    $hafta->execute([$activeSchool, $donem, $haftaNo]);
    $hafta = $hafta->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'hafta' => $hafta]);
    exit;
}

// ── Varsayılan secimler ───────────────────────────────────────────
$selectedBranch = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($branches[0]['id'] ?? 0);
$selectedSinif   = isset($_GET['sinif']) ? trim($_GET['sinif']) : '5';
$selectedDonem   = isset($_GET['donem']) ? (int)$_GET['donem'] : 1;

// Mevcut planlar
$plans = [];
if ($selectedBranch && $selectedSinif) {
    $stmt = $pdo->prepare("SELECT * FROM yillik_plan_mufredat WHERE school_id=? AND branch_id=? AND sinif=? AND donem=? ORDER BY hafta_no, gun_no");
    $stmt->execute([$activeSchool, $selectedBranch, $selectedSinif, $selectedDonem]);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Haftalar (mevcut donem)
$haftalar = $pdo->prepare("SELECT * FROM yillik_plan_haftalar WHERE school_id=? AND donem=? ORDER BY hafta_no");
$haftalar->execute([$activeSchool, $selectedDonem]);
$haftalar = $haftalar->fetchAll(PDO::FETCH_ASSOC);

// Plan haritası (hafta_no + gun_no => plan)
$planMap = [];
foreach ($plans as $p) {
    $planMap[$p['hafta_no'] . '_' . $p['gun_no']] = $p;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yıllık Plan — Aibüke Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0f0f23; --surface: #1a1a2e; --border: #2d2d44;
            --text: #e2e8f0; --muted: #94a3b8;
            --primary: #6366f1; --accent: #ec4899;
            --success: #22c55e; --error: #ef4444; --warning: #f59e0b;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }


        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .page-header h1 { font-size: 22px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--primary); }

        .toast { padding: 12px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .toast-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: var(--error); }
        .toast i { font-size: 15px; }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .card-title { font-size: 15px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }

        /* Filter bar */
        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-select, .form-input {
            padding: 9px 14px; background: rgba(0,0,0,0.3); border: 1px solid var(--border);
            border-radius: 8px; color: var(--text); font-size: 14px; outline: none; font-family: inherit;
        }
        .form-select:focus, .form-input:focus { border-color: var(--primary); }

        .btn { padding: 9px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; font-family: inherit; transition: all 0.15s; display: inline-flex; align-items: center; gap: 7px; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: #5558e3; }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
        .btn-ghost:hover { color: var(--text); border-color: var(--text); }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 6px; }
        .btn-ai { background: rgba(34,197,94,0.15); color: var(--success); }
        .btn-ai:hover { background: rgba(34,197,94,0.25); }
        .btn-edit { background: rgba(99,102,241,0.1); color: var(--primary); }
        .btn-del { background: rgba(239,68,68,0.1); color: var(--error); }

        /* Plan Grid */
        .plan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
        .plan-item {
            background: rgba(0,0,0,0.2); border: 1px solid var(--border);
            border-radius: 10px; padding: 16px; position: relative;
            transition: all 0.15s;
        }
        .plan-item:hover { border-color: var(--primary); box-shadow: 0 0 0 1px rgba(99,102,241,0.1); }
        .plan-item.has-content { border-color: rgba(34,197,94,0.3); background: rgba(34,197,94,0.03); }
        .plan-item.empty { opacity: 0.6; }
        .plan-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .plan-hafta { font-size: 13px; font-weight: 700; color: var(--primary); }
        .plan-gun { font-size: 11px; color: var(--muted); margin-top: 2px; }
        .plan-badge { font-size: 10px; padding: 2px 8px; border-radius: 100px; }
        .badge-ai { background: rgba(34,197,94,0.1); color: var(--success); }
        .badge-manuel { background: rgba(99,102,241,0.1); color: var(--primary); }
        .plan-title { font-size: 14px; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .plan-detail { font-size: 12px; color: var(--muted); margin-bottom: 8px; line-height: 1.5; }
        .plan-kazanim { font-size: 11px; color: var(--accent); margin-bottom: 6px; display: flex; align-items: flex-start; gap: 4px; }
        .plan-kazanim i { margin-top: 2px; font-size: 9px; }
        .plan-footer { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px; }
        .plan-odev { font-size: 11px; color: var(--warning); margin-top: 6px; display: flex; align-items: flex-start; gap: 4px; }
        .plan-odev i { margin-top: 2px; font-size: 9px; }
        .plan-kaynak { font-size: 10px; color: var(--muted); margin-top: 4px; }
        .empty-plan { text-align: center; padding: 20px 10px; color: var(--muted); }
        .empty-plan i { font-size: 28px; opacity: 0.2; display: block; margin-bottom: 8px; }
        .empty-plan span { font-size: 12px; }

        /* Week date range chips */
        .week-date { font-size: 11px; color: var(--muted); margin-left: 8px; }

        /* Stats bar */
        .stats-bar { display: flex; gap: 20px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
        .stat-item { font-size: 12px; display: flex; align-items: center; gap: 6px; }
        .stat-item strong { color: var(--primary); }
        .stat-filled { color: var(--success); }
        .stat-empty { color: var(--error); }

        /* School selector */
        .school-selector { display: flex; align-items: center; gap: 10px; }
        .school-selector select { padding: 8px 14px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 13px; }

        /* Loading */
        .loading { display: inline-flex; align-items: center; gap: 8px; }
        .spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.2); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* AI modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 24px; width: 500px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 16px; font-weight: 600; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 20px; cursor: pointer; }
        .form-group { margin-bottom: 14px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-textarea { width: 100%; padding: 10px 14px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 14px; font-family: inherit; outline: none; resize: vertical; min-height: 80px; }
        .form-textarea:focus { border-color: var(--primary); }

        /* Plan edit inline */
        .edit-form { display: none; }
        .edit-form.active { display: block; }
        .plan-item.editing { border-color: var(--primary); }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<?php if ($isSuperAdmin && count($okullar) > 0): ?>
<div style="background:var(--surface);border-bottom:1px solid var(--border);padding:12px 30px;">
    <div class="school-selector">
        <i class="fas fa-school" style="color:var(--muted);"></i>
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <select name="school_id" class="form-select" onchange="this.form.submit()" style="width:auto;">
                <?php foreach ($okullar as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $o['id'] == $activeSchool ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="branch_id" value="<?= $selectedBranch ?>">
            <input type="hidden" name="sinif" value="<?= htmlspecialchars($selectedSinif) ?>">
            <input type="hidden" name="donem" value="<?= $selectedDonem ?>">
        </form>
    </div>
</div>
<?php endif; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-calendar-week"></i> Yıllık Plan Yönetimi</h1>
    </div>

    <?php if ($mesaj): ?><div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
    <?php if ($hata): ?><div class="toast toast-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($hata) ?></div><?php endif; ?>

    <!-- Filter -->
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label class="filter-label">Branş</label>
            <select name="branch_id" onchange="this.form.submit()" class="form-select">
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $b['id'] == $selectedBranch ? 'selected' : '' ?>><?= htmlspecialchars($b['branch_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Sınıf</label>
            <select name="sinif" onchange="this.form.submit()" class="form-select">
                <?php foreach ($siniflar as $s): ?>
                    <option value="<?= $s ?>" <?= $s == $selectedSinif ? 'selected' : '' ?>><?= $s ?>. Sınıf</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Dönem</label>
            <select name="donem" onchange="this.form.submit()" class="form-select">
                <option value="1" <?= $selectedDonem == 1 ? 'selected' : '' ?>>1. Dönem</option>
                <option value="2" <?= $selectedDonem == 2 ? 'selected' : '' ?>>2. Dönem</option>
            </select>
        </div>
    </form>

    <!-- Stats -->
    <?php if (!empty($haftalar)): ?>
    <div class="stats-bar">
        <span class="stat-item"><strong><?= count($haftalar) ?></strong> hafta</span>
        <span class="stat-item"><strong><?= count($plans) ?></strong> plan girildi</span>
        <span class="stat-item"><strong class="stat-filled"><?= count($plans) ?></strong> dolu</span>
        <span class="stat-item"><strong class="stat-empty"><?= count($haftalar) - count($plans) ?></strong> boş</span>
        <span style="margin-left:auto;font-size:11px;color:var(--muted);"><?= $selectedSinif ?>. sınıf <?= $selectedDonem ?>. dönem — <?= htmlspecialchars($branches[$selectedBranch-1]['branch_name'] ?? '') ?></span>
    </div>
    <?php endif; ?>

    <?php if (empty($haftalar)): ?>
    <div class="card">
        <div class="empty-plan">
            <i class="fas fa-calendar-xmark"></i>
            <span>Bu dönem için hafta tanımlanmamış.</span>
            <div style="margin-top:12px;">
                <a href="/admin/takvim_yonetimi.php" class="btn btn-primary btn-sm"><i class="fas fa-calendar"></i> Takvim Yönetimi'nde Haftaları Oluştur</a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Plan Grid -->
    <div class="plan-grid" id="planGrid">
        <?php foreach ($haftalar as $h): ?>
            <?php
            $plan = $planMap[$h['hafta_no'] . '_1'] ?? null;
            $isEmpty = !$plan;
            ?>
            <div class="plan-item <?= $isEmpty ? 'empty' : 'has-content' ?> <?= isset($editingId) && $editingId == $plan['id'] ? 'editing' : '' ?>" data-hafta="<?= $h['hafta_no'] ?>" data-gun="1">
                <?php if ($isEmpty): ?>
                    <div class="plan-header">
                        <div>
                            <div class="plan-hafta">Hafta <?= $h['hafta_no'] ?><span class="week-date"><?= date('d.m', strtotime($h['baslangic_tarihi'])) ?> — <?= date('d.m', strtotime($h['bitis_tarihi'])) ?></span></div>
                            <div class="plan-gun">Pazartesi</div>
                        </div>
                    </div>
                    <div class="empty-plan">
                        <i class="fas fa-plus-circle"></i>
                        <span>Plan girilmedi</span>
                    </div>
                    <div class="plan-footer">
                        <button type="button" class="btn btn-sm btn-primary" onclick="openEditForm(<?= $h['hafta_no'] ?>, 1)"><i class="fas fa-plus"></i> Ekle</button>
                        <button type="button" class="btn btn-sm btn-ai" onclick="generatePlanAI(<?= $h['hafta_no'] ?>, 1)"><i class="fas fa-robot"></i> AI</button>
                    </div>
                <?php else: ?>
                    <div class="plan-header">
                        <div>
                            <div class="plan-hafta">Hafta <?= $h['hafta_no'] ?><span class="week-date"><?= date('d.m', strtotime($h['baslangic_tarihi'])) ?> — <?= date('d.m', strtotime($h['bitis_tarihi'])) ?></span></div>
                            <div class="plan-gun">Pazartesi</div>
                        </div>
                        <span class="plan-badge badge-manuel"><i class="fas fa-pen"></i></span>
                    </div>
                    <div class="plan-title"><?= htmlspecialchars($plan['konu_basligi']) ?></div>
                    <?php if ($plan['konu_detay']): ?>
                        <div class="plan-detail"><?= nl2br(htmlspecialchars(mb_substr($plan['konu_detay'], 0, 120))) ?><?= mb_strlen($plan['konu_detay']) > 120 ? '…' : '' ?></div>
                    <?php endif; ?>
                    <?php if ($plan['kazanimlar']): ?>
                        <div class="plan-kazanim"><i class="fas fa-bullseye"></i> <?= htmlspecialchars(mb_substr($plan['kazanimlar'], 0, 100)) ?></div>
                    <?php endif; ?>
                    <?php if ($plan['oneri_ev_odevi']): ?>
                        <div class="plan-odev"><i class="fas fa-pen-to-square"></i> <?= htmlspecialchars(mb_substr($plan['oneri_ev_odevi'], 0, 80)) ?></div>
                    <?php endif; ?>
                    <?php if ($plan['kaynak']): ?>
                        <div class="plan-kaynak"><i class="fas fa-book"></i> <?= htmlspecialchars($plan['kaynak']) ?></div>
                    <?php endif; ?>
                    <div class="plan-footer">
                        <button type="button" class="btn btn-sm btn-edit" onclick="openEditForm(<?= $h['hafta_no'] ?>, 1, <?= htmlspecialchars(json_encode($plan)) ?>)"><i class="fas fa-pen"></i></button>
                        <button type="button" class="btn btn-sm btn-ai" onclick="generatePlanAI(<?= $h['hafta_no'] ?>, 1)"><i class="fas fa-rotate"></i></button>
                    </div>
                <?php endif; ?>

                <!-- Edit form (hidden by default) -->
                <div class="edit-form" id="editForm_<?= $h['hafta_no'] ?>_1">
                    <form method="POST" onsubmit="return savePlan(this)">
                        <input type="hidden" name="action" value="save_plan">
                        <input type="hidden" name="branch_id" value="<?= $selectedBranch ?>">
                        <input type="hidden" name="sinif" value="<?= htmlspecialchars($selectedSinif) ?>">
                        <input type="hidden" name="donem" value="<?= $selectedDonem ?>">
                        <input type="hidden" name="hafta_no" value="<?= $h['hafta_no'] ?>">
                        <input type="hidden" name="gun_no" value="1">

                        <div style="margin-bottom:10px;">
                            <input type="text" name="konu_basligi" class="form-input" placeholder="Konu başlığı" style="width:100%;margin-bottom:8px;" value="<?= htmlspecialchars($plan['konu_basligi'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <textarea name="konu_detay" class="form-textarea" placeholder="Konu detayı (alt konular, açıklamalar)"><?= htmlspecialchars($plan['konu_detay'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <textarea name="kazanimlar" class="form-textarea" placeholder="Kazanımlar (MEB kodlari ile, örn: T.5.1.1)" style="min-height:50px;"><?= htmlspecialchars($plan['kazanimlar'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <textarea name="oneri_ev_odevi" class="form-textarea" placeholder="Önerilen ev ödevi" style="min-height:50px;"><?= htmlspecialchars($plan['oneri_ev_odevi'] ?? '') ?></textarea>
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-check"></i> Kaydet</button>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="closeEditForm(<?= $h['hafta_no'] ?>, 1)">İptal</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<!-- AI Generate Modal -->
<div class="modal-overlay" id="aiModal">
    <div class="modal">
        <div class="modal-header">
            <span><i class="fas fa-robot" style="color:var(--success);margin-right:8px;"></i> AI ile Plan Üret</span>
            <button class="modal-close" onclick="closeModal('aiModal')">&times;</button>
        </div>
        <form method="POST" id="aiForm">
            <input type="hidden" name="action" value="ai_generate_single">
            <input type="hidden" name="branch_id" id="ai_branch_id" value="<?= $selectedBranch ?>">
            <input type="hidden" name="sinif" id="ai_sinif" value="<?= htmlspecialchars($selectedSinif) ?>">
            <input type="hidden" name="donem" id="ai_donem" value="<?= $selectedDonem ?>">
            <input type="hidden" name="hafta_no" id="ai_hafta_no">
            <input type="hidden" name="gun_no" id="ai_gun_no" value="1">

            <p style="font-size:13px;color:var(--muted);margin-bottom:16px;line-height:1.5;">
                MiniMax M2.7 modeli seçilen hafta için MEB müfredatına uygun bir ders planı üretir.
                Üretilen plan otomatik olarak kaydedilir.
            </p>

            <div style="background:rgba(99,102,241,0.05);border:1px solid rgba(99,102,241,0.1);border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:var(--muted);">
                <strong style="color:var(--primary);">Seçilen:</strong>
                <?= htmlspecialchars($branches[$selectedBranch-1]['branch_name'] ?? '') ?> —
                <?= $selectedSinif ?>. Sınıf —
                <?= $selectedDonem ?>. Dönem —
                <span id="aiHaftaLabel">Hafta ...</span>
            </div>

            <div id="aiStatus" style="display:none;" class="loading">
                <div class="spinner"></div>
                <span>Plan üretiliyor...</span>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn btn-ghost" onclick="closeModal('aiModal')">İptal</button>
                <button type="submit" class="btn btn-ai" id="aiSubmitBtn"><i class="fas fa-robot"></i> Üret ve Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentEditItem = null;

function openEditForm(haftaNo, gunNo, planData = null) {
    // Close any open edit forms
    document.querySelectorAll('.edit-form.active').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.plan-item.editing').forEach(el => el.classList.remove('editing'));

    const form = document.getElementById('editForm_' + haftaNo + '_' + gunNo);
    if (form) {
        form.classList.add('active');
        form.closest('.plan-item').classList.add('editing');
        currentEditItem = form.closest('.plan-item');
        // Focus first input
        const input = form.querySelector('input[name="konu_basligi"]');
        if (input) input.focus();
    }
}

function closeEditForm(haftaNo, gunNo) {
    const form = document.getElementById('editForm_' + haftaNo + '_' + gunNo);
    if (form) {
        form.classList.remove('active');
        form.closest('.plan-item').classList.remove('editing');
    }
}

function savePlan(form) {
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    fetch(window.location.pathname + '?<?= http_build_query($_GET) ?>', {
        method: 'POST',
        body: params
    }).then(r => r.text()).then(() => {
        // Close form and reload
        closeEditForm(formData.get('hafta_no'), formData.get('gun_no'));
        location.reload();
    });
    return false;
}

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function generatePlanAI(haftaNo, gunNo) {
    document.getElementById('ai_hafta_no').value = haftaNo;
    document.getElementById('aiHaftaLabel').textContent = 'Hafta ' + haftaNo + ' — Pazartesi';
    openModal('aiModal');
}

// AI form submit
document.getElementById('aiForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('aiSubmitBtn');
    const status = document.getElementById('aiStatus');
    btn.disabled = true;
    status.style.display = 'flex';

    const formData = new FormData(this);
    fetch(window.location.pathname + '?<?= http_build_query($_GET) ?>', {
        method: 'POST',
        body: formData
    }).then(r => r.text()).then(() => {
        closeModal('aiModal');
        btn.disabled = false;
        status.style.display = 'none';
        location.reload();
    });
});

// Close on outside click
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
});
</script>
</body>
</html>