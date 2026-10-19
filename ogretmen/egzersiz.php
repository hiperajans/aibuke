<?php
/**
 * Aibuke - Egzersiz Planları
 * 50+ egzersiz etkinlik planları (satranç, futbol, halk oyunları, robotik, müzik vb.).
 */

require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();
$ogretmen = getOgretmenInfo();

$userId = $ogretmen["id"];
$schoolId = $ogretmen["school_id"];

$mesaj = '';

// Egzersiz kategorileri (MEB resmi)
$kategoriler = [
    'afet_hazirlik' => 'Afet Hazırlık',
    'astronomi' => 'Astronomi',
    'ataturkcu_dusunce' => 'Atatürkçü Düşünce',
    'bilim_fen' => 'Bilim Fen Teknoloji',
    'bilincli_tuketici' => 'Bilinçli Tüketici',
    'bilisim_internet' => 'Bilişim ve İnternet',
    'cevre_koruma' => 'Çevre Koruma',
    'cevre_iklim' => 'Çevre ve İklim',
    'cocuk_haklari' => 'Çocuk Hakları',
    'degerler' => 'Değerler Eğitimi',
    'din_bilgisi' => 'Din Bilgisi',
    'doga_egitimi' => 'Doğa Eğitimi',
    'drama' => 'Drama ve Tiyatro',
    'futbol' => 'Futbol',
    'gezi_ogretimi' => 'Gezi Öğretimi',
    'gorsel_sanatlar' => 'Görsel Sanatlar',
    'guclendirme' => 'Güçlendirme',
    'halk_oyunlari' => 'Halk Oyunları',
    'ingilizce' => 'İngilizce',
    'iq_zeka' => 'IQ ve Zeka',
    'kaynak_kontrol' => 'Kaynak Kontrol',
    'kisisel_gelistirme' => 'Kişisel Gelişim',
    'kitap_okuma' => 'Kitap Okuma',
    'kodlama' => 'Kodlama ve Robotik',
    'kultur' => 'Kültür ve Sanat',
    'matematik_oyun' => 'Matematik Oyun',
    'muzik' => 'Müzik',
    'nutuk' => 'Nutuk',
    'oyun_ve_spor' => 'Oyun ve Spor',
    'resim' => 'Resim',
    'robotik_kodlama' => 'Robotik Kodlama',
    'satranc' => 'Satranç',
    'savascilar' => 'Savaşçılar',
    'silah_bilgisi' => 'Silah Bilgisi',
    'sivil_m隔a' => 'Sivil Müdahale',
    'sosyal_bilgiler' => 'Sosyal Bilgiler',
    'spor' => 'Spor',
    'tasarim_teknoloji' => 'Tasarım ve Teknoloji',
    'toplanti' => 'Toplantı',
    'türkçe_dil' => 'Türkçe Dil',
    'yabanci_dil' => 'Yabancı Dil',
    'yetenek' => 'Yetenek',
    'yogun_ogretim' => 'Yoğun Öğretim',
    'yuksek_islev' => 'Yüksek İşlev',
    'z_koç' => 'Z Koç',
    'zeka_oyunlari' => 'Zeka Oyunları',
    'spor_egitimi' => 'Spor Eğitimi',
    'saglik_egitimi' => 'Sağlık Eğitimi',
    'cinsel_saglik' => 'Cinsel Sağlık',
    'madde_bilgisi' => 'Madde Bilgisi',
    'tutum_yaptirim' => 'Tutum ve Yaptırım',
    'bilgi_guvenligi' => 'Bilgi Güvenliği',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);

    if ($_POST['action'] === 'kaydet') {
        $adi = trim($_POST['egzersiz_adi']);
        $kategori = $_POST['kategori'];
        $sinif = trim($_POST['sinif']) ?: null;
        $sure = (int)($_POST['sure_dk'] ?? 40);
        $hedefler = trim($_POST['hedefler']);
        $materyaller = trim($_POST['materyaller']);
        $aciklama = trim($_POST['aciklama']);

        if ($id > 0) {
            $pdo->prepare("UPDATE egzersiz_planlari SET egzersiz_adi=?, kategori=?, sinif=?, sure_dk=?, hedefler=?, materyaller=?, aciklama=? WHERE id=? AND school_id=?")
                ->execute([$adi, $kategori, $sinif, $sure, $hedefler, $materyaller, $aciklama, $id, $schoolId]);
            $mesaj = 'Egzersiz güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO egzersiz_planlari (school_id, created_by, egzersiz_adi, kategori, sinif, sure_dk, hedefler, materyaller, aciklama) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$schoolId, $userId, $adi, $kategori, $sinif, $sure, $hedefler, $materyaller, $aciklama]);
            $mesaj = 'Egzersiz eklendi.';
        }
    } elseif ($_POST['action'] === 'sil') {
        $pdo->prepare("DELETE FROM egzersiz_planlari WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Egzersiz silindi.';
    }
}

$filterKat = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
$filterSinif = isset($_GET['sinif']) ? trim($_GET['sinif']) : '';

$sql = "SELECT * FROM egzersiz_planlari WHERE school_id=?";
$params = [$schoolId];
if ($filterKat) { $sql .= " AND kategori=?"; $params[] = $filterKat; }
if ($filterSinif) { $sql .= " AND sinif=?"; $params[] = $filterSinif; }
$sql .= " ORDER BY kategori, egzersiz_adi";

$list = $pdo->prepare($sql);
$list->execute($params);
$list = $list->fetchAll(PDO::FETCH_ASSOC);

$stats = $pdo->prepare("SELECT kategori, COUNT(*) as sayi FROM egzersiz_planlari WHERE school_id=? GROUP BY kategori ORDER BY sayi DESC LIMIT 10");
$stats->execute([$schoolId]);
$topStats = $stats->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Egzersiz Planları - Aibuke</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #0a0a1a; --surface: rgba(26,26,46,0.85); --surface-solid: #1a1a2e;
            --border: rgba(99,102,241,0.12); --border-solid: #2d2d44;
            --text: #e2e8f0; --muted: #7c8db0;
            --primary: #6366f1; --accent: #a78bfa; --success: #22c55e; --error: #ef4444; --warning: #f59e0b;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); }

        .main { margin-left: 240px; padding: 32px 36px; min-height: 100vh; }
        .page-header { margin-bottom: 28px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 24px; font-weight: 700; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: var(--primary); }

        .toast { padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .toast i { font-size: 16px; }

        .stats-row { display: grid; grid-template-columns: 280px 1fr; gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 24px; }
        .stat-card-title { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .stat-card-title i { color: var(--warning); }
        .stat-card-value { font-size: 32px; font-weight: 700; }
        .stat-card-accent { border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); }
        .stat-card-accent .stat-card-title { color: rgba(255,255,255,0.7); }
        .stat-card-accent .stat-card-title i { color: rgba(255,255,255,0.7); }
        .stat-card-accent .stat-card-value { color: #fff; }

        .kategori-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
        .kategori-tag { display: inline-flex; padding: 4px 12px; border-radius: 100px; font-size: 11px; font-weight: 600; background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.85); }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }

        .filter-form { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-input, .filter-select { padding: 10px 14px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 13px; outline: none; font-family: inherit; min-width: 160px; }
        .filter-input:focus, .filter-select:focus { border-color: var(--primary); }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 12px; border-bottom: 1px solid var(--border-solid); }
        .data-table td { padding: 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.04); }
        .data-table tr:hover { background: rgba(99,102,241,0.04); }
        .data-table .egzersiz-adi { font-weight: 600; color: var(--text); }

        .kategori-badge { display: inline-flex; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; background: rgba(245,158,11,0.1); color: var(--warning); }
        .sinif-badge { display: inline-flex; padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; background: rgba(99,102,241,0.1); color: var(--primary); }
        .sure-text { color: var(--muted); font-size: 12px; }

        .empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
        .empty-state i { font-size: 40px; opacity: 0.2; margin-bottom: 12px; display: block; }

        .btn { padding: 10px 20px; border-radius: 12px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; font-family: inherit; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; box-shadow: 0 4px 16px rgba(99,102,241,0.25); }
        .btn-primary:hover { transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border-solid); }
        .btn-ghost:hover { color: var(--text); }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 10px; }
        .btn-edit { background: rgba(99,102,241,0.1); color: var(--primary); }
        .btn-del { background: rgba(239,68,68,0.1); color: var(--error); }
        .action-btns { display: flex; gap: 6px; }

        .modal-overlay { opacity: 0; pointer-events: none; transition: opacity 0.25s; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; display: flex; align-items: center; justify-content: center; }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; padding: 28px; width: 560px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }

        .form-group { margin-bottom: 16px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-select { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; transition: border-color 0.2s; }
        .form-input:focus, .form-select:focus { border-color: var(--primary); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-row-3 { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 20px; }
            .stats-row { grid-template-columns: 1fr; }
            .form-row, .form-row-3 { grid-template-columns: 1fr; }
            .filter-form { flex-direction: column; }
            .filter-input, .filter-select { min-width: unset; }
            .modal { padding: 20px; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-person-walking"></i> Egzersiz Planları</h1>
        <button class="btn btn-primary" onclick="openFormModal()"><i class="fas fa-plus"></i> Yeni Egzersiz</button>
    </div>

    <?php if ($mesaj): ?><div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div><?php endif; ?>

    <!-- Istatistik -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-card-title"><i class="fas fa-list-check"></i> Toplam Egzersiz</div>
            <div class="stat-card-value"><?= count($list) ?></div>
        </div>
        <div class="stat-card stat-card-accent">
            <div class="stat-card-title"><i class="fas fa-chart-bar"></i> En Cok Kullanilan Kategoriler</div>
            <div class="kategori-tags">
                <?php foreach (array_slice($topStats, 0, 5) as $s): ?>
                    <span class="kategori-tag"><?= $kategoriler[$s['kategori']] ?? $s['kategori'] ?> (<?= $s['sayi'] ?>)</span>
                <?php endforeach; ?>
                <?php if (empty($topStats)): ?>
                    <span style="color:rgba(255,255,255,0.5);font-size:13px;">Henuz kategori verisi yok</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filtreler -->
    <div class="card">
        <form method="get" class="filter-form">
            <div class="filter-group">
                <label>Kategori</label>
                <select name="kategori" class="filter-select">
                    <option value="">Tumu</option>
                    <?php foreach ($kategoriler as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $filterKat == $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Sinif</label>
                <input type="text" name="sinif" class="filter-input" placeholder="orn: 5, 9/A" value="<?= htmlspecialchars($filterSinif) ?>">
            </div>
            <div class="filter-group" style="flex-direction:row;gap:8px;align-self:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtrele</button>
                <a href="egzersiz.php" class="btn btn-ghost btn-sm">Temizle</a>
            </div>
        </form>
    </div>

    <!-- Tablo -->
    <div class="card">
        <?php if (empty($list)): ?>
            <div class="empty-state"><i class="fas fa-person-walking"></i><p>Kayit bulunamadi. "Yeni Egzersiz" ile ekleyebilirsiniz.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Egzersiz Adi</th>
                        <th>Kategori</th>
                        <th>Sinif</th>
                        <th>Sure</th>
                        <th>Hedefler</th>
                        <th>Islem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $row): ?>
                        <tr>
                            <td class="egzersiz-adi"><?= htmlspecialchars($row['egzersiz_adi']) ?></td>
                            <td><span class="kategori-badge"><?= $kategoriler[$row['kategori']] ?? $row['kategori'] ?></span></td>
                            <td><?= $row['sinif'] ? '<span class="sinif-badge">' . htmlspecialchars($row['sinif']) . '</span>' : '&mdash;' ?></td>
                            <td><span class="sure-text"><?= $row['sure_dk'] ?> dk</span></td>
                            <td><?= htmlspecialchars(mb_substr($row['hedefler'], 0, 50)) ?><?= mb_strlen($row['hedefler']) > 50 ? '&hellip;' : '' ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-edit" onclick="duzenle(<?= $row['id'] ?>)"><i class="fas fa-pen"></i></button>
                                    <button class="btn btn-sm btn-del" onclick="sil(<?= $row['id'] ?>)"><i class="fas fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<!-- Form Modal -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <span id="formModalTitle">Yeni Egzersiz</span>
            <button class="modal-close" onclick="closeFormModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="kaydet">
            <input type="hidden" name="id" id="formId" value="0">

            <div class="form-group">
                <label class="form-label">Egzersiz Adi</label>
                <input type="text" name="egzersiz_adi" id="formAdi" class="form-input" placeholder="orn: Satrac Temel Egitim" required>
            </div>

            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="kategori" id="formKat" class="form-select" required>
                        <?php foreach ($kategoriler as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sinif</label>
                    <input type="text" name="sinif" id="formSinif" class="form-input" placeholder="orn: 5">
                </div>
                <div class="form-group">
                    <label class="form-label">Sure (dk)</label>
                    <input type="number" name="sure_dk" id="formSure" class="form-input" value="40" min="10" max="180">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Hedefler</label>
                <textarea name="hedefler" id="formHedefler" class="form-input" rows="3" style="resize:vertical;"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Materyaller</label>
                <textarea name="materyaller" id="formMateryaller" class="form-input" rows="2" style="resize:vertical;"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Aciklama</label>
                <textarea name="aciklama" id="formAciklama" class="form-input" rows="3" style="resize:vertical;"></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeFormModal()">Iptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Sil Modal -->
<div class="modal-overlay" id="silModal">
    <div class="modal" style="width:400px;">
        <div class="modal-header">Egzersizi Sil <span class="modal-close" onclick="closeSilModal()">&times;</span></div>
        <form method="post" style="margin-top:0;">
            <input type="hidden" name="action" value="sil">
            <input type="hidden" name="id" id="silId" value="0">
            <p style="color:var(--muted);margin-bottom:20px;">Bu egzersizi silmek istediginizden emin misiniz?</p>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeSilModal()">Iptal</button>
                <button type="submit" class="btn btn-del"><i class="fas fa-trash-can"></i> Sil</button>
            </div>
        </form>
    </div>
</div>

<script>
const veriler = <?= json_encode($list) ?>;

function openFormModal(data) {
    document.getElementById('formId').value = data ? data.id : 0;
    document.getElementById('formModalTitle').textContent = data ? 'Egzersiz Duzenle' : 'Yeni Egzersiz';
    if (data) {
        document.getElementById('formAdi').value = data.egzersiz_adi;
        document.getElementById('formKat').value = data.kategori;
        document.getElementById('formSinif').value = data.sinif || '';
        document.getElementById('formSure').value = data.sure_dk;
        document.getElementById('formHedefler').value = data.hedefler || '';
        document.getElementById('formMateryaller').value = data.materyaller || '';
        document.getElementById('formAciklama').value = data.aciklama || '';
    } else {
        document.getElementById('formAdi').value = '';
        document.getElementById('formKat').value = '';
        document.getElementById('formSinif').value = '';
        document.getElementById('formSure').value = 40;
        document.getElementById('formHedefler').value = '';
        document.getElementById('formMateryaller').value = '';
        document.getElementById('formAciklama').value = '';
    }
    document.getElementById('formModal').classList.add('open');
}
function closeFormModal() { document.getElementById('formModal').classList.remove('open'); }

function duzenle(id) {
    const r = veriler.find(x => x.id == id);
    if (r) openFormModal(r);
}

function sil(id) {
    document.getElementById('silId').value = id;
    document.getElementById('silModal').classList.add('open');
}
function closeSilModal() { document.getElementById('silModal').classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
});
</script>
</body>
</html>
