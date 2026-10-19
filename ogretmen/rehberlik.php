<?php
/**
 * Aibuke - Rehberlik
 * Öğrenci ve veli görüşme formları ile boy/kilo takibi.
 */
require_once __DIR__ . '/auth.php';
session_start();
requireTeacherLogin();
$ogretmen = getOgretmenInfo();

$userId = $ogretmen["id"];
$schoolId = $ogretmen["school_id"];

$mesaj = '';
$tab = $_GET['tab'] ?? 'gorisme';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);

    if ($_POST['action'] === 'kaydet_gorisme') {
        $ogrenciId = (int)$_POST['ogrenci_id'];
        $tur = $_POST['tur'] ?? null;
        $tarih = trim($_POST['gorisme_tarihi']);
        $konu = trim($_POST['konu']);
        $icerik = trim($_POST['icerik']);
        $sonuc = trim($_POST['sonuc']);
        $takip = trim($_POST['takip_tarihi']) ?: null;
        $not = trim($_POST['olusturan_not']);

        if ($id > 0) {
            $pdo->prepare("UPDATE rehberlik_gorisme SET tur=?, gorisme_tarihi=?, konu=?, icerik=?, sonuc=?, takip_tarihi=?, olusturan_not=? WHERE id=? AND school_id=?")
                ->execute([$tur, $tarih, $konu, $icerik, $sonuc, $takip, $not, $id, $schoolId]);
            $mesaj = 'Görüşme güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO rehberlik_gorisme (ogrenci_id, school_id, created_by, tur, gorisme_tarihi, konu, icerik, sonuc, takip_tarihi, olusturan_not) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$ogrenciId, $schoolId, $userId, $tur, $tarih, $konu, $icerik, $sonuc, $takip, $not]);
            $mesaj = 'Görüşme kaydedildi.';
        }
    } elseif ($_POST['action'] === 'sil_gorisme') {
        $pdo->prepare("DELETE FROM rehberlik_gorisme WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Görüşme silindi.';
    } elseif ($_POST['action'] === 'kaydet_boykilo') {
        $ogrenciId = (int)$_POST['ogrenci_id'];
        $tur = $_POST['takip_turu'];
        $tarih = trim($_POST['olcum_tarihi']);
        $boy = $_POST['boy'] !== '' ? (float)$_POST['boy'] : null;
        $kilo = $_POST['kilo'] !== '' ? (float)$_POST['kilo'] : null;
        $yorum = trim($_POST['yorum']);

        $vki = null;
        if ($boy && $kilo && $boy > 0) {
            $vki = round($kilo / (($boy / 100) ** 2), 2);
        }

        if ($id > 0) {
            $pdo->prepare("UPDATE rehberlik_takip SET takip_turu=?, olcum_tarihi=?, boy=?, kilo=?, vki=?, yorum=? WHERE id=? AND school_id=?")
                ->execute([$tur, $tarih, $boy, $kilo, $vki, $yorum, $id, $schoolId]);
            $mesaj = 'Takip güncellendi.';
        } else {
            $pdo->prepare("INSERT INTO rehberlik_takip (ogrenci_id, school_id, created_by, takip_turu, olcum_tarihi, boy, kilo, vki, yorum) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$ogrenciId, $schoolId, $userId, $tur, $tarih, $boy, $kilo, $vki, $yorum]);
            $mesaj = 'Boy/kilo takibi kaydedildi.';
        }
    } elseif ($_POST['action'] === 'sil_boykilo') {
        $pdo->prepare("DELETE FROM rehberlik_takip WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
        $mesaj = 'Takip silindi.';
    }
}

$ogrenciler = $pdo->prepare("SELECT id, first_name AS ad, last_name AS soyad, class AS sinif FROM students WHERE school_id=? ORDER BY class, first_name, last_name");
$ogrenciler->execute([$schoolId]);
$ogrenciler = $ogrenciler->fetchAll(PDO::FETCH_ASSOC);

$gorismeList = $pdo->prepare("SELECT s.first_name AS ad, s.last_name AS soyad, s.class AS sinif, rg.* FROM rehberlik_gorisme rg JOIN students s ON rg.ogrenci_id=s.id WHERE rg.school_id=? ORDER BY rg.gorisme_tarihi DESC, rg.created_at DESC LIMIT 50");
$gorismeList->execute([$schoolId]);
$gorismeList = $gorismeList->fetchAll(PDO::FETCH_ASSOC);

$boykiloList = $pdo->prepare("SELECT s.first_name AS ad, s.last_name AS soyad, s.class AS sinif, rt.* FROM rehberlik_takip rt JOIN students s ON rt.ogrenci_id=s.id WHERE rt.school_id=? ORDER BY rt.olcum_tarihi DESC, rt.created_at DESC LIMIT 50");
$boykiloList->execute([$schoolId]);
$boykiloList = $boykiloList->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rehberlik - Aibuke</title>
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

        .tabs { display: flex; gap: 8px; margin-bottom: 24px; }
        .tab-btn { padding: 10px 20px; border-radius: 12px; border: 1px solid var(--border-solid); background: transparent; color: var(--muted); font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit; display: flex; align-items: center; gap: 8px; }
        .tab-btn:hover { color: var(--text); border-color: var(--primary); }
        .tab-btn.active { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border-color: transparent; box-shadow: 0 4px 14px rgba(99,102,241,0.25); }
        .tab-btn i { font-size: 14px; }

        .toast { padding: 14px 20px; border-radius: 14px; font-size: 13px; font-weight: 500; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .toast-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
        .toast i { font-size: 16px; }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--primary); }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 12px; border-bottom: 1px solid var(--border-solid); }
        .data-table td { padding: 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.04); }
        .data-table tr:hover { background: rgba(99,102,241,0.04); }
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

        .tur-badge { display: inline-flex; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; }
        .tur-veli { background: rgba(236,72,153,0.1); color: #ec4899; }
        .tur-ogrenci { background: rgba(99,102,241,0.1); color: var(--primary); }
        .vki-badge { font-weight: 700; }
        .vki-normal { color: var(--success); }
        .vki-yuksek { color: var(--warning); }
        .vki-dusuk { color: var(--error); }
        .action-btns { display: flex; gap: 6px; }

        .modal-overlay { opacity: 0; pointer-events: none; transition: opacity 0.25s; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; display: flex; align-items: center; justify-content: center; }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal { background: var(--surface-solid); border: 1px solid var(--border-solid); border-radius: 20px; padding: 28px; width: 520px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; }

        .form-group { margin-bottom: 16px; }
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 7px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-select { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.25); border: 1px solid var(--border-solid); border-radius: 12px; color: var(--text); font-size: 14px; outline: none; font-family: inherit; transition: border-color 0.2s; }
        .form-input:focus, .form-select:focus { border-color: var(--primary); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 76px 16px; }
            .page-header h1 { font-size: 20px; }
            .form-row { grid-template-columns: 1fr; }
            .modal { padding: 20px; }
        }
    select option { background: #1a1a2e; color: #e2e8f0; } select option:checked { background: #6366f1; color: #fff; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1><i class="fas fa-person-heart"></i> Rehberlik</h1>
        <button class="btn btn-primary" onclick="openTab('gorisme'); setTimeout(() => openGorismeModal(), 100)"><i class="fas fa-plus"></i> Yeni Görüşme</button>
    </div>

    <?php if ($mesaj): ?><div class="toast toast-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div><?php endif; ?>

    <?php $aiType = 'rehberlik'; $aiExtraLabel = 'Öğrenci Adı Soyadı'; include __DIR__ . '/includes/ai_evrak_widget.php'; ?>

    <div class="tabs">
        <button class="tab-btn <?= $tab === 'gorisme' ? 'active' : '' ?>" onclick="location.href='?tab=gorisme'">
            <i class="fas fa-comments"></i> Öğrenci/Veli Görüşmeleri
        </button>
        <button class="tab-btn <?= $tab === 'boykilo' ? 'active' : '' ?>" onclick="location.href='?tab=boykilo'">
            <i class="fas fa-ruler-vertical"></i> Boy/Kilo Takibi
        </button>
    </div>

    <?php if ($tab === 'gorisme'): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-list"></i> Görüşme Kayıtları</div>
            <button class="btn btn-primary btn-sm" onclick="openGorismeModal()"><i class="fas fa-plus"></i> Yeni Görüşme</button>
        </div>
        <?php if (empty($gorismeList)): ?>
            <div class="empty-state"><i class="fas fa-comments"></i><p>Kayıt bulunamadı.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Tür</th>
                        <th>Öğrenci</th>
                        <th>Konu</th>
                        <th>Takip</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gorismeList as $row): ?>
                        <tr>
                            <td><span class="date-str"><?= htmlspecialchars($row['gorisme_tarihi']) ?></span></td>
                            <td><span class="tur-badge <?= $row['tur'] === 'veli' ? 'tur-veli' : 'tur-ogrenci' ?>"><?= $row['tur'] === 'veli' ? 'Veli' : 'Öğrenci' ?></span></td>
                            <td><strong><?= htmlspecialchars($row['ad'] . ' ' . $row['soyad']) ?></strong></td>
                            <td><?= htmlspecialchars(mb_substr($row['konu'], 0, 40)) ?><?= mb_strlen($row['konu']) > 40 ? '…' : '' ?></td>
                            <td><?= $row['takip_tarihi'] ? htmlspecialchars($row['takip_tarihi']) : '—' ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-edit" onclick='duzenleG(<?= json_encode($row) ?>)'><i class="fas fa-pen"></i></button>
                                    <button class="btn btn-sm btn-del" onclick='silG(<?= $row['id'] ?>)'><i class="fas fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-ruler-vertical"></i> Boy/Kilo Takibi</div>
            <button class="btn btn-primary btn-sm" onclick="openBoyKiloModal()"><i class="fas fa-plus"></i> Yeni Ölçüm</button>
        </div>
        <?php if (empty($boykiloList)): ?>
            <div class="empty-state"><i class="fas fa-ruler-vertical"></i><p>Kayıt bulunamadı.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Öğrenci</th>
                        <th>Sınıf</th>
                        <th>Tür</th>
                        <th>Boy</th>
                        <th>Kilo</th>
                        <th>VKI</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($boykiloList as $row): ?>
                        <?php
                        $vkiClass = 'vki-normal';
                        if ($row['vki']) {
                            if ($row['vki'] >= 25) $vkiClass = 'vki-yuksek';
                            elseif ($row['vki'] < 18.5) $vkiClass = 'vki-dusuk';
                        }
                        ?>
                        <tr>
                            <td><span class="date-str"><?= htmlspecialchars($row['olcum_tarihi']) ?></span></td>
                            <td><strong><?= htmlspecialchars($row['ad'] . ' ' . $row['soyad']) ?></strong></td>
                            <td><?= htmlspecialchars($row['sinif']) ?>. Sınıf</td>
                            <td><?= $row['takip_turu'] === 'boy' ? 'Boy' : ($row['takip_turu'] === 'kilo' ? 'Kilo' : 'Boy+Kilo') ?></td>
                            <td><?= $row['boy'] ? $row['boy'] . ' cm' : '—' ?></td>
                            <td><?= $row['kilo'] ? $row['kilo'] . ' kg' : '—' ?></td>
                            <td><span class="vki-badge <?= $vkiClass ?>"><?= $row['vki'] ? number_format($row['vki'], 1) : '—' ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-edit" onclick='duzenleBK(<?= json_encode($row) ?>)'><i class="fas fa-pen"></i></button>
                                    <button class="btn btn-sm btn-del" onclick='silBK(<?= $row['id'] ?>)'><i class="fas fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<!-- Görüşme Modal -->
<div class="modal-overlay" id="gorismeModal">
    <div class="modal">
        <div class="modal-header">
            <span id="gModalTitle">Yeni Görüşme</span>
            <button class="modal-close" onclick="closeGorismeModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="kaydet_gorisme">
            <input type="hidden" name="id" id="formGId" value="0">
            <div class="form-group">
                <label class="form-label">Öğrenci</label>
                <select name="ogrenci_id" id="formGOgrId" class="form-select" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($ogrenciler as $o): ?>
                        <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['ad'] . ' ' . $o['soyad']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tür</label>
                    <select name="tur" id="formGTur" class="form-select">
                        <option value="ogrenci">Öğrenci</option>
                        <option value="veli">Veli</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tarih</label>
                    <input type="date" name="gorisme_tarihi" id="formGTarih" class="form-input" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Konu</label>
                <input type="text" name="konu" id="formGKonu" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">İçerik</label>
                <textarea name="icerik" id="formGIcerik" class="form-input" rows="3" style="resize:vertical;"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Sonuç</label>
                <textarea name="sonuc" id="formGSonuc" class="form-input" rows="2" style="resize:vertical;"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Takip Tarihi</label>
                    <input type="date" name="takip_tarihi" id="formGTakip" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Not</label>
                <textarea name="olusturan_not" id="formGNot" class="form-input" rows="2" style="resize:vertical;"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeGorismeModal()">İptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Boy/Kilo Modal -->
<div class="modal-overlay" id="boykiloModal">
    <div class="modal">
        <div class="modal-header">
            <span id="bkModalTitle">Yeni Ölçüm</span>
            <button class="modal-close" onclick="closeBoyKiloModal()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="kaydet_boykilo">
            <input type="hidden" name="id" id="formBKId" value="0">
            <div class="form-group">
                <label class="form-label">Öğrenci</label>
                <select name="ogrenci_id" id="formBKOgrId" class="form-select" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($ogrenciler as $o): ?>
                        <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['ad'] . ' ' . $o['soyad']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tür</label>
                    <select name="takip_turu" id="formBKTur" class="form-select">
                        <option value="boy_kilo">Boy + Kilo</option>
                        <option value="boy">Sadece Boy</option>
                        <option value="kilo">Sadece Kilo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Ölçüm Tarihi</label>
                    <input type="date" name="olcum_tarihi" id="formBKTarih" class="form-input" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Boy (cm)</label>
                    <input type="number" name="boy" id="formBKBoy" class="form-input" step="0.1" min="50" max="250" placeholder="örn: 145">
                </div>
                <div class="form-group">
                    <label class="form-label">Kilo (kg)</label>
                    <input type="number" name="kilo" id="formBKKilo" class="form-input" step="0.1" min="5" max="200" placeholder="örn: 40">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Yorum</label>
                <textarea name="yorum" id="formBKYorum" class="form-input" rows="2" style="resize:vertical;"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeBoyKiloModal()">İptal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Sil Modals -->
<div class="modal-overlay" id="silGModal">
    <div class="modal" style="width:400px;">
        <div class="modal-header">Görüşmeyi Sil<span class="modal-close" onclick="closeSilG()">&times;</span></div>
        <form method="post" style="margin-top:0;">
            <input type="hidden" name="action" value="sil_gorisme">
            <input type="hidden" name="id" id="silGId" value="0">
            <p style="color:var(--muted);margin-bottom:20px;">Bu görüşmeyi silmek istediğinizden emin misiniz?</p>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeSilG()">İptal</button>
                <button type="submit" class="btn btn-del"><i class="fas fa-trash-can"></i> Sil</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="silBKModal">
    <div class="modal" style="width:400px;">
        <div class="modal-header">Kaydı Sil<span class="modal-close" onclick="closeSilBK()">&times;</span></div>
        <form method="post" style="margin-top:0;">
            <input type="hidden" name="action" value="sil_boykilo">
            <input type="hidden" name="id" id="silBKId" value="0">
            <p style="color:var(--muted);margin-bottom:20px;">Bu kaydı silmek istediğinizden emin misiniz?</p>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeSilBK()">İptal</button>
                <button type="submit" class="btn btn-del"><i class="fas fa-trash-can"></i> Sil</button>
            </div>
        </form>
    </div>
</div>

<script>
const gData = <?= json_encode($gorismeList) ?>;
const bkData = <?= json_encode($boykiloList) ?>;

function openTab(tab) { location.href = '?tab=' + tab; }

function openGorismeModal(data) {
    document.getElementById('formGId').value = data ? data.id : 0;
    document.getElementById('gModalTitle').textContent = data ? 'Görüşme Düzenle' : 'Yeni Görüşme';
    if (data) {
        document.getElementById('formGOgrId').value = data.ogrenci_id;
        document.getElementById('formGTur').value = data.tur;
        document.getElementById('formGTarih').value = data.gorisme_tarihi;
        document.getElementById('formGKonu').value = data.konu || '';
        document.getElementById('formGIcerik').value = data.icerik || '';
        document.getElementById('formGSonuc').value = data.sonuc || '';
        document.getElementById('formGTakip').value = data.takip_tarihi || '';
        document.getElementById('formGNot').value = data.olusturan_not || '';
    } else {
        document.getElementById('formGId').value = 0;
    }
    document.getElementById('gorismeModal').classList.add('open');
}
function closeGorismeModal() { document.getElementById('gorismeModal').classList.remove('open'); }
function duzenleG(id) {
    const r = gData.find(x => x.id == id);
    if (r) openGorismeModal(r);
}
function silG(id) { document.getElementById('silGId').value = id; document.getElementById('silGModal').classList.add('open'); }
function closeSilG() { document.getElementById('silGModal').classList.remove('open'); }

function openBoyKiloModal(data) {
    document.getElementById('formBKId').value = data ? data.id : 0;
    document.getElementById('bkModalTitle').textContent = data ? 'Ölçüm Düzenle' : 'Yeni Ölçüm';
    if (data) {
        document.getElementById('formBKOgrId').value = data.ogrenci_id;
        document.getElementById('formBKTur').value = data.takip_turu;
        document.getElementById('formBKTarih').value = data.olcum_tarihi;
        document.getElementById('formBKBoy').value = data.boy || '';
        document.getElementById('formBKKilo').value = data.kilo || '';
        document.getElementById('formBKYorum').value = data.yorum || '';
    } else {
        document.getElementById('formBKId').value = 0;
    }
    document.getElementById('boykiloModal').classList.add('open');
}
function closeBoyKiloModal() { document.getElementById('boykiloModal').classList.remove('open'); }
function duzenleBK(id) {
    const r = bkData.find(x => x.id == id);
    if (r) openBoyKiloModal(r);
}
function silBK(id) { document.getElementById('silBKId').value = id; document.getElementById('silBKModal').classList.add('open'); }
function closeSilBK() { document.getElementById('silBKModal').classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
});
</script>
</body>
</html>
