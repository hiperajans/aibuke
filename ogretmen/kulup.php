<?php
/**
 * Aibuke Öğretmen - Kulüp Evrakları
 * 30+ MEB onaylı kulüp türü için etkinlik evrakları
 */
require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();
$ogretmen = getOgretmenInfo();
$schoolId = (int)$ogretmen['school_id'];
$ogretmenId = $ogretmen['id'];

$mesaj = '';
$hata = '';

$kulupler = [
    'Afet Hazırlık', 'Astronomi', 'Atatürkçü Düşünce', 'Bilim Fen Teknoloji',
    'Bilinci Geliştirme', 'Bilişim ve İnternet', 'Çevre Koruma', 'Çevre ve İklim',
    'Çocuk Hakları', 'Değerler Eğitimi', 'Dış Kaynaklı Proje', 'Dranaj ve İlk Yardım',
    'Eğitsel İçerik Üretimi', 'Estetik ve Sanat', 'Futbol', 'Görsel Sanatlar',
    'Halk Oyunları', 'İlk Yardım', 'Kadın Hakları veGender', 'Kütüphane ve Kitap',
    'Müzik', 'Münazara', 'Proje Üretimi ve Yönetimi', 'Robotik Kodlama',
    'Sağlık ve Spor', 'Satranç', 'Sinema ve Araştırma', 'Sosyal Yardımlaşma ve Dayanışma',
    'Şiir ve Hikaye', 'Tarih ve Kültür', 'Teknoloji ve Tasarım', 'Tiyatro ve Drama',
    'Uluslararası Proje', 'Yabancı Dil', 'Yazılım ve Veritabanı', 'Yetenek',
    'Yürüyüş ve Doğa', 'Zeka Oyunları'
];

// POST: Ekle / Güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $kulupAdi = trim($_POST['kulup_adi'] ?? '');
    $etkinlikTarihi = trim($_POST['etkinlik_tarihi'] ?? '');
    $etkinlikSaati = trim($_POST['etkinlik_saati'] ?? '');
    $etkinlikYeri = trim($_POST['etkinlik_yeri'] ?? '');
    $katilimciSayisi = (int)($_POST['katilimci_sayisi'] ?? 0);
    $aciklama = trim($_POST['aciklama'] ?? '');
    $hedefler = trim($_POST['hedefler'] ?? '');
    $sonuclar = trim($_POST['sonuclar'] ?? '');

    if ($kulupAdi && $etkinlikTarihi) {
        if ($id > 0) {
            $pdo->prepare("UPDATE kulup_evraklari SET kulup_adi=?, etkinlik_tarihi=?, etkinlik_saati=?, etkinlik_yeri=?, katilimci_sayisi=?, aciklama=?, hedefler=?, sonuclar=? WHERE id=? AND school_id=?")
                ->execute([$kulupAdi, $etkinlikTarihi, $etkinlikSaati, $etkinlikYeri, $katilimciSayisi, $aciklama, $hedefler, $sonuclar, $id, $schoolId]);
            $mesaj = 'Etkinlik güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO kulup_evraklari (school_id, created_by, kulup_adi, etkinlik_tarihi, etkinlik_saati, etkinlik_yeri, katilimci_sayisi, aciklama, hedefler, sonuclar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$schoolId, $ogretmenId, $kulupAdi, $etkinlikTarihi, $etkinlikSaati, $etkinlikYeri, $katilimciSayisi, $aciklama, $hedefler, $sonuclar]);
            $mesaj = 'Etkinlik eklendi.';
        }
    } else {
        $hata = 'Kulüp adı ve etkinlik tarihi zorunludur.';
    }
}

// POST: Sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pdo->prepare("DELETE FROM kulup_evraklari WHERE id=? AND school_id=?")->execute([(int)($_POST['id'] ?? 0), $schoolId]);
    $mesaj = 'Etkinlik silindi.';
}

// Filtre
$filterKulup = trim($_GET['kulup_adi'] ?? '');

$sql = "SELECT * FROM kulup_evraklari WHERE school_id=?";
$params = [$schoolId];
if ($filterKulup) { $sql .= " AND kulup_adi=?"; $params[] = $filterKulup; }
$sql .= " ORDER BY etkinlik_tarihi DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$etkinlikler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$stats = $pdo->prepare("SELECT COUNT(*) as total, SUM(katilimci_sayisi) as total_katilimci FROM kulup_evraklari WHERE school_id=?");
$stats->execute([$schoolId]);
$stats = $stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kulüp Evrakları — Aibüke</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0a0a1a; --surface: rgba(26,26,46,0.85); --surface-solid: #1a1a2e;
            --border: rgba(99,102,241,0.12); --border-solid: #2d2d44;
            --text: #e2e8f0; --muted: #7c8db0;
            --primary: #6366f1; --primary-soft: rgba(99,102,241,0.1);
            --accent: #a78bfa; --success: #22c55e; --error: #ef4444; --warning: #f59e0b;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); }

        .main { margin-left: 240px; padding: 32px 36px; min-height: 100vh; }

        .page-header { margin-bottom: 28px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 24px; font-weight: 700; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--primary); }

        .toast { padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; animation: toastIn 0.4s ease; }
        @keyframes toastIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .toast-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: var(--error); }
        .toast i { font-size: 16px; }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 28px; }
        .stat-card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 16px; padding: 18px; text-align: center; }
        .stat-val { font-size: 28px; font-weight: 800; letter-spacing: -1px; background: linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-lbl { font-size: 11px; color: var(--muted); margin-top: 3px; font-weight: 600; }

        .card { background: var(--surface); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }

        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-select, .form-input { padding: 9px 14px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 10px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; }
        .form-select:focus, .form-input:focus { border-color: var(--primary); }

        .btn { padding: 10px 20px; border-radius: 12px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; font-family: inherit; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; box-shadow: 0 4px 16px rgba(99,102,241,0.25); }
        .btn-primary:hover { transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border-solid); }
        .btn-ghost:hover { color: var(--text); border-color: var(--text); }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 10px; }
        .btn-edit { background: rgba(99,102,241,0.1); color: var(--primary); }
        .btn-del { background: rgba(239,68,68,0.1); color: var(--error); }

        .kulup-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
        .kulup-card { background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 14px; padding: 18px; transition: all 0.2s; }
        .kulup-card:hover { border-color: rgba(99,102,241,0.3); }
        .kulup-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .kulup-adi { font-size: 14px; font-weight: 700; color: var(--accent); }
        .kulup-tarih { font-size: 12px; color: var(--muted); margin-top: 3px; }
        .kulup-info { display: flex; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; }
        .kulup-info-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--muted); }
        .kulup-info-item i { color: var(--primary); }
        .kulup-desc { font-size: 12px; color: var(--muted); margin-bottom: 10px; line-height: 1.5; }
        .kulup-actions { display: flex; gap: 6px; margin-top: 12px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 12px; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state i { font-size: 48px; opacity: 0.2; margin-bottom: 16px; display: block; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; padding: 28px; width: 560px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }

        .form-group { margin-bottom: 16px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 20px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .kulup-grid { grid-template-columns: 1fr; }
            .modal { padding: 20px; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-hat-wizard"></i> Kulüp Evrakları</h1>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Yeni Etkinlik</button>
    </div>

    <?php if ($mesaj): ?><div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
    <?php if ($hata): ?><div class="toast toast-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($hata) ?></div><?php endif; ?>

    <?php $aiType = 'kulup'; $aiExtraLabel = 'Kulüp Adı'; include __DIR__ . '/includes/ai_evrak_widget.php'; ?>

    <!-- İstatistikler -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-val"><?= (int)$stats['total'] ?></div>
            <div class="stat-lbl">Toplam Etkinlik</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= (int)($stats['total_katilimci'] ?? 0) ?></div>
            <div class="stat-lbl">Toplam Katılımcı</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= count(array_unique(array_column($etkinlikler, 'kulup_adi'))) ?></div>
            <div class="stat-lbl">Aktif Kulüp</div>
        </div>
    </div>

    <!-- Filtre -->
    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Kulüp Türü</label>
                <select name="kulup_adi" onchange="this.form.submit()" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($kulupler as $k): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= $filterKulup === $k ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filterKulup): ?>
                <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Etkinlikler -->
    <?php if (empty($etkinlikler)): ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-hat-wizard"></i>
                <p>Henüz etkinlik eklenmemiş.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="kulup-grid">
            <?php foreach ($etkinlikler as $e): ?>
                <div class="kulup-card">
                    <div class="kulup-header">
                        <div>
                            <div class="kulup-adi"><?= htmlspecialchars($e['kulup_adi']) ?></div>
                            <div class="kulup-tarih"><i class="fas fa-calendar" style="margin-right:4px;"></i><?= date('d.m.Y', strtotime($e['etkinlik_tarihi'])) ?></div>
                        </div>
                    </div>
                    <?php if ($e['etkinlik_saati'] || $e['etkinlik_yeri']): ?>
                        <div class="kulup-info">
                            <?php if ($e['etkinlik_saati']): ?>
                                <div class="kulup-info-item"><i class="fas fa-clock"></i> <?= htmlspecialchars($e['etkinlik_saati']) ?></div>
                            <?php endif; ?>
                            <?php if ($e['etkinlik_yeri']): ?>
                                <div class="kulup-info-item"><i class="fas fa-location-dot"></i> <?= htmlspecialchars($e['etkinlik_yeri']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($e['katilimci_sayisi']): ?>
                        <div class="kulup-info">
                            <div class="kulup-info-item"><i class="fas fa-users"></i> <?= (int)$e['katilimci_sayisi'] ?> katılımcı</div>
                        </div>
                    <?php endif; ?>
                    <?php if ($e['aciklama']): ?>
                        <div class="kulup-desc"><?= htmlspecialchars(mb_substr($e['aciklama'], 0, 120)) ?><?= mb_strlen($e['aciklama']) > 120 ? '…' : '' ?></div>
                    <?php endif; ?>
                    <?php if ($e['hedefler']): ?>
                        <div class="kulup-desc" style="color:var(--success);"><i class="fas fa-bullseye" style="margin-right:4px;"></i><?= htmlspecialchars(mb_substr($e['hedefler'], 0, 80)) ?></div>
                    <?php endif; ?>
                    <div class="kulup-actions">
                        <button class="btn btn-sm btn-edit" onclick='editRow(<?= json_encode($e) ?>)'><i class="fas fa-pen"></i></button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-del"><i class="fas fa-trash-can"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Modal -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <span id="modalTitle">Yeni Etkinlik</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="etkinlikForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="f_id" value="0">

            <div class="form-group">
                <label class="form-label">Kulüp Türü *</label>
                <select name="kulup_adi" id="f_kulup_adi" class="form-select" required>
                    <?php foreach ($kulupler as $k): ?>
                        <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Etkinlik Tarihi *</label>
                    <input type="date" name="etkinlik_tarihi" id="f_etkinlik_tarihi" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Etkinlik Saati</label>
                    <input type="time" name="etkinlik_saati" id="f_etkinlik_saati" class="form-input">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Etkinlik Yeri</label>
                <input type="text" name="etkinlik_yeri" id="f_etkinlik_yeri" class="form-input" placeholder="Örn: Spor Salonu, Laboratuvar">
            </div>

            <div class="form-group">
                <label class="form-label">Katılımcı Sayısı</label>
                <input type="number" name="katilimci_sayisi" id="f_katilimci_sayisi" class="form-input" min="0" placeholder="0">
            </div>

            <div class="form-group">
                <label class="form-label">Etkinlik Açıklaması</label>
                <textarea name="aciklama" id="f_aciklama" class="form-textarea" placeholder="Etkinlik hakkında detaylı bilgi..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Hedefler</label>
                <textarea name="hedefler" id="f_hedefler" class="form-textarea" placeholder="Bu etkinlikle ulaşılmak istenen hedefler..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Sonuçlar</label>
                <textarea name="sonuclar" id="f_sonuclar" class="form-textarea" placeholder="Etkinlik sonunda elde edilen sonuçlar..."></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">İptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(data = null) {
    document.getElementById('f_id').value = data ? data.id : 0;
    document.getElementById('modalTitle').textContent = data ? 'Etkinlik Düzenle' : 'Yeni Etkinlik';
    if (data) {
        document.getElementById('f_kulup_adi').value = data.kulup_adi;
        document.getElementById('f_etkinlik_tarihi').value = data.etkinlik_tarihi;
        document.getElementById('f_etkinlik_saati').value = data.etkinlik_saati || '';
        document.getElementById('f_etkinlik_yeri').value = data.etkinlik_yeri || '';
        document.getElementById('f_katilimci_sayisi').value = data.katilimci_sayisi || 0;
        document.getElementById('f_aciklama').value = data.aciklama || '';
        document.getElementById('f_hedefler').value = data.hedefler || '';
        document.getElementById('f_sonuclar').value = data.sonuclar || '';
    } else {
        document.getElementById('etkinlikForm').reset();
    }
    document.getElementById('formModal').classList.add('active');
}
function closeModal() { document.getElementById('formModal').classList.remove('active'); }
function editRow(data) { openModal(data); }
document.getElementById('formModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
</script>
</body>
</html>