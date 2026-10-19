<?php
/**
 * Aibuke Öğretmen Paneli — Ana Sayfa
 * Konu giriş, geçmiş, sınıf takibi
 */
require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();
$ogretmen   = getOgretmenInfo();
$schoolId   = $ogretmen['school_id'];
$ogretmenId = $ogretmen['id'];

// sinif_ogretmeni bilgisini her zaman DB'den güncel al
$stmt = $pdo->prepare("SELECT sinif_ogretmeni FROM staff WHERE id = ?");
$stmt->execute([$ogretmenId]);
$ogretmen['sinif_ogretmeni'] = $stmt->fetchColumn() ?: null;
$_SESSION['ogretmen_sinif_ogretmeni'] = $ogretmen['sinif_ogretmeni'];

$mesaj = '';
$hata  = '';

// Sınıf listesi
$stmt = $pdo->prepare("SELECT DISTINCT class FROM students WHERE school_id = ? ORDER BY class");
$stmt->execute([$schoolId]);
$siniflar = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ── POST İşlemleri ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'kaydet') {
    $sinif      = trim($_POST['sinif'] ?? '');
    $tarih      = trim($_POST['ders_tarihi'] ?? '');
    $baslik     = trim($_POST['konu_basligi'] ?? '');
    $detay      = trim($_POST['konu_detay'] ?? '');
    $kazanimlar = trim($_POST['kazanimlar'] ?? '');
    $evOdevi    = trim($_POST['ev_odevi'] ?? '');
    if ($sinif && $tarih && $baslik) {
        $pdo->prepare("INSERT INTO islenen_konular (ogretmen_id, school_id, ders, sinif, ders_tarihi, konu_basligi, konu_detay, kazanimlar, ev_odevi) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$ogretmenId, $schoolId, $ogretmen['ders'], $sinif, $tarih, $baslik, $detay, $kazanimlar, $evOdevi]);
        $mesaj = 'Konu başarıyla kaydedildi.';
    } else {
        $hata = 'Sınıf, tarih ve konu başlığı zorunludur.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sil') {
    $pdo->prepare("DELETE FROM islenen_konular WHERE id=? AND ogretmen_id=?")->execute([(int)($_POST['id'] ?? 0), $ogretmenId]);
    $mesaj = 'Konu silindi.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'guncelle') {
    $id = (int)($_POST['id'] ?? 0);
    $sinif = trim($_POST['sinif'] ?? ''); $tarih = trim($_POST['ders_tarihi'] ?? '');
    $baslik = trim($_POST['konu_basligi'] ?? ''); $detay = trim($_POST['konu_detay'] ?? '');
    $kazanimlar = trim($_POST['kazanimlar'] ?? ''); $evOdevi = trim($_POST['ev_odevi'] ?? '');
    if ($id && $sinif && $tarih && $baslik) {
        $pdo->prepare("UPDATE islenen_konular SET sinif=?, ders_tarihi=?, konu_basligi=?, konu_detay=?, kazanimlar=?, ev_odevi=? WHERE id=? AND ogretmen_id=?")
            ->execute([$sinif, $tarih, $baslik, $detay, $kazanimlar, $evOdevi, $id, $ogretmenId]);
        $mesaj = 'Konu güncellendi.';
    }
}

// ── İstatistikler ───────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) FROM islenen_konular WHERE ogretmen_id=? AND ders_tarihi=CURDATE()");
$stmt->execute([$ogretmenId]); $bugunSayisi = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM islenen_konular WHERE ogretmen_id=? AND ders_tarihi >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute([$ogretmenId]); $haftaSayisi = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM islenen_konular WHERE ogretmen_id=?");
$stmt->execute([$ogretmenId]); $toplamSayisi = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT sinif) FROM islenen_konular WHERE ogretmen_id=?");
$stmt->execute([$ogretmenId]); $sinifSayisi = (int)$stmt->fetchColumn();

// ── Geçmiş Konular ─────────────────────────────────────────────────────────
$filtreTarih = $_GET['tarih'] ?? '';
$sql = "SELECT * FROM islenen_konular WHERE ogretmen_id = ?";
$params = [$ogretmenId];
if ($filtreTarih) { $sql .= " AND ders_tarihi = ?"; $params[] = $filtreTarih; }
$sql .= " ORDER BY ders_tarihi DESC, created_at DESC LIMIT 30";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$gecmisKonular = $stmt->fetchAll();

// Düzenleme
$duzKonu = null;
if (isset($_GET['duzenle'])) {
    $stmt = $pdo->prepare("SELECT * FROM islenen_konular WHERE id=? AND ogretmen_id=?");
    $stmt->execute([(int)$_GET['duzenle'], $ogretmenId]);
    $duzKonu = $stmt->fetch();
}

$sayfa = $_GET['sayfa'] ?? 'gir';
$currentPage = 'panel.php';
$bugun = date('Y-m-d');
$dun = date('Y-m-d', strtotime('-1 day'));
$gunler = ['Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi','Pazar'];

// Sınıf öğretmeni verileri
$sinifOgretmeni = $ogretmen['sinif_ogretmeni'];
$sinifOgrenciler = [];
$sinifStats = [];
$sinifKonular = [];
$sinifUyarilar = [];
$sinifIslenenKonular = [];
if ($sinifOgretmeni && $sayfa === 'sinifim') {
    $stmt = $pdo->prepare("
        SELECT s.id, s.first_name, s.last_name, s.class,
               sp.exam_type, sp.persona_mode, sp.learning_style,
               (SELECT COUNT(*) FROM aibuke_daily_usage du WHERE du.student_id = s.id AND du.usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as haftalik_mesaj,
               (SELECT COUNT(*) FROM aibuke_daily_usage du WHERE du.student_id = s.id AND du.usage_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as aylik_aktif_gun,
               (SELECT ROUND(AVG(sp2.strength_score)) FROM aibuke_student_progress sp2 WHERE sp2.student_id = s.id) as ort_basari
        FROM students s
        LEFT JOIN aibuke_student_profiles sp ON s.id = sp.student_id
        WHERE s.school_id = ? AND s.class = ?
        ORDER BY s.first_name, s.last_name
    ");
    $stmt->execute([$schoolId, $sinifOgretmeni]);
    $sinifOgrenciler = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT du.student_id) as aktif_ogrenci,
               SUM(du.message_count) as toplam_mesaj,
               ROUND(AVG(du.message_count), 1) as ort_mesaj
        FROM aibuke_daily_usage du
        JOIN students s ON du.student_id = s.id
        WHERE s.school_id = ? AND s.class = ? AND du.usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$schoolId, $sinifOgretmeni]);
    $sinifStats = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT sp2.subject, ROUND(AVG(sp2.strength_score)) as ort_skor,
               SUM(sp2.questions_solved) as toplam_soru,
               SUM(sp2.correct_answers) as toplam_dogru
        FROM aibuke_student_progress sp2
        JOIN students s ON sp2.student_id = s.id
        WHERE s.school_id = ? AND s.class = ?
        GROUP BY sp2.subject
        ORDER BY ort_skor ASC
        LIMIT 10
    ");
    $stmt->execute([$schoolId, $sinifOgretmeni]);
    $sinifKonular = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT sa.*, CONCAT(s.first_name, ' ', s.last_name) as ogrenci_adi
        FROM aibuke_sentiment_alerts sa
        JOIN students s ON sa.student_id = s.id
        WHERE s.school_id = ? AND s.class = ? AND sa.is_resolved = 0
        ORDER BY sa.created_at DESC
    ");
    $stmt->execute([$schoolId, $sinifOgretmeni]);
    $sinifUyarilar = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT ik.ders, ik.konu_basligi, ik.konu_detay, ik.kazanimlar, ik.ev_odevi, ik.ders_tarihi,
               st.full_name as ogretmen_adi
        FROM islenen_konular ik
        JOIN staff st ON ik.ogretmen_id = st.id
        WHERE ik.school_id = ? AND ik.sinif = ?
          AND ik.ders_tarihi >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY ik.ders_tarihi DESC, ik.ders
    ");
    $stmt->execute([$schoolId, $sinifOgretmeni]);
    $sinifIslenenKonular = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana Panel — <?= htmlspecialchars($ogretmen['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    :root{
        --bg:#07071a; --surface:rgba(16,16,42,0.72); --surface-solid:#10102a;
        --surface-hover:rgba(99,102,241,0.07);
        --border:rgba(99,102,241,0.11); --border-solid:#23234a; --border-hover:rgba(99,102,241,0.22);
        --text:#e9eaf6; --text-soft:#c4c7e0; --muted:#8892b0;
        --primary:#6366f1; --primary-soft:rgba(99,102,241,0.12); --primary-glow:rgba(99,102,241,0.25);
        --accent:#818cf8; --accent-soft:rgba(129,140,248,0.1);
        --success:#34d399; --success-soft:rgba(52,211,153,0.1);
        --error:#f87171; --error-soft:rgba(248,113,113,0.1);
        --warning:#fbbf24; --warning-soft:rgba(251,191,36,0.1);
        --gradient:linear-gradient(135deg,#6366f1 0%,#8b5cf6 60%,#a78bfa 100%);
        --r-sm:8px; --r-md:12px; --r-lg:16px; --r-xl:20px;
        --t-base:0.2s ease; --t-fast:0.15s ease;
        --shadow-primary:0 4px 20px rgba(99,102,241,0.3);
    }
    body{
        font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;
        background:var(--bg); color:var(--text); min-height:100vh; line-height:1.5;
        background-image:
            radial-gradient(ellipse 100% 60% at 50% -5%,rgba(99,102,241,0.09) 0%,transparent 55%),
            radial-gradient(ellipse 60% 50% at 95% 95%,rgba(139,92,246,0.05) 0%,transparent 50%);
        background-attachment:fixed;
    }

    /* ── Layout ─── */
    .main{margin-left:260px;padding:32px 36px;min-height:100vh}

    /* ── Toast ─── */
    .toast{padding:14px 18px;border-radius:var(--r-lg);font-size:13.5px;font-weight:500;
        margin-bottom:22px;display:flex;align-items:center;gap:10px;
        animation:toastIn 0.35s cubic-bezier(0.16,1,0.3,1)}
    .toast i{font-size:15px;flex-shrink:0}
    .toast-success{background:var(--success-soft);border:1px solid rgba(52,211,153,0.2);color:var(--success)}
    .toast-error{background:var(--error-soft);border:1px solid rgba(248,113,113,0.2);color:var(--error)}
    @keyframes toastIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

    /* ── Page Header ─── */
    .page-header{margin-bottom:28px}
    .page-header h1{font-size:22px;font-weight:700;letter-spacing:-0.4px;display:flex;align-items:center;gap:10px}
    .page-header h1 i{color:var(--primary);font-size:17px;padding:8px;background:var(--primary-soft);border-radius:var(--r-sm);flex-shrink:0}
    .page-header p{color:var(--muted);font-size:13px;margin-top:7px;line-height:1.6}

    /* ── Stats ─── */
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:26px}
    .stat-card{
        background:var(--surface);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
        border:1px solid var(--border);border-radius:var(--r-xl);
        padding:20px 18px;text-align:center;
        transition:transform var(--t-base),border-color var(--t-base),box-shadow var(--t-base);
        position:relative;overflow:hidden;
    }
    .stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;
        background:var(--gradient);opacity:0;transition:opacity var(--t-base)}
    .stat-card:hover{transform:translateY(-3px);border-color:var(--border-hover);box-shadow:var(--shadow-primary)}
    .stat-card:hover::after{opacity:1}
    .stat-icon{font-size:22px;margin-bottom:10px;color:var(--primary)}
    .stat-val{font-size:30px;font-weight:800;letter-spacing:-1.5px;
        background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
        line-height:1;margin-bottom:5px}
    .stat-lbl{font-size:10.5px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.6px}

    /* ── Plan Banner ─── */
    .plan-banner{
        background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15);
        border-radius:var(--r-xl);padding:20px 22px;margin-bottom:24px;
        position:relative;overflow:hidden;
        backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
    }
    .plan-banner::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--gradient)}
    .plan-banner.tatil{background:rgba(248,113,113,0.06);border-color:rgba(248,113,113,0.15)}
    .plan-banner.tatil::before{background:linear-gradient(90deg,var(--error),#fb7185)}
    .plan-banner.yok{background:rgba(251,191,36,0.06);border-color:rgba(251,191,36,0.15)}
    .plan-banner.yok::before{background:linear-gradient(90deg,var(--warning),#fcd34d)}
    .plan-banner-title{font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;margin-bottom:9px}
    .plan-badge-source{font-size:10px;padding:3px 9px;border-radius:100px;background:var(--primary-soft);color:var(--accent);font-weight:700}
    .plan-banner-konu{font-weight:700;margin-bottom:4px;font-size:14px}
    .plan-banner-detay{color:var(--muted);font-size:12.5px;line-height:1.6;margin-bottom:8px}
    .plan-banner-kazanim{font-size:11px;color:var(--accent);margin-bottom:6px;display:flex;align-items:flex-start;gap:6px}
    .plan-banner-apply{
        padding:10px 18px;background:var(--gradient);border:none;border-radius:var(--r-md);
        color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;
        display:inline-flex;align-items:center;gap:8px;
        box-shadow:var(--shadow-primary);transition:all var(--t-base);
    }
    .plan-banner-apply:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(99,102,241,0.45)}
    .plan-banner-loading{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:13px}
    .spinner-sm{width:16px;height:16px;border:2px solid rgba(255,255,255,0.08);
        border-top-color:var(--primary);border-radius:50%;animation:spin 0.65s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}

    /* ── Cards ─── */
    .card,.form-card{
        background:var(--surface);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
        border:1px solid var(--border);border-radius:var(--r-xl);
        padding:24px;margin-bottom:20px;position:relative;overflow:hidden;
    }
    .form-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--gradient)}
    .card-title{font-size:15px;font-weight:700;display:flex;align-items:center;gap:10px;margin-bottom:18px}
    .card-title i{color:var(--primary);font-size:15px}

    /* ── Forms ─── */
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .form-group{margin-bottom:18px}
    .form-label{font-size:11px;font-weight:700;color:var(--muted);margin-bottom:7px;
        display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:0.6px}
    .form-label i{font-size:11px;color:var(--accent);opacity:0.7}
    .form-label .req{color:var(--error);font-size:14px;margin-left:1px}
    .form-input,.form-select,.form-textarea{
        width:100%;padding:12px 16px;
        background:rgba(0,0,0,0.3);border:1.5px solid var(--border-solid);
        border-radius:var(--r-md);color:var(--text);font-size:14px;
        outline:none;transition:border-color var(--t-base),box-shadow var(--t-base),background var(--t-base);
        font-family:inherit;line-height:1.5;-webkit-appearance:none;appearance:none;
    }
    .form-textarea{resize:vertical;min-height:90px}
    .form-input:focus,.form-select:focus,.form-textarea:focus{
        border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-glow);background:rgba(0,0,0,0.2)}
    .form-input::placeholder,.form-textarea::placeholder{color:rgba(136,146,176,0.4);font-size:13px}
    .form-select{
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='none' stroke='%238892b0' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round' d='M1 1l4 4 4-4'/%3E%3C/svg%3E");
        background-repeat:no-repeat;background-position:right 14px center;padding-right:40px;cursor:pointer;
    }
    .form-select option{background:#10102a;color:var(--text)}
    .form-actions{display:flex;align-items:center;gap:12px;margin-top:10px;flex-wrap:wrap}

    /* ── Buttons ─── */
    .btn{padding:11px 22px;border-radius:var(--r-md);border:none;cursor:pointer;
        font-size:13.5px;font-weight:600;font-family:inherit;
        transition:all var(--t-base);display:inline-flex;align-items:center;justify-content:center;gap:8px;
        text-decoration:none;white-space:nowrap;min-height:44px}
    .btn-primary{background:var(--gradient);color:#fff;box-shadow:var(--shadow-primary)}
    .btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(99,102,241,0.4)}
    .btn-ghost{background:rgba(255,255,255,0.04);color:var(--muted);border:1.5px solid var(--border-solid)}
    .btn-ghost:hover{color:var(--text);background:rgba(255,255,255,0.07);border-color:var(--border-hover)}
    .btn-sm{padding:8px 16px;font-size:12px;border-radius:var(--r-sm);min-height:36px}
    .btn-edit{background:var(--primary-soft);color:var(--accent);border:1px solid rgba(99,102,241,0.15)}
    .btn-edit:hover{background:rgba(99,102,241,0.18);color:var(--primary)}
    .btn-del{background:var(--error-soft);color:var(--error);border:1px solid rgba(248,113,113,0.15)}
    .btn-del:hover{background:rgba(248,113,113,0.18)}

    /* ── Filter Bar ─── */
    .filter-bar{display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap}
    .filter-input{
        padding:10px 14px;background:rgba(0,0,0,0.3);border:1.5px solid var(--border-solid);
        border-radius:var(--r-md);color:var(--text);font-size:13px;font-family:inherit;
        outline:none;transition:border-color var(--t-base),box-shadow var(--t-base);
        min-height:44px;-webkit-appearance:none;appearance:none;
    }
    .filter-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-glow)}
    .filter-input::placeholder{color:rgba(136,146,176,0.4)}

    /* ── Page Tabs ─── */
    .page-tabs{display:flex;gap:2px;margin-bottom:26px;
        border-bottom:1px solid var(--border-solid);padding-bottom:0;overflow-x:auto}
    .page-tab{
        padding:11px 20px;font-size:13px;font-weight:600;
        color:var(--muted);text-decoration:none;
        border-bottom:2px solid transparent;transition:all var(--t-base);
        margin-bottom:-1px;white-space:nowrap;display:flex;align-items:center;gap:7px;
    }
    .page-tab:hover{color:var(--text)}
    .page-tab.active{color:var(--primary);border-bottom-color:var(--primary)}
    .page-tab i{font-size:12px;opacity:0.8}

    /* ── History Timeline ─── */
    .history-card{
        background:var(--surface);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
        border:1px solid var(--border);border-radius:var(--r-xl);overflow:hidden;
    }
    .day-group{padding:0 22px}
    .day-label{
        display:inline-flex;align-items:center;gap:7px;
        font-size:10px;font-weight:800;color:var(--primary);
        text-transform:uppercase;letter-spacing:1.2px;
        background:var(--primary-soft);padding:6px 14px;
        border-radius:100px;border:1px solid rgba(99,102,241,0.15);
        margin-top:22px;margin-bottom:4px;
    }
    .tl-item{
        display:flex;align-items:flex-start;gap:16px;
        padding:18px 0;border-bottom:1px solid rgba(255,255,255,0.03);
    }
    .tl-item:last-child{border-bottom:none}
    .tl-dot-wrap{padding-top:7px;flex-shrink:0}
    .tl-dot{
        width:10px;height:10px;border-radius:50%;
        background:var(--gradient);
        border:2px solid var(--bg);
        box-shadow:0 0 0 3px rgba(99,102,241,0.2),0 0 14px rgba(99,102,241,0.35);
    }
    .tl-body{flex:1;min-width:0}
    .tl-head{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px}
    .tl-sinif{
        font-size:10px;font-weight:800;padding:4px 10px;border-radius:100px;
        background:var(--primary-soft);color:var(--accent);
        border:1px solid rgba(99,102,241,0.15);
    }
    .tl-konu{font-size:14px;font-weight:700;color:var(--text)}
    .tl-detay{font-size:12.5px;color:var(--muted);margin-top:5px;line-height:1.65}
    .tl-kazanim{margin-top:7px;font-size:11px;color:var(--accent);display:flex;align-items:flex-start;gap:6px}
    .tl-odev{
        margin-top:9px;padding:10px 14px;border-radius:var(--r-md);
        background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.12);
        display:flex;align-items:flex-start;gap:8px;font-size:11.5px;color:var(--muted);
    }
    .tl-odev i{color:var(--warning);margin-top:1px;flex-shrink:0}
    .tl-odev strong{color:var(--warning)}
    .tl-actions{display:flex;gap:7px;flex-shrink:0;padding-top:2px;align-items:flex-start}

    /* ── Empty State ─── */
    .empty-state{text-align:center;padding:52px 20px;color:var(--muted)}
    .empty-state i{font-size:48px;opacity:0.12;margin-bottom:14px;display:block}
    .empty-state p{font-size:13.5px;line-height:1.6}

    /* ── Sınıfım ─── */
    .sinif-stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
    .sinif-stat-card{
        background:var(--surface);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
        border:1px solid var(--border);border-radius:var(--r-xl);
        padding:20px 18px;position:relative;overflow:hidden;
    }
    .sinif-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
    .sinif-stat-card.green::before{background:linear-gradient(90deg,var(--success),#6ee7b7)}
    .sinif-stat-card.blue::before{background:var(--gradient)}
    .sinif-stat-card.purple::before{background:linear-gradient(90deg,#8b5cf6,#a78bfa)}
    .sinif-stat-card.amber::before{background:linear-gradient(90deg,var(--warning),#fcd34d)}
    .sstat-icon{font-size:20px;color:var(--primary);margin-bottom:11px}
    .sstat-val{font-size:28px;font-weight:800;letter-spacing:-1px;
        background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
    .sstat-lbl{font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-top:4px}

    /* Student list */
    .student-item{display:flex;align-items:center;gap:14px;
        padding:15px 0;border-bottom:1px solid rgba(255,255,255,0.03)}
    .student-item:last-child{border-bottom:none}
    .student-avatar{
        width:42px;height:42px;border-radius:13px;
        background:var(--gradient);display:flex;align-items:center;justify-content:center;
        font-weight:800;font-size:14px;color:white;flex-shrink:0;
        box-shadow:0 4px 14px rgba(99,102,241,0.3);
    }
    .student-info{flex:1;min-width:0}
    .student-name{font-weight:600;font-size:14px;margin-bottom:3px}
    .student-meta{display:flex;gap:12px;flex-wrap:wrap}
    .student-meta span{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:4px}

    /* Alert cards */
    .alert-card{
        background:rgba(248,113,113,0.06);border:1px solid rgba(248,113,113,0.14);
        border-radius:var(--r-lg);padding:14px 16px;margin-bottom:10px;
        position:relative;overflow:hidden;
    }
    .alert-card::before{content:'';position:absolute;top:0;left:0;width:3px;bottom:0;background:var(--error)}
    .alert-header{display:flex;align-items:center;gap:9px;margin-bottom:7px}
    .alert-dot{width:8px;height:8px;border-radius:50%;background:var(--error);box-shadow:0 0 8px rgba(248,113,113,0.5);flex-shrink:0}
    .alert-name{font-weight:700;font-size:13px}
    .alert-type{font-size:9px;font-weight:800;padding:3px 8px;border-radius:100px;
        background:var(--error-soft);color:var(--error)}
    .alert-msg{font-size:12px;color:var(--muted);line-height:1.5;
        padding:9px 12px;background:rgba(248,113,113,0.05);border-radius:var(--r-sm);margin-top:7px}

    /* Subject bars */
    .subject-bar-item{margin-bottom:14px}
    .subject-bar-header{display:flex;justify-content:space-between;margin-bottom:6px}
    .subject-bar-name{font-size:13px;font-weight:600}
    .subject-bar-score{font-size:11px;color:var(--muted)}
    .subject-bar-track{height:6px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden}
    .subject-bar-fill{height:100%;border-radius:3px;transition:width 0.9s cubic-bezier(0.4,0,0.2,1)}

    /* Topic list */
    .topic-item{display:flex;align-items:flex-start;gap:13px;
        padding:13px 0;border-bottom:1px solid rgba(255,255,255,0.03)}
    .topic-item:last-child{border-bottom:none}
    .topic-dot{width:8px;height:8px;border-radius:50%;background:var(--primary);
        margin-top:5px;flex-shrink:0;box-shadow:0 0 8px rgba(99,102,241,0.5)}
    .topic-content{flex:1;min-width:0}
    .topic-header{display:flex;align-items:center;gap:8px;margin-bottom:3px;flex-wrap:wrap}
    .topic-ders-badge{font-size:9px;font-weight:800;padding:3px 8px;border-radius:100px;
        background:var(--primary-soft);color:var(--accent);border:1px solid rgba(99,102,241,0.12)}
    .topic-title{font-size:13px;font-weight:700}
    .topic-detail{font-size:11.5px;color:var(--muted);line-height:1.55}
    .topic-teacher{font-size:10px;color:var(--muted);margin-top:4px;display:flex;align-items:center;gap:5px}

    /* ── Mobile ─── */
    @media(max-width:768px){
        .main{margin-left:0;padding:76px 16px 90px}
        .stats-grid{grid-template-columns:repeat(2,1fr);gap:10px}
        .sinif-stats-grid{grid-template-columns:repeat(2,1fr);gap:10px}
        .stat-val{font-size:26px}
        .form-row{grid-template-columns:1fr}
        .form-card{padding:18px 16px}
        .filter-bar{flex-direction:column;align-items:stretch}
        .filter-input,.filter-bar .btn{width:100%}
        .tl-item{flex-wrap:wrap;padding:14px 0;gap:8px}
        .tl-dot-wrap{display:none}
        .tl-body{width:100%}
        .tl-actions{width:100%;padding-top:8px;border-top:1px solid rgba(255,255,255,0.04)}
        .tl-actions .btn{flex:1;justify-content:center}
        .tl-actions form{flex:1}
        .tl-actions form .btn{width:100%}
    }
    @media(max-width:480px){
        .stats-grid{gap:8px}
        .stat-card{padding:14px 12px}
        .stat-val{font-size:22px}
    }
    select option{background:#10102a;color:#e9eaf6}
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">

<?php if ($mesaj): ?>
<div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div>
<?php endif; ?>
<?php if ($hata): ?>
<div class="toast toast-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($hata) ?></div>
<?php endif; ?>

<!-- ── Page Tabs ────────────────────────────────────────── -->
<div class="page-tabs">
    <a href="?sayfa=gir" class="page-tab <?= $sayfa === 'gir' ? 'active' : '' ?>"><i class="fas fa-plus"></i> Konu Gir</a>
    <a href="?sayfa=gecmis" class="page-tab <?= $sayfa === 'gecmis' ? 'active' : '' ?>"><i class="fas fa-clock-rotate-left"></i> Geçmiş</a>
    <?php if ($sinifOgretmeni): ?>
    <a href="?sayfa=sinifim" class="page-tab <?= $sayfa === 'sinifim' ? 'active' : '' ?>"><i class="fas fa-users"></i> Sınıfım</a>
    <?php endif; ?>
</div>

<?php if ($sayfa === 'gir'): ?>
<!-- ══════════════ KONU GİRİŞ SAYFASI ══════════════ -->

<div class="page-header">
    <h1><i class="fas <?= $duzKonu ? 'fa-pen-to-square' : 'fa-plus-circle' ?>"></i> <?= $duzKonu ? 'Konu Düzenle' : 'Yeni Konu Gir' ?></h1>
    <p>Bugün işlediğiniz konuyu kaydedin — öğrencilerin günlük planı buna göre şekillenecek.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-val"><?= $bugunSayisi ?></div>
        <div class="stat-lbl">Bugün</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div class="stat-val"><?= $haftaSayisi ?></div>
        <div class="stat-lbl">Bu Hafta</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book"></i></div>
        <div class="stat-val"><?= $toplamSayisi ?></div>
        <div class="stat-lbl">Toplam</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-val"><?= $sinifSayisi ?></div>
        <div class="stat-lbl">Sınıf</div>
    </div>
</div>

<!-- Plan Banner -->
<div class="plan-banner" id="planBanner" style="display:none;">
    <div id="planBannerInner"></div>
</div>

<!-- Form Card -->
<div class="form-card">
    <form method="POST">
        <input type="hidden" name="action" value="<?= $duzKonu ? 'guncelle' : 'kaydet' ?>">
        <?php if ($duzKonu): ?><input type="hidden" name="id" value="<?= $duzKonu['id'] ?>"><?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-calendar"></i> Ders Tarihi <span class="req">*</span></label>
                <input type="date" name="ders_tarihi" class="form-input"
                    value="<?= htmlspecialchars($duzKonu['ders_tarihi'] ?? date('Y-m-d')) ?>"
                    max="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-users"></i> Sınıf <span class="req">*</span></label>
                <?php if ($siniflar): ?>
                <select name="sinif" class="form-select" required>
                    <option value="">Sınıf seçin...</option>
                    <?php foreach ($siniflar as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= ($duzKonu['sinif'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="text" name="sinif" class="form-input" placeholder="Örn: 12/A"
                    value="<?= htmlspecialchars($duzKonu['sinif'] ?? '') ?>" required>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label"><i class="fas fa-book"></i> Konu Başlığı <span class="req">*</span></label>
            <input type="text" name="konu_basligi" class="form-input"
                value="<?= htmlspecialchars($duzKonu['konu_basligi'] ?? '') ?>"
                placeholder="Örn: İkinci Dereceden Denklemler — Diskriminant" required maxlength="200">
        </div>

        <div class="form-group">
            <label class="form-label">Konu Detayı</label>
            <textarea name="konu_detay" class="form-textarea" placeholder="Bu derste ne işlendi? Hangi alt konulara değinildi?"><?= htmlspecialchars($duzKonu['konu_detay'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-bullseye"></i> Kazanımlar</label>
                <textarea name="kazanimlar" class="form-textarea" placeholder="Öğrencinin bu dersten elde etmesi gereken kazanımlar"><?= htmlspecialchars($duzKonu['kazanimlar'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-pen"></i> Ev Ödevi</label>
                <textarea name="ev_odevi" class="form-textarea" placeholder="Sayfa, soru numarası veya açıklama"><?= htmlspecialchars($duzKonu['ev_odevi'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas <?= $duzKonu ? 'fa-check' : 'fa-paper-plane' ?>"></i>
                <?= $duzKonu ? 'Güncelle' : 'Kaydet' ?>
            </button>
            <?php if ($duzKonu): ?>
                <a href="?sayfa=gir" class="btn btn-ghost">İptal</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php elseif ($sayfa === 'gecmis'): ?>
<!-- ══════════════ GEÇMİŞ SAYFASI ══════════════ -->

<div class="page-header">
    <h1><i class="fas fa-clock-rotate-left"></i> Geçmiş Konular</h1>
    <p>Kaydettiğiniz tüm konular burada listelenir.</p>
</div>

<form method="GET" class="filter-bar">
    <input type="hidden" name="sayfa" value="gecmis">
    <span style="font-size:12px;color:var(--muted);font-weight:600;">Filtre:</span>
    <input type="date" name="tarih" class="filter-input" value="<?= htmlspecialchars($filtreTarih) ?>" max="<?= date('Y-m-d') ?>">
    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filtrele</button>
    <?php if ($filtreTarih): ?>
        <a href="?sayfa=gecmis" class="btn btn-sm btn-ghost">Temizle</a>
    <?php endif; ?>
</form>

<div class="history-card">
    <?php if (empty($gecmisKonular)): ?>
        <div class="empty-state">
            <i class="fas fa-book-open"></i>
            <p>Henüz konu girilmemiş<?= $filtreTarih ? ' (bu tarihe ait kayıt yok)' : '' ?>.</p>
        </div>
    <?php else: ?>
        <?php
        $grouped = [];
        foreach ($gecmisKonular as $k) { $grouped[$k['ders_tarihi']][] = $k; }
        foreach ($grouped as $tarih => $konular):
            $gunAdi = $gunler[(int)date('N', strtotime($tarih)) - 1];
            $tarihStr = date('d M Y', strtotime($tarih));
            $gunLabel = $tarih === $bugun ? 'Bugün' : ($tarih === $dun ? 'Dün' : $gunAdi);
        ?>
        <div class="day-group">
            <div class="day-label"><i class="fas fa-calendar"></i> <?= $gunLabel ?>, <?= $tarihStr ?></div>
            <?php foreach ($konular as $k): ?>
            <div class="tl-item">
                <div class="tl-dot-wrap"><div class="tl-dot"></div></div>
                <div class="tl-body">
                    <div class="tl-head">
                        <span class="tl-sinif"><?= htmlspecialchars($k['sinif']) ?></span>
                        <span class="tl-konu"><?= htmlspecialchars($k['konu_basligi']) ?></span>
                    </div>
                    <?php if ($k['konu_detay']): ?>
                        <div class="tl-detay"><?= nl2br(htmlspecialchars(mb_substr($k['konu_detay'], 0, 150))) ?><?= mb_strlen($k['konu_detay']) > 150 ? '…' : '' ?></div>
                    <?php endif; ?>
                    <?php if ($k['kazanimlar']): ?>
                        <div class="tl-kazanim"><i class="fas fa-bullseye"></i> <span><?= htmlspecialchars(mb_substr($k['kazanimlar'], 0, 100)) ?></span></div>
                    <?php endif; ?>
                    <?php if ($k['ev_odevi']): ?>
                        <div class="tl-odev">
                            <i class="fas fa-pen"></i>
                            <div><strong>Ev Ödevi:</strong> <?= htmlspecialchars(mb_substr($k['ev_odevi'], 0, 100)) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="tl-actions">
                    <a href="?sayfa=gir&duzenle=<?= $k['id'] ?>" class="btn btn-sm btn-edit" title="Düzenle"><i class="fas fa-pen"></i></a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Silmek istediğinizden emin misiniz?')">
                        <input type="hidden" name="action" value="sil">
                        <input type="hidden" name="id" value="<?= $k['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-del" title="Sil"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php elseif ($sayfa === 'sinifim' && $sinifOgretmeni): ?>
<!-- ══════════════ SINIFIM SAYFASI ══════════════ -->

<div class="page-header">
    <h1><i class="fas fa-users"></i> Sınıfım — <?= htmlspecialchars($sinifOgretmeni) ?></h1>
    <p>Sınıf öğretmeni olarak öğrencilerinizin Aibüke istatistiklerini buradan takip edebilirsiniz.</p>
</div>

<!-- Sınıf İstatistikleri -->
<div class="sinif-stats-grid">
    <div class="sinif-stat-card blue">
        <div class="sstat-icon"><i class="fas fa-users"></i></div>
        <div class="sstat-val"><?= count($sinifOgrenciler) ?></div>
        <div class="sstat-lbl">Toplam Öğrenci</div>
    </div>
    <div class="sinif-stat-card green">
        <div class="sstat-icon" style="color:var(--success);"><i class="fas fa-chart-line"></i></div>
        <div class="sstat-val" style="background:none;-webkit-text-fill-color:var(--success);"><?= (int)($sinifStats['aktif_ogrenci'] ?? 0) ?></div>
        <div class="sstat-lbl">Aktif (7 Gün)</div>
    </div>
    <div class="sinif-stat-card purple">
        <div class="sstat-icon" style="color:var(--accent);"><i class="fas fa-comment"></i></div>
        <div class="sstat-val" style="background:none;-webkit-text-fill-color:var(--accent);"><?= (int)($sinifStats['toplam_mesaj'] ?? 0) ?></div>
        <div class="sstat-lbl">Haftalık Mesaj</div>
    </div>
    <div class="sinif-stat-card <?= count($sinifUyarilar) > 0 ? 'amber' : 'green' ?>">
        <div class="sstat-icon" style="color:var(--error);"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="sstat-val" style="background:none;-webkit-text-fill-color:<?= count($sinifUyarilar) > 0 ? 'var(--error)' : 'var(--success)' ?>;"><?= count($sinifUyarilar) ?></div>
        <div class="sstat-lbl">Uyarı</div>
    </div>
</div>

<?php if (!empty($sinifUyarilar)): ?>
<!-- Duygu Uyarıları -->
<?php foreach ($sinifUyarilar as $u):
    $trigger = json_decode($u['trigger_messages'] ?? '{}', true);
    $alertLabel = $trigger['label'] ?? '';
    $alertMsg = $trigger['message'] ?? '';
    $typeLabels = ['red_flag' => 'Uyarı', 'gold_flag' => 'Takip', 'critical' => 'Kritik'];
    $typeName = $typeLabels[$u['alert_type']] ?? $u['alert_type'];
?>
<div class="alert-card">
    <div class="alert-header">
        <div class="alert-dot"></div>
        <span class="alert-name"><?= htmlspecialchars($u['ogrenci_adi']) ?></span>
        <span class="alert-type"><?= $typeName ?></span>
        <span style="font-size:10px;color:var(--muted);margin-left:auto;"><?= date('d.m H:i', strtotime($u['created_at'])) ?></span>
    </div>
    <?php if ($alertLabel): ?>
    <div style="font-size:11px;font-weight:700;color:var(--error);margin-left:16px;margin-bottom:4px;">
        <i class="fas fa-tag"></i> <?= htmlspecialchars($alertLabel) ?>
    </div>
    <?php endif; ?>
    <?php if ($alertMsg): ?>
    <div class="alert-msg">"<?= htmlspecialchars($alertMsg) ?>"</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($sinifKonular)): ?>
<!-- Konu Başarı -->
<div class="card">
    <div class="card-title"><i class="fas fa-chart-bar"></i> Konu Bazlı Sınıf Başarısı</div>
    <?php foreach ($sinifKonular as $kn):
        $skor = (int)($kn['ort_skor'] ?? 0);
        $barColor = $skor >= 70 ? 'var(--success)' : ($skor >= 40 ? 'var(--warning)' : 'var(--error)');
        $toplamSoru = (int)($kn['toplam_soru'] ?? 0);
        $toplamDogru = (int)($kn['toplam_dogru'] ?? 0);
    ?>
    <div class="subject-bar-item">
        <div class="subject-bar-header">
            <span class="subject-bar-name"><?= htmlspecialchars($kn['subject']) ?></span>
            <span class="subject-bar-score"><?= $toplamDogru ?>/<?= $toplamSoru ?> — <strong style="color:<?= $barColor ?>;">%<?= $skor ?></strong></span>
        </div>
        <div class="subject-bar-track">
            <div class="subject-bar-fill" style="width:<?= $skor ?>%;background:<?= $barColor ?>;"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- İşlenen Konular -->
<?php if (!empty($sinifIslenenKonular)): ?>
<div class="card">
    <div class="card-title">
        <i class="fas fa-book"></i> Bu Hafta İşlenen Konular
        <span style="margin-left:auto;font-size:11px;color:var(--muted);font-weight:500;"><?= count($sinifIslenenKonular) ?> konu</span>
    </div>
    <?php
    $grouped = [];
    foreach ($sinifIslenenKonular as $ik) { $grouped[$ik['ders_tarihi']][] = $ik; }
    foreach ($grouped as $tarih => $konular):
        $gunAdi = $gunler[(int)date('N', strtotime($tarih)) - 1];
        $tarihStr = date('d M', strtotime($tarih));
        $gunLabel = $tarih === $bugun ? 'Bugün' : ($tarih === $dun ? 'Dün' : $gunAdi);
    ?>
    <div style="margin-bottom:14px;">
        <div class="day-label" style="margin-top:12px;"><i class="fas fa-calendar"></i> <?= $gunLabel ?>, <?= $tarihStr ?></div>
        <?php foreach ($konular as $ik): ?>
        <div class="topic-item">
            <div class="topic-dot"></div>
            <div class="topic-content">
                <div class="topic-header">
                    <span class="topic-ders-badge"><?= htmlspecialchars($ik['ders']) ?></span>
                    <span class="topic-title"><?= htmlspecialchars($ik['konu_basligi']) ?></span>
                </div>
                <?php if ($ik['konu_detay']): ?>
                    <div class="topic-detail"><?= htmlspecialchars(mb_substr($ik['konu_detay'], 0, 120)) ?><?= mb_strlen($ik['konu_detay']) > 120 ? '…' : '' ?></div>
                <?php endif; ?>
                <div class="topic-teacher"><i class="fas fa-user"></i> <?= htmlspecialchars($ik['ogretmen_adi']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Öğrenci Listesi -->
<div class="card">
    <div class="card-title">
        <i class="fas fa-users"></i> Öğrenciler
        <span style="margin-left:auto;font-size:11px;color:var(--muted);font-weight:500;"><?= count($sinifOgrenciler) ?> öğrenci</span>
    </div>
    <?php if (empty($sinifOgrenciler)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>Bu sınıfta kayıtlı öğrenci bulunamadı.</p>
        </div>
    <?php else: ?>
        <?php foreach ($sinifOgrenciler as $ogr):
            $basari = (int)($ogr['ort_basari'] ?? 0);
            $basariColor = $basari >= 70 ? 'var(--success)' : ($basari >= 40 ? 'var(--warning)' : ($basari > 0 ? 'var(--error)' : 'var(--muted)'));
            $mesajSayi = (int)($ogr['haftalik_mesaj'] ?? 0);
            $initials = mb_substr($ogr['first_name'], 0, 1) . mb_substr($ogr['last_name'], 0, 1);
        ?>
        <div class="student-item">
            <div class="student-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="student-info">
                <div class="student-name"><?= htmlspecialchars($ogr['first_name'] . ' ' . $ogr['last_name']) ?></div>
                <div class="student-meta">
                    <span><i class="fas fa-comment"></i> <?= $mesajSayi ?> mesaj/hafta</span>
                    <span><i class="fas fa-calendar-check"></i> <?= (int)($ogr['aylik_aktif_gun'] ?? 0) ?> aktif gün</span>
                    <?php if ($basari > 0): ?>
                        <span style="color:<?= $basariColor ?>;font-weight:600;"><i class="fas fa-chart-simple"></i> %<?= $basari ?> başarı</span>
                    <?php endif; ?>
                    <?php if ($ogr['learning_style']):
                        $styleNames = ['gorsel'=>'Görsel','isitsel'=>'İşitsel','okuma_yazma'=>'Okuma/Yazma','kinestetik'=>'Kinestetik'];
                    ?>
                        <span style="color:var(--accent);"><i class="fas fa-brain"></i> <?= $styleNames[$ogr['learning_style']] ?? $ogr['learning_style'] ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php endif; ?>
</main>

<script>
// ── Plan Suggestion Banner
(function() {
    const banner = document.getElementById('planBanner');
    const bannerInner = document.getElementById('planBannerInner');

    function fetchPlanSuggestion() {
        const tarihInput = document.querySelector('input[name="ders_tarihi"]');
        const sinifSelect = document.querySelector('select[name="sinif"]');
        if (!banner || !tarihInput || !sinifSelect) return;

        const tarih = tarihInput.value;
        const sinif = sinifSelect.value;
        if (!tarih || !sinif) { banner.style.display = 'none'; return; }

        banner.style.display = 'block';
        banner.className = 'plan-banner';
        bannerInner.innerHTML = '<div class="plan-banner-loading"><div class="spinner-sm"></div> Plan kontrol ediliyor...</div>';

        fetch('/ogretmen/plan_oneri.php?tarih=' + encodeURIComponent(tarih) + '&sinif=' + encodeURIComponent(sinif))
            .then(r => r.json())
            .then(data => {
                if (!data.success) { banner.style.display = 'none'; return; }

                banner.className = 'plan-banner';

                if (data.type === 'tatil') {
                    banner.classList.add('tatil');
                    bannerInner.innerHTML = '<div class="plan-banner-title"><i class="fas fa-fire"></i> ' + esc(data.event_name) + '</div><div style="font-size:12px;color:var(--muted);margin-top:6px;">Bu gün ders işlenmiyor.</div>';
                } else if (data.type === 'donem_disinda') {
                    banner.style.display = 'none';
                } else if (data.type === 'brans_yok') {
                    banner.classList.add('yok');
                    bannerInner.innerHTML = '<div class="plan-banner-title"><i class="fas fa-triangle-exclamation"></i> Branş Uyarı</div><div style="font-size:12px;color:var(--muted);">' + esc(data.mesaj) + '</div>';
                } else if (data.type === 'plan_yok') {
                    banner.classList.add('yok');
                    bannerInner.innerHTML = '<div class="plan-banner-title"><i class="fas fa-calendar-xmark"></i> Plan Bulunamadı</div><span style="font-size:10px;color:var(--muted);">Hafta ' + data.hafta_no + '</span><div style="font-size:12px;color:var(--muted);margin-top:6px;">Bu hafta için yıllık plan henüz girilmemiş.</div>';
                } else if (data.type === 'oneri' && data.oneri) {
                    const o = data.oneri;
                    const sourceLabel = data.source === 'merkezi' ? 'Merkezi' : 'Okul';
                    let kazanimHtml = o.kazanimlar ? '<div class="plan-banner-kazanim"><i class="fas fa-bullseye"></i> ' + esc(o.kazanimlar) + '</div>' : '';
                    let detayHtml = o.konu_detay ? '<div class="plan-banner-detay">' + esc(o.konu_detay.substring(0, 200)) + '</div>' : '';
                    let odevHtml = o.oneri_ev_odevi ? '<div style="margin-top:8px;font-size:11px;color:var(--warning);"><i class="fas fa-pen"></i> <strong>Ev Ödevi:</strong> ' + esc(o.oneri_ev_odevi.substring(0, 100)) + '</div>' : '';
                    bannerInner.innerHTML = '<div class="plan-banner-title"><i class="fas fa-star"></i> Bugünün Planı <span class="plan-badge-source">' + sourceLabel + '</span></div>' +
                        '<div class="plan-banner-konu">' + esc(o.konu_basligi) + '</div>' + detayHtml + kazanimHtml + odevHtml +
                        '<div style="margin-top:14px;"><button class="plan-banner-apply" onclick="applySuggestion(\'' + esc(o.konu_basligi) + '\', \'' + esc((o.konu_detay || '').replace(/'/g, "\\'")) + '\', \'' + esc((o.kazanimlar || '').replace(/'/g, "\\'")) + '\', \'' + esc((o.oneri_ev_odevi || '').replace(/'/g, "\\'")) + '\')"><i class="fas fa-wand-magic-sparkles"></i> Forma Uygula</button></div>';
                }
            })
            .catch(() => { banner.style.display = 'none'; });
    }

    function esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    window.applySuggestion = function(baslik, detay, kazanim, odev) {
        const baslikInput = document.querySelector('input[name="konu_basligi"]');
        const detayInput  = document.querySelector('textarea[name="konu_detay"]');
        const kazanimInput = document.querySelector('textarea[name="kazanimlar"]');
        const odeviInput  = document.querySelector('textarea[name="ev_odevi"]');
        if (baslikInput) baslikInput.value = baslik;
        if (detayInput)  detayInput.value = detay;
        if (kazanimInput) kazanimInput.value = kazanim;
        if (odeviInput)  odeviInput.value = odev;
        banner.style.display = 'none';
    };

    fetchPlanSuggestion();

    const tarihInput = document.querySelector('input[name="ders_tarihi"]');
    const sinifSelect = document.querySelector('select[name="sinif"]');
    if (tarihInput) tarihInput.addEventListener('change', fetchPlanSuggestion);
    if (sinifSelect) sinifSelect.addEventListener('change', fetchPlanSuggestion);
})();

// ── Toast auto dismiss
document.querySelectorAll('.toast').forEach(function(toast) {
    setTimeout(function() {
        toast.style.transition = 'opacity 0.4s, transform 0.4s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        setTimeout(function() { toast.remove(); }, 400);
    }, 3500);
});
</script>
</body>
</html>
